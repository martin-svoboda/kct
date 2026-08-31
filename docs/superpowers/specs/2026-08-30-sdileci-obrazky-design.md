# Sdílecí obrázky příspěvků a akcí — návrh

**Datum:** 2026-08-30

## Problém

Odkaz na příspěvek nebo akci vypadá na Facebooku podle toho, co zrovna najde
`og:image`. Dnes to je:

- **příspěvek** — náhledový obrázek v plné velikosti, tedy holá fotka bez
  jakéhokoli kontextu; kdo v ní nepozná místo, nepozná ani o čem odkaz je,
- **akce** — `EventSeoData::image()` vrátí obrázek z importu, a když žádný není
  (což je skoro vždy), spadne to na logo `images/kct_barva.png`.

Změřeno v databázi sokct:

```
db_events (importované, virtuální):  318   z toho s obrázkem  6
akce publish:                         12   z toho s náhledem  8
post publish:                         48   z toho s náhledem 46
```

Akce tedy sdílí logo ve 312 případech z 318. Příspěvky sdílí fotku bez titulku.
Ani jedno nenese informaci, kvůli které by na to někdo klikl.

## Cíl

Generovat vlastní sdílecí obrázek 1200×630 pro příspěvky a akce. U příspěvku
fotografická karta odvozená z hero hlavičky detailu, u akce datová karta, která
nese to podstatné i bez fotky.

## Rozsah

**Je v rozsahu:**

- typ obsahu `post` (aktuality),
- typ obsahu `akce` (skutečné příspěvky akcí),
- virtuální stránky `/akce-db/{id}` (akce z centrální databáze KČT),
- všechny weby v síti — plugin `kct` je aktivovaný pro celou síť, takže se
  feature registruje všude bez nastavování.

**Není v rozsahu:**

- stránky, odbory, archivy a homepage — ty si vystačí s dnešním chováním
  (`OpenGraph::image_url()` → náhled, jinak výchozí obrázek z nastavení),
- ruční editace sdílecího obrázku v administraci; kdo chce jiný, změní
  náhledový obrázek příspěvku,
- Twitter/X specifické varianty — `twitter:image` dostane tentýž soubor,
  poměr 1200×630 je pro `summary_large_image` správně.

## Vzhled

Obě karty jsou 1200×630 PNG, vnější odsazení 48 px, dole trikolórní pruh 8 px.

Pruh kopíruje mixin `kct-strip` z `core/_variables.scss`. Ten kreslí modrý pás
přes celou šířku a přes něj trikolóru širokou `min(100%, 1440px)` — při šířce
1200 px tedy trikolóra zabere celou šířku a modrý pás pod ní není vidět. Na
kartě se proto kreslí rovnou jen trikolóra: červená, zelená, žlutá po třetinách
(`--kct-red` `#E4032E`, `--kct-green` `#009640`, `--kct-yellow` `#FFCC00`).

Logo se kreslí vpravo nahoře, výška 56 px, šířka dopočtená podle poměru stran.
Které logo, se ale liší podle typu obsahu:

- **příspěvek** — `custom_logo` daného webu, jinak `images/kct_barva.png`
  ze šablony. Aktualita patří tomu webu, na kterém je napsaná. Když je
  `custom_logo` SVG, použije se rovnou PNG ze šablony — Imagick na produkci
  SVG neumí (viz meta řádek níž).
- **akce** — vždy obecné logo KČT `images/kct_barva.png`, nikdy `custom_logo`.

Ten rozdíl není libovůle. `Seo\EventSeoData::fallback_image()` už dnes stejné
rozhodnutí obsahuje a zdůvodňuje ho: „Záměrně ne `custom_logo` daného webu: na
oblastním webu se vypisují akce a odbory z celé oblasti, takže logo oblasti je
u cizího odboru nemístné." Sdílecí obrázek akce pořádané cizím odborem by
s logem oblasti tvrdil něco, co není pravda. Držím se toho, co v kódu platí.

### Příspěvek — fotografická karta

```
┌────────────────────────────────────────────────┐
│                                    [logo KČT]  │
│                                                │
│                  ( fotka přes celou plochu )   │
│                                                │
│  ┌──────────┐                                  │
│  │ Z regionu│                                  │
│  └──────────┘                                  │
│  Zimní přechod Brd skončil                     │
│  rekordní účastí                               │
│  📅 14. 5. 2026    •    4 min čtení            │
│════════════════════════════════════════════════│
└────────────────────────────────────────────────┘
```

Vrstvy zdola nahoru:

1. **Fotka** — náhledový obrázek ve velikosti `full`, oříznutý na 1200×630
   přes `cropThumbnailImage` (zachová poměr, ořízne přetékající stranu).
2. **Spodní gradient** — `transparent → rgba(13, 25, 38, .92)` přes spodních
   60 % výšky. Barva je `--surface-invert` `#0d1926`, tedy tatáž tma jako
   v `posthero.scss`.
3. **Horní gradient** — `rgba(13, 25, 38, .72) → transparent` přes horních
   150 px, aby logo drželo na světlé fotce.
4. **Chip kategorie** — první kategorie příspěvku (`get_the_category()[0]`),
   bílý text v zaobleném obdélníku `--kct-blue` `#1466B0`, poloměr 6 px
   (`--radius-sm`), Plus Jakarta Sans 700, 22 px, odsazení 14/8 px.
   Bez kategorie se chip vynechá a titulek se posune dolů na jeho místo.
5. **Titulek** — Oswald 700, 62 px, bílá, řádkování 70 px, maximálně 3 řádky.
   Delší se ořízne na poslední vejdoucí slovo a doplní `…`.
6. **Meta řádek** — Plus Jakarta Sans 500, 26 px, `rgba(255,255,255,.86)`,
   ve tvaru `14. 5. 2026   •   4 min čtení`. Doba čtení se počítá stejně jako
   v `template-parts/post-hero.php`:
   `max(1, round(str_word_count(wp_strip_all_tags(obsah)) / 200))`.

   **Bez ikon**, na rozdíl od hero hlavičky na webu. Ikony kalendáře a hodin
   jsou v šabloně inline SVG a Imagick by je musel vykreslit — jenže:

   ```
   DDEV:      SVG: ano   MSVG: ano   → readImageBlob() se SVG projde
   produkce:  SVG: NE    MSVG: NE    → nevykreslí se nic
   ```

   Na produkci Imagick SVG delegáta nemá. Ikony by tedy fungovaly lokálně a na
   produkci beze slova zmizely, což je horší než je nemít vůbec. Ve zmenšenině,
   v jaké Facebook náhled ukazuje, by stejně skoro nebyly vidět.

Příspěvek bez náhledového obrázku (2 ze 48) dostane místo fotky grafické
pozadí popsané níž. Zbytek vrstev zůstává.

### Akce — datová karta

```
┌────────────────────────────────────────────────┐
│                                    [logo KČT]  │
│  ┌──────┐  38. ročník                          │
│  │  SO  │  Pochod Okolo Řevnic                 │
│  │  14  │                                      │
│  │ kvě  │                                      │
│  └──────┘                                      │
│  ──────────────────────────────────────────    │
│  START           CÍL            POŘADATEL      │
│  14. 5. 7:00     14. 5. 16:00   KČT Řevnice    │
│  Řevnice, nádr.  Řevnice        okr. Praha-záp.│
│════════════════════════════════════════════════│
└────────────────────────────────────────────────┘
```

**Pozadí.** Karta je primárně grafická a datová, fotka je jen textura. Když
akce obrázek má, vloží se oříznutý na 1200×630, odbarví se na 35 % sytosti
(`modulateImage(100, 35, 100)`) a překryje se plochou `rgba(13, 25, 38, .82)`.
Zůstane z ní tvar a nálada, ne detail. Když obrázek nemá — což je 312 z 318 —
použije se grafické pozadí: svislý přechod `--surface-invert` `#0d1926` →
`#16304a` (tmavší odstín `--kct-blue`), bez vzoru a bez ilustrace.

**Blok s titulkem** sedí v horní třetině, ne u dolní hrany:

- **Datumová kartička** vlevo, kopíruje komponentu `.event-date` z
  `core/blocks/events.scss`: bílý zaoblený obdélník 100×122 px, poloměr 12 px,
  vlas `--line` `#eef1f5`; hlavička výšky 36 px v `--kct-blue` se zaoblenými
  jen horními rohy, v ní zkratka dne v týdnu Oswald 700, 19 px, bílá; pod ní
  číslo dne Oswald 700, 46 px, `--text` `#16202b`; dole měsíc Plus Jakarta
  Sans 500, 22 px, `--text-muted` `#7b8492`.

  **Hodnoty se neskládají znovu.** Bere je `kct_format_event_date()`
  z `kct.php`, tedy tentýž zdroj, ze kterého kartu vykresluje
  `kct_render_event_item()` v `themes/kct/inc/template-tags.php`. Vrací
  `day_abbr`, `day`, `month`, `is_range`, `end_day`, `end_month` a
  `days_label`, všechno přes `date_i18n()`, takže názvy dnů i měsíců jsou
  české bez vlastní tabulky. Funkce navíc řeší skloňování počtu dní
  (`den` / `dny` / `dní`), což je přesně ta drobnost, kterou by vlastní
  implementace udělala špatně.

  Jeden rozdíl proti webu: `day_abbr` vrací `So`, ne `SO` — na webu verzálky
  dělá `text-transform: uppercase`. Imagick nic takového nemá, takže je
  renderer udělá sám přes `mb_strtoupper()` s UTF-8.

  Vícedenní akce (`is_range`) má v hlavičce místo dne v týdnu `days_label`
  (`3 dny`) a v těle dvě dvojice číslo + měsíc oddělené pomlčkou `–`, stejně
  jako komponenta na webu. Kartička se v tom případě rozšíří na 150 px.

- **Ročník** je první řádek textového sloupce vpravo od kartičky, tedy přímo
  nad titulkem a zarovnaný s ním na stejnou levou hranu. Text `{N}. ročník`,
  Plus Jakarta Sans 600, 24 px, `--hero-eyebrow` `#8fd0ff`. Bez ročníku
  (`event['year']` prázdný) řádek odpadá a titulek se posune nahoru na jeho
  místo.

- **Titulek** Oswald 700, 54 px, bílá, řádkování 62 px, maximálně 2 řádky,
  delší se ořízne s `…`.

**Datový pás** u dolní hrany, nad trikolórou, oddělený vodorovnou linkou
`rgba(255,255,255,.18)`. Tři sloupce stejné šířky přes celou obsahovou šířku
(1104 px, mezery 32 px, tedy 346 px na sloupec). Každý sloupec má:

- **popisek** — Plus Jakarta Sans 700, 20 px, verzálky, prostrkání 0,06 em,
  `rgba(255,255,255,.62)`,
- **hlavní hodnotu** — Oswald 500, 30 px, bílá,
- **doplněk** — Plus Jakarta Sans 400, 24 px, `rgba(255,255,255,.78)`.

Obsah sloupců odpovídá infoboxům pod hlavičkou detailu akce
(`template-parts/content-akce.php`):

| Sloupec | Hlavní hodnota | Doplněk |
|---|---|---|
| `START` | datum `j. n.` + čas, když je | `start.place` |
| `CÍL` | datum `j. n.` + čas, když je | `finish.place` |
| `POŘADATEL` | `organiser.name` | `place`, a když je `district`, tak `, okr. {district}` |

Hodnoty se ořezávají na šířku sloupce s `…`. Sloupec, ke kterému nejsou data,
se vynechá a zbylé se roztáhnou rovnoměrně — u části importovaných akcí chybí
cíl, a u 42 záznamů je čas startu prázdný, takže tohle je běžný stav, ne
výjimka.

## Data pro karty

Karta nesahá do WordPressu ani do databáze. Dostane hotovou strukturu, kterou
složí `Features/OgImages.php` z toho, co už v projektu je:

- **příspěvek** — `get_the_title()`, `get_the_category()`,
  `get_post_thumbnail_id()`, `get_the_date()`, obsah pro dobu čtení,
- **akce** — pole z `Features\Events::get_event()`, tedy tentýž zdroj, ze
  kterého se vykresluje detail. Části data pro kartičku dá
  `kct_format_event_date()` z `kct.php`; formátování celého data a kontrolu,
  jestli akce už proběhla, umí `Seo\EventSeoData` (`format_date()`,
  `is_past()`).

Žádná z těchto funkcí se nepíše znovu. Kdyby se na kartě datum formátovalo
jinak než ve výpisu akcí, rozešlo by se to při první změně jedné z kopií.

## Architektura

```
src/Og/OgImageRenderer.php   Imagick primitiva: plátno, svislý přechod,
                             zaoblený obdélník, zalomení a ořez textu, kresba
                             textu, trikolóra, logo, načtení a ořez fotky.
                             Nezná WordPress ani typy obsahu.
src/Og/PostCard.php          Rozvržení příspěvku. Dostane data a renderer,
                             vrátí PNG blob.
src/Og/EventCard.php         Rozvržení akce. Totéž.
src/Og/OgImageStore.php      Disk: sestavení cesty, zápis, smazání starších
                             verzí téhož objektu, převod cesty na URL.
src/Og/OgImageService.php    Orchestrace: z dat spočítá klíč, zeptá se
                             úložiště, a když soubor chybí, nechá kartu
                             vyrenderovat a uloží ho. Vrací URL.
src/Features/OgImages.php    Háky WordPressu, spouštěče a napojení na výstup.
```

Hranice: renderer neví, co kreslí, karty nevědí, kam se to ukládá, úložiště
neví, jak obrázek vznikl. Feature je jediné místo, které zná WordPress.

Třídy se registrují v `Managers/FeaturesManager.php` stejně jako ostatní
features, kontejner PHP-DI si je sestaví sám.

### Co si beru z Turinky

`packages/core/src/Service/TuriOgImageService.php` ve vedlejším projektu dělá
totéž pro Symfony a je odladěný v provozu. Přebírám z něj tři poznatky, ne kód:

1. **Zalamování textu** přes `Imagick::queryFontMetrics()` na kandidátní
   řetězec — jediný spolehlivý způsob, jak zjistit šířku textu před vykreslením.
2. **Zaoblený obdélník se kreslí přímo** přes `ImagickDraw::roundRectangle()`,
   ne maskou. Maska dělá kolem hran tmavý lem.
3. **Kompozice výhradně přes `COMPOSITE_OVER`.** Turinka má u téhle poznámky
   varování, že `COMPOSITE_DSTIN` rozbíjí rastr na ImageMagicku 7.1.2 — to je
   ale její server, ne náš. Ověřeno:

   ```
   DDEV:     ImageMagick 6.9.11-60 Q16 aarch64
   produkce: ImageMagick 6.9.11-60 Q16 x86_64
   ```

   Obě prostředí tedy běží na stejné verzi 6.9 a kresba textu na obou funguje
   (ověřeno `annotateImage()` s diakritikou). `DSTIN` se přesto nepoužije —
   `COMPOSITE_OVER` stačí na všechno, co karta potřebuje, a nedává smysl
   riskovat kvůli ničemu.

## Úložiště, klíčování, úklid

Soubory leží v `wp-content/uploads/kct-og/`. Cesta se bere z
`wp_get_upload_dir()`, takže v multisite každý web dostane vlastní adresář pod
`uploads/sites/{N}/` bez další práce.

Název souboru:

```
post-{ID}-{hash}.png
akce-{ID}-{hash}.png
akce-db-{db_id}-{hash}.png
```

`hash` je prvních 12 znaků ze `sha1()` serializovaných vstupů karty — titulek,
datum, místo, pořadatel, URL fotky — a konstanty `OgImageRenderer::RENDER_VERSION`.

Z toho plynou dvě vlastnosti, kvůli kterým je klíčování takhle:

- **změna obsahu vyrobí jiný název**, takže se nikdy neservíruje zastaralý
  obrázek a Facebook si sáhne pro nový, protože se změní i URL v `og:image`,
- **bumpnutí `RENDER_VERSION` přegeneruje všechno**, takže změna designu
  nevyžaduje hromadný úklid.

Po zápisu nového souboru se smažou všechny starší soubory se stejnou předponou
(`post-123-`). Na disku tak leží vždy nejvýš jeden obrázek na objekt.

## Spouštěče

| Situace | Kdy se generuje |
|---|---|
| Příspěvek nebo akce se uloží | `save_post`, priorita 20, po uložení metadat. Přeskočí revize, autosave a stavy jiné než `publish`. |
| Virtuální `/akce-db/{id}` | Ve chvíli, kdy se skládá `og:image` a soubor chybí. Render trvá kolem 300 ms, proběhne jednou a stránka navíc sedí za stránkovou cache WP-Optimize. |
| Hromadně | WP-CLI `wp kct og_images`, ve výchozím stavu suchý běh, `--write` zapisuje, `--force` přegeneruje i existující. Projde všechny weby v síti. |

Virtuální akce nemají příspěvek, takže u nich žádný hák na uložení neexistuje —
proto to generování při prvním zobrazení detailu. Než se vypíše `og:image` tag,
soubor už na disku je.

## Napojení na výstup

Sdílecí obrázek se do stránky dostane na dvou místech, ne na čtyřech — kód už
má pro obojí jediný průchod:

**Akce — `Seo\EventSeoData::image()`.** Tahle jediná metoda obsluhuje
`RankMathOutput::image_url()`, `StandaloneOutput::head()` i `event_schema()`.
Stačí do ní na začátek přidat dotaz na `OgImageService` a zbytek (fotka
z importu, pak logo) zůstane jako záloha.

**To ale samo nestačí na akce, které mají vlastní CPT příspěvek** — a jsou to
zrovna ty, které někdo považoval za dost důležité, aby je převedl.
`/akce-db/{id}` u nich trvale přesměrovává na `/akce/{slug}/`, takže sdílí se
CPT stránka. A na ní:

- `RankMathOutput::render()` se vrací dřív, než se filtry obrázku zaregistrují
  (úmyslně — titulek, popisek a kanonickou adresu tam skládá Rank Math a
  redakce je může přepsat),
- `StandaloneOutput::render()` se vrací taky a nechává tagy na
  `Features\OpenGraph`, jenže ta o akcích nic neví.

Obě cesty proto potřebují doplnit:

- v `RankMathOutput` se registrace filtrů `image` a `image_array` přesouvá
  **nad** ten předčasný návrat, takže platí i pro CPT stránku. Titulek a
  popisek zůstávají editorovi, mění se jen obrázek.
- `Features\OgImages` dostává metodu `for_event_post()`, kterou volá
  `OpenGraph::image_url()` pro jiný typ než `post`.

Objevilo se to až při závěrečné revizi: `curl` na `/akce/{slug}/` vracel pořád
starou fotku z náhledu.

Vedlejší efekt, který stojí za zmínku: metoda dnes vrací u vlastní fotky akce
`width` a `height` rovné nule, protože je to vzdálená URL z importu a rozměry
nikdo nezná — a `StandaloneOutput` proto `og:image:width` vůbec nevypíše.
U vygenerovaného obrázku rozměry známe (1200×630), takže se tagy vypíšou a
Facebook vykreslí náhled hned při prvním sdílení, místo aby čekal, až si
obrázek sám stáhne.

**Příspěvky — dvě větve podle toho, jestli web má SEO plugin:**

- `Features/OpenGraph.php` — `image_url()` se nejdřív zeptá `OgImageService`
  a teprve pak spadne na náhled a výchozí obrázek. Týká se webů bez Rank Mathu.
- filtry `rank_math/opengraph/facebook/image` a `rank_math/opengraph/twitter/image`
  na webech s Rank Mathem, registrované jen když `is_singular( 'post' )`.

  **Ne `og_image`.** `RankMathOutput` má u sebe zjištění, které platí i tady:
  `og_image` filtruje až finální hodnotu tagu, a ten se vypíše jen tehdy, když
  Rank Math nějaký obrázek už našel — bez nalezeného obrázku se pozdní filtr
  nezavolá vůbec. `image` je dřívější filtr uvnitř `Image::add_image()` a
  proběhne vždycky. Je to tedy jediné spolehlivé místo.

  Ke srážce s registrací pro akce nedojde: `RankMathOutput` se registruje jen
  na stránce akce, přes `EventSeo::setup()` na háku `wp`.
- `Features/FacebookShare.php` — **nemění se**. Posílá `POST /{page_id}/feed`
  s `message` a `link` (viz `Facebook/GraphClient::publish()`), tedy sdílení
  odkazu. Facebook si obrázek načte z `og:image` sám. Protože se URL obrázku
  mění s hashem obsahu, přepsaný příspěvek dostane novou adresu a Facebook
  nepoužije svoji starou cache.

## Fonty

Imagick potřebuje soubor na disku. Téma bere Oswald a Plus Jakarta Sans
z Google Fonts CDN a v repozitáři žádný font není, takže se přibalí:

```
resources/fonts/Oswald-Medium.ttf
resources/fonts/Oswald-Bold.ttf
resources/fonts/PlusJakartaSans-Regular.ttf
resources/fonts/PlusJakartaSans-Medium.ttf
resources/fonts/PlusJakartaSans-SemiBold.ttf
resources/fonts/PlusJakartaSans-Bold.ttf
```

Obojí je pod SIL Open Font License 1.1, redistribuce v pluginu je v pořádku.
Licenční texty se přiloží jako `resources/fonts/OFL-Oswald.txt` a
`OFL-PlusJakartaSans.txt`. Dohromady 728 kB.

**Zdroj není repozitář Google Fonts.** Ten u obou rodin dnes vede jen variabilní
font (`Oswald[wght].ttf`, `PlusJakartaSans[wght].ttf`) a ImageMagick 6.9 neumí
nastavit osu váhy — vykreslil by výchozí instanci, což je u Plus Jakarta Sans
ExtraLight 200. Statické řezy se proto berou z původních repozitářů, na které
Google Fonts sám odkazuje:

```
https://raw.githubusercontent.com/googlefonts/OswaldFont/main/fonts/ttf/
    Oswald-Medium.ttf, Oswald-Bold.ttf
https://raw.githubusercontent.com/tokotype/PlusJakartaSans/master/fonts/ttf/
    PlusJakartaSans-{Regular,Medium,SemiBold,Bold}.ttf
```

Ověřeno stažením a vykreslením přes Imagick v DDEV: všech šest řezů kreslí
českou diakritiku správně (`Příkrý žluťoučký kůň — 38. ročník`) a naměřené
šířky se mezi řezy liší, takže se opravdu načítají různé váhy, ne jedna.

Fonty se do balíčku i na produkci dostanou samy — `.github/workflows/deploy.yml`
vylučuje `*.scss`, `node_modules`, `tests` a dokumentaci, `resources/` mezi
výjimkami není.

## Chybové stavy

Sdílecí obrázek je ozdoba, ne funkce. Nic v něm nesmí shodit stránku ani
rozbít sdílení:

| Stav | Chování |
|---|---|
| Rozšíření Imagick chybí | `OgImageService` vrátí `null` hned na začátku, výstup spadne na dnešní chování. |
| Fotka se nedá načíst nebo je poškozená | Karta pokračuje s grafickým pozadím. |
| Render vyhodí výjimku | Zaloguje se varování, vrátí se `null`, výstup spadne na dnešní chování. |
| Adresář nejde vytvořit nebo zapsat | Totéž — varování a `null`. |
| Font chybí na disku | Totéž. Kontroluje se jednou při prvním renderu. |

„Dnešní chování" znamená to, co dělá kód dnes: náhledový obrázek příspěvku,
u akce obrázek z importu, jinak výchozí obrázek z nastavení sdílení.

Žádná z těchto větví nesmí být fatal a žádná nesmí nechat na disku useknutý
soubor — zapisuje se do dočasného souboru a přejmenovává se až po úspěšném
zápisu.

## Ověření

V projektu není PHPUnit ani jiná testovací infrastruktura a nezavádí se kvůli
tomuhle — to je samostatné rozhodnutí. Ověřování stojí na:

- `php -l` nad každým novým souborem,
- `ddev wp kct og_images --write` nad lokální kopií produkce; vzorky se
  vyrenderují ze skutečných příspěvků a akcí, včetně okrajových případů:
  akce bez fotky, akce bez cíle, akce bez času startu, vícedenní akce,
  příspěvek bez kategorie, příspěvek bez náhledu, velmi dlouhý titulek,
- vizuální kontrola Martinem — vyrenderované PNG se zmenší na 500 px, tedy na
  šířku, v jaké je Facebook ukazuje ve feedu, a posoudí se čitelnost datového
  pásu; teprve podle toho se doladí velikosti písma,
- `curl` nad detailem akce a příspěvku, jestli `og:image` míří na vygenerovaný
  soubor a jestli soubor vrací HTTP 200,
- Facebook Sharing Debugger nad nasazeným odkazem.

Ověřování probíhá výhradně čtením a renderem do souborů. Do databáze se kvůli
ověření nezapisuje.

## Otevřené body k doladění při implementaci

Velikosti písma v datovém pásu jsou první odhad. Facebook zobrazuje náhled ve
feedu kolem 500 px širokého, takže se obrázek zmenšuje 2,4× a text pod zhruba
28 px ve zdroji je na hraně čitelnosti. Konkrétní čísla se nastaví podle
vizuální kontroly zmenšených vzorků, ne od stolu.
