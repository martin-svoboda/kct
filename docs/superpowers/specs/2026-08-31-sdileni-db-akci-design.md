# Sdílení databázových akcí na Facebook — návrh

**Datum:** 2026-08-31

**Navazuje na:** [`2026-08-31-sdileni-fotkou-design.md`](2026-08-31-sdileni-fotkou-design.md)
a [`2026-08-31-casovani-sdileni-design.md`](2026-08-31-casovani-sdileni-design.md)

## Problém

Automatické sdílení obsluhuje jen typy `post` a `akce`. Akce z centrální
databáze KČT žijí na virtuálních stránkách `/akce-db/{id}` a **žádný příspěvek
nemají**, takže se jich sdílení nedotkne vůbec.

Přitom je jich drtivá většina: z 319 akcí, které sokct.cz vypisuje, má vlastní
příspěvek 12. Zbylých 307 se na Facebook nedostane jinak než ručně.

Převod na příspěvek („Převést na vlastní akci") to neřeší — je jednosměrný,
překlopí adresu na `/akce/{slug}/` a u stovky akcí ročně je to stovka
zbytečných příspěvků.

## Rozsah

**Je v rozsahu:** odesílání akcí z `wp_db_events`, které daný web vypisuje,
včetně stavu, práva a ovládání.

**Není v rozsahu:** změny v odesílání příspěvků a CPT akcí; ta část je hotová
a beze změny. Text a obrázek se skládají stejně jako dnes.

## Analýza: unese to WP-Cron?

Návrh stojí na denní WP-Cron události. WP-Cron se spouští při požadavcích na
web, takže u málo navštěvovaného webu hrozí, že se nespustí včas. Změřeno na
produkci 31. 8. 2026:

**Provoz za 17 hodin:**

```
sokct.cz                  8389 požadavků
kctricany.cz              7555
kctpodebrady.sokct.cz     1820
kctvltavin.cz              803
kctzdice.sokct.cz          187
kctrakovnik.sokct.cz        13
```

**Počet požadavků ale není počet spuštění cronu.** WP-Optimize servíruje
stránkovou cache z `advanced-cache.php` a request ukončí dřív, než se
WordPress dostane ke spuštění cronu. Cachovaný pohled cron nespustí. Vidět je
to na kctpodebrady: 1820 požadavků, tedy jeden za 34 sekund, a přesto se za
měřených 130 sekund cron nespustil.

**Přímé měření stavu cronu:**

```
události po termínu     0 na všech 6 webech (měřeno v sekundách)
posun za 130 s          kctvltavin ANO, kctzdice ANO, kctpodebrady NE
poslední běh denní      kctpodebrady  před ~1 h 41 min
události                kctvltavin    před ~21 h 30 min
```

**Závěr:** cron běží všude a nikde nic neuvízlo — ani jedna událost není po
termínu. Latence se liší podle provozu: na živých webech sekundy, na nejtišším
řádově hodiny, nejhorší naměřené zpoždění pod dvě hodiny.

Pro denní odeslání v 9:00 to stačí. Pozvánka na akci za dvanáct dní není
termínovaná operace; jestli odejde v 9:00 nebo v 10:30, nikoho nezajímá.
Vadilo by propadnutí celého dne — a to se podle měření nestává.

Hodinová událost by naopak nic nezískala: na tichém webu by se stejně spustila
jednou za pár hodin, jen by mezitím víckrát nadarmo hledala.

## Úložiště stavu

Stav odeslání jde do **nového sloupce `fb_share` v `wp_db_events`**, v němž je
JSON s klíči podle ID webu:

```json
{
  "1": { "sent": 1756630800, "fb": "311386038723747_122306465594323532" },
  "2": { "off": true }
}
```

Tabulka je jedna pro celou síť a tatáž akce se legitimně objevuje na oblastním
i odborovém webu — každý má vlastní facebookovou stránku, takže stav musí být
per web. Rozdělení podle ID webu to řeší uvnitř jednoho řádku.

**Hlavní důvod je přehled.** Jedním dotazem je vidět, kdo co kde sdílel:

```sql
SELECT db_id, date, title, fb_share
FROM wp_db_events WHERE fb_share IS NOT NULL;
```

### Zápis musí být atomický

Naivní „načti řádek, uprav JSON v PHP, ulož" by byl závod: sokct.cz vidí akce
celé oblasti a odborový web tytéž svoje, takže oba mohou tentýž řádek
zpracovávat současně — a jeden by druhému zápis přepsal. Ztracený záznam
„odesláno" znamená odeslání podruhé, tedy duplicitní příspěvek na Facebooku.

Zapisuje se proto **jedním příkazem, mimo model**:

```sql
UPDATE wp_db_events
SET fb_share = JSON_MERGE_PATCH( COALESCE(fb_share, '{}'), %s )
WHERE db_id = %d
```

kde parametrem je `{"<blog_id>": {...}}`. Klíče ostatních webů zůstanou
netknuté a celá operace je na úrovni řádku atomická. Odebrání záznamu se dělá
týmž příkazem s `{"<blog_id>": null}` — `JSON_MERGE_PATCH` klíč s hodnotou
`null` odstraní.

**Název tabulky se skládá z `$wpdb->base_prefix`, ne z `prefix`.** Tabulka je
jedna pro celou síť a jmenuje se `wp_db_events`; `$wpdb->prefix` je na webu 2
`wp_2_`, takže by ukazoval na tabulku, která neexistuje. `DbEventRepository`
to dnes řeší tím, že si před dotazem přepne na web 1 (`get_by_db_id()`
i `find_all_by_date()` to dělají), ale u vlastního SQL je `base_prefix`
jednodušší a nemá vedlejší účinky.

**Past, kterou to přináší:** kdyby se použilo přepnutí na web 1, muselo by se
`get_current_blog_id()` přečíst PŘED ním — po přepnutí vrací 1 a stav by se
zapsal pod klíč hlavního webu bez ohledu na to, odkud se odesílá. S
`base_prefix` tenhle problém nevzniká.

**Čte se naopak přes model**, který sloupec typu `Column::JSON` deserializuje
sám.

Ověřeno na produkci: MariaDB 10.11.18, `JSON_MERGE_PATCH` se chová podle
očekávání v obou směrech.

### Cena migrace

Přidat sloupec znamená přidat atribut do `DbEventModel` a zvýšit verzi
repozitáře. `CustomTableRepository::migrate()` pak schéma dorovná přes
`dbDelta()` sám při dalším načtení. `Column::JSON` se ukládá jako `text`
a v modelu už se používá (`organiser`, `image`, `proposal`), takže se
nezavádí nic nového.

### Co se stane při zrušení akce

Import maže řádky akcí, které feed označí `deleted=Y`. Se řádkem zmizí i stav
odeslání — a je to tak správně: zrušená akce, která by se do feedu vrátila, je
nová akce a pozvánka na ni se má poslat znovu.

### Proč ne option

Zvažovalo se držet stav v option daného webu (jedna na rok). Fungovalo by to,
ale znamenalo by to osm oddělených úložišť místo jednoho a žádný způsob, jak
se jedním pohledem podívat, co kde odešlo. Jediné dvě námitky proti sloupci —
závod o zápis a cena migrace — po ověření neobstály.

## Spouštění

**Jedna denní WP-Cron událost na každém webu**, kterou si plugin naplánuje sám
na hodinu z nastavení (`fb_event_hour`, výchozí 9). Žádný systémový cron —
plugin se distribuuje i jako balíček pro weby mimo tuhle síť a nesmí vyžadovat
zásah do serveru.

Úloha udělá jedno:

```
najdi akce, které tenhle web vypisuje
    a jejichž den odeslání je dnešek nebo dva předchozí dny
    a nemají vypnuto, ještě neodešly, nemají vlastní příspěvek
odešli je
```

Den odeslání je `datum akce − fb_event_lead_days` (výchozí 12). Odstup je
**globální, ne per akce** — den odeslání je proto z data akce jednoznačný
a dotaz se scvrkne na úzký rozsah dat místo procházení celé tabulky.

**Okno tří dnů** je pojistka proti tomu, aby akce propadla, kdyby web na den
ztichl, cache se zpřísnila nebo někdo předsadil CDN. Je levné: pořád je to
rozsah tří dat, ne celá tabulka.

Z okna zároveň plyne, že se **nemá co nahromadit** — akce starší než tři dny se
nikdy neodešlou samy. Při prvním spuštění se tedy na Facebook nevysype
historie; kdo chce starší akci poslat, použije tlačítko.

## Právo na akci

**Web smí odeslat akci právě tehdy, když ji sám vypisuje.** Žádné nové pravidlo
se nevymýšlí — je to filtr podle `id_code`, který `Events::get_events()` už
používá. `code_type()` odvozuje z délky kódu: tři číslice = oblast, šest =
odbor.

```
sokct.cz          102       oblast  →  akce celé Středočeské oblasti
kctricany.cz      102100    odbor   →  jen svoje
kctrakovnik       102131    odbor
kctpodebrady      102093    odbor
kctzdice          102033    odbor
kctvltavin        102126    odbor
```

Filtr se vytáhne z `get_events()` do vlastní veřejné metody, ať není na dvou
místech a nerozejde se.

**Akce s vlastním příspěvkem se přeskočí** — o ni se stará běžné sdílení
a odešla by dvakrát.

**Hlavní vypínač** je stávající nastavení `fb_share_default_akce`. Když je
vypnuté, neodesílají se ani DB akce. Nové nastavení nepřibývá.

## Text a obrázek

**Obrázek** už existuje: `Features\OgImages::social_for_event( array $event )`
bere pole akce, ne příspěvek, takže se použije beze změny.

**Text** dnes skládá `MessageComposer::event_message( WP_Post $post )` ze
souboru post meta. Pro DB akci ta meta neexistují, ale tytéž hodnoty jsou
v poli akce. Skládání se proto rozdělí: společná část dostane titulek, datum,
čas, místo a perex jako hodnoty, a nad ní budou dvě tenké metody — jedna čte
z příspěvku, druhá z pole akce. Bez toho by se oba formáty při první úpravě
rozešly.

Odkaz u DB akce je `home_url( 'akce-db/{db_id}' )`.

## Ovládání

Na `/akce-db/{id}` uvidí přihlášený redaktor s právem `edit_posts` pod obsahem,
vedle stávajícího odkazu na převod:

```
Facebook: odešle se 24. 8. 2026     [Nesdílet]  [Odeslat hned]
```

Po odeslání se to změní na potvrzení s odkazem na příspěvek na Facebooku.
U akce, kterou web nevypisuje, se nezobrazí nic — nemá na ni právo. Obě
tlačítka jsou odkazy s nonce, stejně jako stávající „Převést na vlastní akci".

Nová obrazovka v administraci se nedělá. Byla by to větší práce než zbytek
funkce dohromady a přehled o tom, co odejde, dává i ten řádek na stránce akce.

## Architektura

```
src/Facebook/ShareStore.php     nové — rozhraní stavu odeslání
src/Facebook/DbShareState.php   nové — stav DB akcí ve sloupci fb_share
src/Facebook/Publisher.php      nové — vlastní odeslání (fotka, jinak odkaz)
src/Features/DbEventShare.php   nové — denní úloha, ovládání, odeslání DB akcí
```

**`ShareStore`** popisuje, co odesílání od stavu potřebuje: `is_shared()`,
`should_share()`, `claim()`, `release()`, `mark_shared()`, `mark_error()`.
Stávající `ShareState` (post meta) rozhraní splňuje už dnes, jen se dopíše
`implements`.

**`Publisher`** je extrakce z `FacebookShare::share()`: dostane text, odkaz
a případnou adresu obrázku, pošle fotku, a když ji Facebook odmítne, spadne na
odkaz. Dnes je ta logika uvnitř `FacebookShare`; se druhým volajícím by se
kopírovala, a je to zrovna ta část, kde na kopii záleží nejvíc — chybová větev
se v provozu potká zřídka a rozdíl mezi kopiemi by nikdo nezpozoroval.

Musí si s sebou vzít i rozlišení, které v `FacebookShare` vzniklo minule:
na odkaz se spadne, jen když Facebook **odpoví a odmítne** (kód větší než
nula). Když neodpoví vůbec (kód 0, chyba spojení nebo časový limit), neví se,
jestli příspěvek na zdi vznikl, a odeslat po tom ještě odkaz by mohlo znamenat
dva příspěvky za sebou. Neplatný token (190) se rovnou předá obsluze chyb.

**Zámek** proti dvojímu odeslání funguje stejně jako u příspěvků, jen klíčovaný
`db_id`.

## Chybové stavy

| Stav | Chování |
|---|---|
| Akce nemá datum | Neodešle se — nedá se spočítat den odeslání |
| Akci web nevypisuje | Neodešle se, ovládání se nezobrazí |
| Akce má vlastní příspěvek | Přeskočí se, řeší ji běžné sdílení |
| Sdílecí obrázek se nevyrobí | Odešle se odkazem, jako u příspěvků |
| Facebook odmítne fotku | Odešle se odkazem; přechod se zapíše do logu |
| Facebook neodpoví (kód 0) | Neopakuje se automaticky — příspěvek na zdi mohl vzniknout |
| Neplatný token (kód 190) | Zapíše se chyba, upozornění v administraci řeší stávající kód |

## Ověření

**Výběr akcí** se ověří čtením: úloha se pustí v režimu nanečisto (WP-CLI
příkaz `wp kct fb_due --dry-run`) a vypíše, které akce by na kterém webu
odeslala a proč. Kontroluje se, že oblastní web vidí akce celé oblasti,
odborový jen svoje, že akce s vlastním příspěvkem chybí a že akce mimo okno
tří dnů chybí taky.

**Stav** se ověří dotazem na sloupec `fb_share` po odeslání — pod klíčem
daného webu musí být ID příspěvku na Facebooku, ne ID fotky. Zároveň se ověří,
že zápis z jednoho webu nesmazal klíč druhého: nastaví se ručně klíč cizího
webu, odešle se z tohoto, a oba klíče musí zůstat.

**Ovládání** se ověří na `/akce-db/{id}` v prohlížeči.

**Odeslání samo** se lokálně ověřit nedá, Facebook nedosáhne na `sokct.test`.
Otestuje se po nasazení jedním ručním „Odeslat hned" na konkrétní akci.
Spouští Martin — je to zápis na veřejný profil.

Do databáze se kvůli ověření nezapisuje.
