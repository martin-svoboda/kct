# Časování sdílení na Facebook — návrh

**Datum:** 2026-08-31

**Navazuje na:** [`2026-08-31-sdileni-fotkou-design.md`](2026-08-31-sdileni-fotkou-design.md)

## Problém

Tři věci, které dnes nesedí:

1. **Jeden výchozí stav pro aktuality i akce.** Nastavení `fb_share_default`
   platí na obojí, ale aktualita a akce jsou jiný druh obsahu a redakce u nich
   může chtít jiné chování.

2. **Akce se odesílá hned po publikaci.** Pozvánka na pochod, který je za půl
   roku, je na Facebooku k ničemu — zapadne dřív, než bude aktuální. Časování
   je u pozvánky to hlavní.

3. **Nastavení „Výchozí náhledový obrázek" už nedělá nic.** Čte ho jen
   `OpenGraph::image_url()`, a `OpenGraph::render()` se celý vypne, když je
   aktivní Rank Math nebo Yoast. Aktuality a akce navíc od minulého úkolu mají
   vlastní generovanou kartu. Zbyla mu podmnožina tak úzká, že setrvačně mate
   víc, než pomáhá — a není vyplněný ani na jednom z osmi webů.

## Rozsah

**Je v rozsahu:** rozdělení výchozího stavu na dvě nastavení, časování
odeslání akcí, odstranění výchozího náhledového obrázku.

**Není v rozsahu:** cokoli kolem samotného odesílání a kreslení karet. Tenhle
úkol určuje jen *kdy* a *jestli* se odešle.

## Rozdělení výchozího stavu

`fb_share_default` se rozpadne na dvě:

```
fb_share_default_post   Nové aktuality sdílet automaticky
fb_share_default_akce   Nové akce sdílet automaticky
```

`Credentials::share_by_default()` nahradí `share_default_for( string $post_type )`.
`ShareState::should_share()` se nemění — dál se ptá na přepsání u konkrétního
příspěvku a jinak vezme výchozí hodnotu, jen ji volající vybere podle typu.

**Migrace.** Stará hodnota není vyplněná ani na jednom z osmi webů, takže
prakticky není co migrovat. Přesto: když `fb_share_default` existuje a nová
nastavení ne, převezme se do obou. Bez toho by web, kde by to někdo mezitím
zapnul, po nasazení tiše přestal sdílet.

## Časování akcí

### Nastavení

```
fb_event_lead_days   Kolik dní před začátkem odeslat   výchozí 12
fb_event_hour        V kolik hodin                     výchozí 9
```

U konkrétní akce jde počet dní přepsat polem `kct_fb_lead_days`; prázdné pole
znamená „použij nastavení webu".

### Proč zrovna 12 dní a 9:00

Většina akcí je o víkendu a zveřejňovat pozvánku o víkendu je špatně — lidi
plánují další víkend na začátku týdne. Odstup 12 dní to trefí:

```
akce v sobotu  →  odeslání v pondělí
akce v neděli  →  odeslání v úterý
```

Ověřeno výpočtem přes všechny dny v týdnu. Devítka ráno je pak doba, kdy si
lidi pročítají, co je čeká.

Není to tedy libovolné číslo a nemá se měnit „aby to bylo kulaté" — kdo ho
bude přenastavovat, měl by vědět, že mění den v týdnu, na který odeslání
padne.

### Kdy přesně se odešle

Vezme se datum začátku akce, nastaví se na zvolenou hodinu a odečte se počet
dní. **Počítá se v časovém pásmu webu, ne v UTC** — jinak by „9:00" v létě
znamenalo 11:00.

| Stav | Chování |
|---|---|
| Vypočtený čas je v budoucnu | Naplánuje se na něj |
| Akce začíná dřív, než je odstup | Odešle se hned |
| **Akce už proběhla** | **Neodešle se vůbec** |
| Akce nemá datum | Odešle se hned |

Proběhlá akce se neodesílá záměrně: pozvánka na loňský pochod je horší než
žádný příspěvek. Netýká se to jen překlepů — příspěvky typu `akce` vznikají
i převodem akcí z centrální databáze, takže se mezi nimi proběhlé akce
objevují běžně.

### Kde se ten čas počítá

**Ne při publikaci.** `transition_post_status` běží uvnitř `wp_insert_post()`,
tedy dřív, než metaboxy uloží svoje metadata — proto má dnešní kód
`DELAY = 60` s komentářem „aby se stihla uložit metadata". Kdyby se datum akce
četlo v tu chvíli, mohlo by ještě chybět.

Rozhodování se proto přesune do `share()`:

1. `maybe_schedule()` zůstává, jak je — naplánuje běh za minutu.
2. `share()` se po dnešních kontrolách zeptá `ShareSchedule` na cílový čas.
   - `null` → akce proběhla, konec, neodesílá se.
   - čas v budoucnu → naplánuje se na něj a běh skončí bez odeslání.
   - jinak → pokračuje se na odeslání jako dnes.

Nekonečnému přeplánovávání to nehrozí: jakmile cílový čas nastane, není už
v budoucnu a odešle se.

Aktuality tímhle rozhodováním neprojdou vůbec — odesílají se hned, jako dnes.

### Když se termín akce změní

Redakce může akci publikovat a pak jí posunout datum. Na `save_post_akce` se
proto naplánované odeslání zruší a naplánuje znovu za minutu, čímž se
rozhodování zopakuje nad novým datem.

Neplatí to pro akci, která už odeslaná je — ta se nechá být. Odeslat pozvánku
podruhé kvůli opravě překlepu v místě startu by bylo horší než neopravit nic.

### Co uvidí redakce

V metaboxu Facebook u akce přibude pole na počet dní a řádek s vypočteným
časem:

```
Sdílet na Facebook          [✓]
Kolik dní předem            [  ]  (výchozí: 12)
                            Odešle se 1. 5. 2026 v 9:00
```

Bez toho řádku by redakce zadávala číslo a doufala. U proběhlé akce se místo
času vypíše, že se neodešle, a proč.

Pole je jen u typu `akce`; u aktualit se nezobrazí.

**Metabox se tím musí rozdělit na dva.** Dnes se registruje jedním voláním
`create_metabox()` s oběma typy obsahu v poli `post_types`. Nově se liší
v obou směrech — výchozí hodnota přepínače (jiné nastavení pro aktuality a pro
akce) i skladba polí (počet dní jen u akcí) — a jedním voláním to nejde.
Společné položky se proto složí v jedné metodě a zaregistrují dvakrát, zvlášť
pro `post` a zvlášť pro `akce`.

## Odstranění výchozího náhledového obrázku

Odstraní se:

- pole `fb_default_image` z nastavení (`Settings.php`),
- metoda `Credentials::default_image_id()`,
- její použití v `OpenGraph::image_url()`.

**Nic se tím prakticky nemění.** Hodnota není vyplněná na žádném z osmi webů,
takže záloha, kterou poskytovala, se dnes stejně nikdy neuplatní. Po
odstranění vrátí `image_url()` v témž případě `null` a tag `og:image` se
prostě nevypíše — což je i dnešní stav.

Uložená hodnota se z databáze nemaže. Není kde — a kdyby někde byla, je to
jen nepoužité ID přílohy.

## Architektura

Nová třída `src/Facebook/ShareSchedule.php`:

```php
public function target_for_event( array $event, ?int $override_days ): ?int
```

Dostane pole akce z `Features\Events::get_event()` a případné přepsání počtu
dní; vrátí unixový čas odeslání, nebo `null` pro „neodesílat".

Žádné WordPress háky, žádné HTTP, žádný zápis. Je to jediná část s netriviální
logikou — časové pásmo, čtyři případy, přepsání — a zároveň jediná, kterou jde
ověřit samostatně na vymyšlených datech, bez publikování čehokoli.

`Features\FacebookShare` ji zavolá v `share()`, přidá hák `save_post_akce`
a předá typ obsahu do `should_share()`.

## Chybové stavy

| Stav | Chování |
|---|---|
| Akce nemá datum ani v post meta, ani v DB řádku | Odešle se hned |
| `fb_event_lead_days` je prázdné nebo nečíselné | Použije se 12 |
| `fb_event_hour` je mimo 0–23 | Použije se 9 |
| Přepsání u akce je prázdné | Použije se nastavení webu |
| Přepsání u akce je záporné | Bere se jako 0, tedy odeslat v den akce |

## Ověření

`ShareSchedule` se ověří na vymyšlených datech přes `eval-file`: akce za měsíc,
akce za tři dny, akce včerejší, akce bez data, akce s přepsáním, a kontrola,
že sobotní akce vyjde na pondělí a nedělní na úterý.

Rozdělení výchozího stavu se ověří čtením `should_share()` pro oba typy
obsahu, se zapnutým i vypnutým nastavením.

Odstranění výchozího obrázku se ověří tím, že `og:image` na stránce bez
náhledového obrázku vypadá stejně před i po.

Do databáze se kvůli ověření nezapisuje a na Facebook se nic neodesílá.
