# Sdílení na Facebook fotkou + karta 4:5 — návrh

**Datum:** 2026-08-31

**Navazuje na:** [`2026-08-30-sdileci-obrazky-design.md`](2026-08-30-sdileci-obrazky-design.md)

## Problém

Automatické sdílení posílá `POST /{page_id}/feed` s `message` a `link`, tedy
odkaz. Facebook z něj složí náhledovou kartu podle `og:image`, což je od
předchozího úkolu vlastní obrázek 1200×630.

Odkazová karta zabírá ve feedu málo místa a formát na šířku je pro mobilní
feed ten nejhorší možný. Fotka na výšku zabere víc než dvakrát tolik plochy.

## Cíl

Sdílet fotkou místo odkazu a vyrobit pro to samostatný obrázek 1080×1350
(poměr 4:5), tedy nejvyšší formát, který Facebook i Instagram ve feedu berou.

## Rozhodnutí a jejich cena

**Fotka vždy, u aktualit i u akcí.** Bez přepínače v redakci.

**Cena je proklik.** Příspěvek s fotkou nemá klikací náhledovou kartu; odkaz
jde do textu popisku, kde ho Facebook podtrhne jako obyčejnou adresu. Prokliknou
ho výrazně méně lidí než celou kartu. Je to vědomá výměna dosahu za návštěvnost,
ne přehlédnutí — Martin ji zvolil s tímhle vysvětlením na stole.

**Vedlejší efekt:** každý sdílený příspěvek se přidá do fotoalba stránky. Tak
se fotopříspěvky na Facebooku chovají, nedá se to vypnout.

## Rozsah

**Je v rozsahu:** automatické sdílení aktualit (`post`) a akcí (`akce` CPT),
tedy přesně to, co `FacebookShare` obsluhuje dnes.

**Není v rozsahu:**

- **Instagram.** Není to jiný parametr téhož volání: potřebuje Instagram
  Business účet propojený se stránkou, oprávnění `instagram_content_publish`,
  publikuje se nadvakrát (kontejner média, pak publikace) a v popisku nefunguje
  žádný odkaz. Je to samostatná integrace a dostane vlastní spec, až bude účet
  připravený. Formát 4:5 a výstup v JPEG jsou zvolené tak, aby jí nic nebránilo.
- Virtuální akce `/akce-db/{id}`. Nemají příspěvek, takže je automatické
  sdílení nikdy neobsluhovalo.
- Obrázek 1200×630. Zůstává beze změny — `og:image` musí být na šířku, jinak
  se rozbijí náhledy odkazů všude, kde někdo adresu vloží ručně.

## Co se mění ve sdílení

### Klient

`Facebook\GraphClient` dostane druhou publikační metodu:

```php
public function publish_photo( string $page_id, string $token, string $message, string $image_url ): array
```

Posílá `POST /{page_id}/photos` s poli `message`, `url` a `access_token`.
Odpověď se zpracuje stávající metodou `parse()`.

**Pozor na to, které ID se ukládá.** `/photos` vrací dvě různá: `id` je
identifikátor fotky, `post_id` identifikátor příspěvku na zdi.
`ShareState::mark_shared()` uloženou hodnotu předává do `ShareMetabox`, který
z ní staví odkaz `https://www.facebook.com/{id}` — tedy odkaz na příspěvek.
Uložit ID fotky by dalo rozbitý odkaz v administraci, a to tiše, protože
odeslání by proběhlo v pořádku.

`publish_photo()` proto zavolá `parse( $response, 'post_id' )` a vrácenou
hodnotu přemapuje do klíče `id`. Volající — `FacebookShare` i příkaz
`wp kct fb_share` — se tak nemusí měnit a dál čtou `$result['id']`.

Stávající `publish()` zůstává beze změny. Slouží jako záloha, viz níž.

**Obrázek se nenahrává, Facebook si ho stáhne sám** z adresy v `url`. Soubor
leží ve `wp-content/uploads`, takže je veřejně dosažitelný.

### Text

`Facebook\MessageComposer` dostane druhou metodu `compose_with_link()`, která
připojí odkaz na konec textu na vlastní řádek. `compose()` zůstává beze změny,
tedy bez odkazu.

**Musí to být dvě metody, ne jedna.** Záložní větev posílá odkaz zvlášť v poli
`link` a Facebook z něj skládá náhledovou kartu; kdyby ho `compose()` přidávala
i tam, byl by na příspěvku dvakrát — jednou jako text, jednou jako karta.
Fotopříspěvek naopak kartu nemá a bez odkazu v textu by nevedl nikam.

Krátké aktuality odkaz nemají už dnes (`is_short_news()` vrací `null`, protože
nemají detail, na který by se dalo odkázat). U nich se text nemění vůbec.

Vlastní text redaktora z metaboxu má i nadále přednost. Odkaz se k němu
připojí stejně jako ke skládanému — redaktor mění znění, ne to, jestli se
odkazuje.

### Orchestrace

`Features\FacebookShare` si vyžádá sdílecí obrázek 4:5. Když ho dostane,
zavolá `publish_photo()`; když ne, zavolá dnešní `publish()` s odkazem.

Zámek, opakované pokusy, evidence stavu i obsluha chyb zůstávají beze změny —
mění se jen to, co se volá uvnitř `try` bloku.

Stejná větev se doplní i do WP-CLI příkazu `wp kct fb_share`, aby ruční
odeslání dopadlo stejně jako automatické.

### Když se fotka nepovede

Sdílení se kvůli obrázku nesmí neuskutečnit. Na dnešní odeslání odkazem se
spadne, kdykoli:

- obrázek se nepodaří vyrobit (`OgImages` vrátí `null` — chybí Imagick, font,
  nebo se render nepovedl),
- **Graph API na `/photos` odpoví a odmítne** (kód chyby větší než nula).

**Rozdíl mezi odmítnutím a mlčením je podstatný.** Když Facebook neodpoví
vůbec — chyba spojení nebo časový limit, tedy kód 0 — neví se, jestli
příspěvek na zdi vznikl. Poslat po tom ještě odkaz by mohlo znamenat dva
příspěvky za sebou. Takový případ se proto nechá spadnout do běžného
opakování, které je oproti tomu chráněné kontrolou `is_shared()`.

Neplatný token (kód 190) je výjimka v druhou stranu: odkaz by dopadl úplně
stejně, takže se jím neplýtvá a rovnou se to předá obsluze chyb, která kvůli
němu vypíše upozornění do administrace.

Přechod na záložní cestu se zapíše do `error_log` i s důvodem od Facebooku.

## Karta 4:5

Nový formát 1080×1350, **JPEG kvalita 88** (řádově 200–400 kB). JPEG proto, že
karta je z velké části fotografická a PNG by u ní byl zbytečně velký — a taky
proto, že Instagram jiný formát nepřijímá.

Existuje **vedle** karty 1200×630, nenahrazuje ji.

### Příspěvek

```
┌────────────────────────┐
│                  [logo]│
│                        │
│      ( fotka )         │   horní dvě třetiny
│                        │
├════════════════════════┤
│  Z regionu             │   barevný panel, spodní třetina
│  Zimní přechod Brd     │
│  skončil rekordní      │
│  účastí                │
│                        │
│  20. 8. 2026 · 4 min   │
╚════════════════════════╝
```

Bez náhledového obrázku (2 příspěvky ze 48) se místo fotky použije přechod,
tedy totéž, co dostane akce bez souřadnic.

### Akce

Karta akce **fotku nepoužije vůbec**, ani když ji akce má. Důvod je v datech:
z 318 importovaných akcí má obrázek 6, a ani u těch není jisté, že se k akci
vztahuje — často je to plakát nebo loňská fotka. Karta by tak vypadala u pěti
procent akcí jinak než u zbytku a nešlo by spolehnout, že to dopadne dobře.
Když fotka nevstupuje, vypadá každá akce stejně a vždy správně.

`EventPosterCard` proto fotku nedostane ani jako vstup a hash názvu souboru
z ní nic nebere.

```
┌────────────────────────┐
│ [všechny ikony]  [logo]│
│                        │
│     ( mapa akce )      │
│                        │
│  ┌───┐ 38. ročník      │
│  │ SO│ Pochod Okolo    │
│  │ 14│ Řevnic          │
│  │kvě│                 │
│  └───┘                 │
├════════════════════════┤
│  START       14. 5. 7:00
│              Řevnice, nádraží
│  CÍL         14. 5. 16:00
│  POŘADATEL   KČT Řevnice
│  ──────────────────────│
│  [ikona] Pěší turistika: 12, 25 km
│  [ikona] Cykloturistika: 40 km
╚════════════════════════╝
```

**Datum vlevo, titulek vedle**, tedy stejná sestava jako na kartě 1200×630.
Zvažovalo se vysázení na střed, ale karty by se pak mezi sebou rozešly.

**Mapa akce vyplňuje horní plochu.** Mapy se generují z mapy.cz při zobrazení
detailu akce a leží v `uploads/maps`. Změřeno: souřadnice má 279 akcí z 319
(87 %) a **všech 279 už mapu na disku má**, takže se nic nestahuje — kreslení
obrázku nezávisí na cizím serveru. Akce bez souřadnic dostane jen přechod.

Mapa je 800×400. Na plnou výšku plochy by se musela zvětšit dvojnásobně
a popisky by se rozmazaly, proto se roztáhne jen na šířku (1,35×) jako pás
a zbytek plochy pod ní dokreslí přechod; spodní okraj mapy se do něj vytratí,
aby tam nebyl šev. Překryje se ztmavením, protože na ní leží ikony, logo
i titulek.

Zkoušela se místo mapy turistická značka jako grafický motiv, odvozená barvou
od měsíce konání. Neosvědčila se: při průhlednosti potřebné k tomu, aby
nekonkurovala textu, nebyly bílé pruhy vidět a zbyly z toho tmavé obdélníky
vypadající jako chyba vykreslení. Mapa navíc nese informaci — je z ní vidět,
kde se akce koná.

**Světlý panel** ve spodní části nese údaje pod sebou, ne ve sloupcích. Na
kartě 1200×630 jsou tři úzké sloupce a dlouhé názvy míst se v nich ořezávají;
na řádcích se vejdou celé.

U příspěvku je panel naopak **ve značkové modré s bílým textem** a titulek
výrazně větší (96 px) — karta se tím ve feedu pozná na první pohled. Chip
kategorie je tam bílý s modrým textem, protože modrý na modrém by zmizel.

Panel akce začíná výš než panel příspěvku (700 proti 810 px): nese při plných
datech tři řádky s poznámkami a ještě tři s délkami tras, a po zvětšení písma
se to do 540 px nevešlo — poslední poznámka narážela do linky nad trasami.
Blok s datem a titulkem tím zároveň dosedne na spodní okraj mapy, místo aby
visel v pruhu mezi ní a panelem.

### Délky tras

Poslední skupina v panelu. Zdrojem je klíč `km` v `details` — pole typů akce
sloučené s nastavením z options (`Events::merge_event_details_data()`).

Hodnota je **volný, už naformátovaný text**, ne číslo. Změřeno v datech:

```
464 vyplněných hodnot, 274 akcí z 319 má aspoň jednu
"12 km"   "14, 17 km"   "9–35 km"   "6,5 km"
"9, 15, 20, 25, 36, 43 km"   "individuální trasy"   "dle propozic"
```

Řádek se proto skládá jen jako `{název typu}: {km}` a nic se nepřepočítává ani
neformátuje. U hodnot jako „dle propozic" to vyjde stejně dobře jako u čísel.

**Nahoře jsou ikony všech typů, dole jen ty s délkou.** Typů bez vyplněného
`km` je v datech 750 z 1329, ty zůstanou jen jako ikona v horním bloku.

Ikony na panelu se kreslí bez bílého podkladu — piktogramy jsou černá kresba
na bílé a světlý panel je pro ně přirozené pozadí. V horním bloku na tmavém
pozadí bílý podklad zůstává, protože bez něj by kolem každé ikony byl bílý
čtverec.

**Strop tři řádky.** Akce má v datech i čtyři typy s délkou; bez stropu by
seznam přerostl přes trikolóru. Co se nevejde, zůstane jako ikona nahoře.

**Ořez dlouhých hodnot.** `„Vysokohorská turistika VHT: 9, 15, 20, 25, 36, 43 km"`
je delší než šířka panelu. Ořeže se s výpustkou stejně jako pořadatel — radši
ořez než přetečení.

## Co to znamená pro hotový kód

`Og\OgImageRenderer` má dnes rozměry v konstantách `WIDTH` a `HEIGHT` a počítá
s nimi v `canvas()`, `gradient()`, `photo()`, `strip()` i `logo()`. Musí se stát
nezávislý na velikosti:

- `canvas()` a `photo()` dostanou rozměry jako parametry,
- `gradient()`, `strip()` a `logo()` si je přečtou z plátna přes
  `getImageWidth()` / `getImageHeight()`,
- rozměry se přesunou z rendereru do jednotlivých karet jako jejich vlastní
  konstanty `WIDTH` a `HEIGHT`.

Je to zásah do třídy dokončené včera, ale mechanický. Alternativa — druhý
renderer pro formát na výšku — by znamenala dvě kopie zalamování textu, ořezu
a kreslení, které se rozejdou při první opravě.

`Og\OgImageService` dnes vrací rozměry z konstant rendereru; nově je vezme
z karty, kterou zrovna kreslí.

## Nové soubory

```
src/Og/PostPosterCard.php    Karta příspěvku 1080×1350.
src/Og/EventPosterCard.php   Karta akce 1080×1350, bez fotky.
src/Og/Waymark.php           Kresba turistické značky jako motivu pozadí.
```

`Waymark` je zvlášť, protože ji potřebují obě karty a s ničím jiným nesouvisí —
dostane plátno, souřadnice a barvu a nakreslí tři obdélníky.

## Úložiště

Název souboru `social-post-12-{hash}.jpg`, `social-akce-345-{hash}.jpg`.
Předpony se nekříží s dnešními (`post-12-…`, `akce-db-345-…`), takže úklid
starších verzí nemůže smazat cizí soubor.

`OgImageStore::save()` a `prune()` dnes počítají s příponou `.png` natvrdo;
musí ji dostat jako parametr.

Obrázek vzniká stejně jako dnes: při uložení příspěvku, a když chybí, tak ve
chvíli sdílení.

## Chybové stavy

| Stav | Chování |
|---|---|
| Karta se nevyrobí | Sdílí se odkazem, jako dnes. |
| Facebook odmítne `/photos` | Sdílí se odkazem; do stavu příspěvku se zapíše, že šlo o náhradní cestu. |
| Ikona typu se nedá stáhnout | Řádek s délkou se vypíše bez ikony. |
| `km` je prázdné nebo není řetězec | Typ nedostane řádek, zůstane jen jako ikona nahoře. |

## Ověření

**Vykreslení karet** se ověří lokálně: JPEG 1080×1350 pro aktualitu, pro akci
s jedním, dvěma a třemi typy s délkou, pro akci bez délek, pro akci s dlouhou
hodnotou `km` a pro akci se čtyřmi typy (strop). Vzorky se zmenší na 500 px,
tedy na šířku, v jaké je Facebook ukazuje ve feedu, a posoudí se čitelnost.

**Skládání textu** se ověří čtením: `compose()` musí vrátit text s odkazem na
konci, a u krátké aktuality text bez odkazu.

**Samotné odeslání fotky se lokálně ověřit nedá.** Facebook si obrázek stahuje
z veřejné adresy a na `sokct.test` nedosáhne. Otestuje se až po nasazení, a to
ručně: `wp kct fb_share <id>` na jednom konkrétním příspěvku, s kontrolou
výsledku na stránce. Spouští to Martin — je to zápis na veřejný profil, ne
ověřovací krok, který by si agent mohl udělat sám.

Do databáze se kvůli ověření nezapisuje.
