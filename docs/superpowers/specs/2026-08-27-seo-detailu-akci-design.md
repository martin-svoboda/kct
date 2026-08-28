# SEO detailů akcí

**Datum:** 2026-08-27
**Rozsah:** virtuální stránky `/akce-db/{id}` na všech webech v síti + single CPT `akce`
**Priorita:** dostat detaily akcí do indexu vyhledávačů; dnes jsou z něj vyřazené

## 1. Cíl

Detaily akcí jsou nejcennější obsah webu — 318 záznamů s termínem, místem, GPS,
pořadatelem a propozicemi — a vyhledávače je ignorují. Stránka `/akce-db/24056`
dostává titulek „Aktuality a zprávy z oblasti“, prázdný popisek a kanonickou adresu
`/aktuality-a-zpravy/`, takže ji Google vyhodnotí jako duplicitu výpisu blogu
a z indexu vyřadí.

Po implementaci má každý detail akce vlastní titulek, popisek, kanonickou adresu,
Open Graph tagy, `schema.org/Event` a být v XML sitemapě.

### Co je mimo rozsah

Zakládání příspěvků z importu — **DB akce zůstávají jen v tabulce `wp_db_events`**
a do administrace nepřibude nic. Převod akce na CPT `akce` zůstává ruční přes
stávající tlačítko (`Events::convert_event()`).

Dál mimo rozsah: SEO odborů (samostatný nález, stejný problém s chybějícím JSON-LD),
drobečková navigace, obsahové stránky tras a míst, přepis výpisu `/akce/`.

## 2. Předpoklady a omezení

**Virtuální stránka není singulární.** Rewrite pravidlo v `Events::add_rewrite_rules()`
mapuje `akce-db/{id}` na `index.php?db_id={id}`. WordPress dotaz vyhodnotí jako výpis
blogu (`is_home()`), šablona `index.php` jen podle `get_query_var('db_id')` vykreslí
`template-parts/content-akce.php`. Rank Math proto skládá hlavičku pro stránku
nastavenou jako `page_for_posts`. Singulární větev Rank Mathu (per-post meta, schema
z editoru) se na těchto stránkách vůbec nespustí — všechno musí přijít z filtrů.

**Rank Math sám `Event` negeneruje.** Nastavení `pt_akce_default_rich_snippet = event`
v Titles & Meta pouze předvybere typ v editoru. Vlastní JSON-LD vzniká jen z per-post
meta `rank_math_schema_*`, kterých je v databázi nula. V kódu je whitelist natvrdo:

```php
// includes/helpers/class-schema.php:69
if ( $return_valid && ! in_array( $schema, [ 'Article', 'NewsArticle', 'BlogPosting',
     'WooCommerceProduct', 'EDDProduct' ], true ) ) {
    return false;
}
```

`Singular::get_default_schema()` mapuje rovněž jen `article` a `product`.

**Důsledek pro single CPT `akce`:** protože `get_default_schema_type()` vrátí `false`,
spadne i `JsonLD::can_add_global_entities()` a na stránkách akcí nevzniká *žádné*
JSON-LD — ani `WebSite`, ani `WebPage`, ani `BreadcrumbList`. Ověřeno: single akce
má 0 bloků `application/ld+json`, běžný příspěvek 1. Nastavení „Event“ je tedy dnes
horší než „Article“.

**Ne každý web v síti má Rank Math.** `kctpodebrady` nemá aktivní žádný plugin kromě
síťového `kct`. Proto existuje feature `OpenGraph`, která se sama vypne, když najde
`RankMath` nebo `WPSEO_Options`. Nový modul musí umět obojí: přes filtry Rank Mathu
tam, kde je, a vlastním výpisem do `wp_head` tam, kde není.

**Tytéž stránky existují na všech webech sítě.** `DbEventRepository` dělá
`switch_to_blog(1)` a čte sdílenou tabulku, takže `kctricany.cz/akce-db/23954/`
vrací 200 se stejným obsahem jako `sokct.cz/akce-db/23954/`. Dnes to nevadí,
protože to nikde není indexovatelné; po této změně by vznikla duplicita na pěti
doménách.

**Kolize slugů v datech.** „Uzlařská regata, 30. ročník“ je v tabulce dvakrát
(`db_id` 23990 na 8. 2. 2026 a 23306 na 15. 2. 2026, stejný odbor). Pro virtuální
stránky to nevadí — adresu tvoří `db_id`, ne slug — ale stojí za nahlášení do
centrální databáze KČT.

## 3. Architektura

Jeden nový modul `src/Features/EventSeo.php`, registrovaný v `Managers/FeaturesManager.php`
vedle stávajících features. Nesahá na import, na `wp_db_events`, ani na administraci.

Modul má tři odpovědnosti a nic víc:

**`EventSeo`** — rozpozná kontext (virtuální stránka akce / single CPT `akce`),
načte data přes stávající `Events::get_event()` a rozhodne, co se má vypsat.
Sám nic neformátuje.

**`EventSeoData`** — z pole akce poskládá hodnoty: titulek, popisek, kanonickou
adresu, URL obrázku, `Event` graf. Čistá transformace bez WordPressu, dá se testovat
samostatně.

**`EventSeoOutput`** — vypíše hodnoty do stránky. Dvě implementace za jedním rozhraním:
`RankMathOutput` (registruje filtry) a `StandaloneOutput` (vypisuje do `wp_head`,
používá se na webech bez SEO pluginu). Volba podle `class_exists( 'RankMath' )`,
stejná podmínka jakou používá `OpenGraph::has_seo_plugin()`.

Rozdělení je záměrné: skládání hodnot je to, co se bude ladit nejčastěji, a nemá
smysl kvůli změně formulace titulku procházet registraci filtrů.

### Napojení na Rank Math

| Háček | Co se přepíše |
|---|---|
| `rank_math/frontend/title` | titulek akce |
| `rank_math/frontend/description` | popisek akce |
| `rank_math/frontend/canonical` | kanonická adresa akce |
| `rank_math/opengraph/facebook/og_title` | totéž co titulek |
| `rank_math/opengraph/facebook/og_description` | totéž co popisek |
| `rank_math/opengraph/facebook/og_url` | kanonická adresa |
| `rank_math/opengraph/facebook/og_type` | `article` |
| `rank_math/opengraph/facebook/image` | obrázek akce, jinak logo oblasti |
| `rank_math/opengraph/twitter/twitter_*` | totéž (Rank Math sám nedědí) |
| `rank_math/json_ld` | odebrat `CollectionPage`, přidat `Event` |
| `rank_math/schema/add_global_entities` | vrátit `true` pro CPT `akce` |
| `rank_math/sitemap/providers` | vlastní provider `akce-db` |

`og:description` se nebere z `frontend/description` — `OpenGraph::get_description()`
si pro kontext výpisu blogu sahá pro `rank_math_facebook_description` stránky
`page_for_posts`. Proto se filtruje zvlášť.

## 4. Skládání hodnot

**Titulek.** `{ročník}. {název} — {místo konání}, {datum}`, u akce bez ročníku
(`year = 0`) se číslo vynechá. Příklad: `46. Krajem nezbedného bakaláře — Rakovník,
5. 9. 2026`. Sufix s názvem webu doplní Rank Math sám ze šablony; ve `StandaloneOutput`
se připojí ručně.

**Popisek.** Volný text u většiny akcí není, takže popisek se **skládá z dat** a text
je jen doplněk. Naměřeno na 318 řádcích:

| Zdroj | Vyplněno | Poznámka |
|---|---|---|
| `details[].name` | 318 | typ akce, vždy k dispozici |
| `details[].km` | 277 | délky tras |
| `start.date`, `start.place` | 313 | |
| `organiser.name` | 318 | |
| `content` | 97 | delší než 80 znaků jen u 44 akcí |
| `proposal` | 92 | **není text** — `{proposalid, url, name}`, odkaz na PDF |

`proposal` se tedy pro popisek nepoužívá vůbec.

Popisek vzniká skládáním vět v tomto pořadí, dokud se nenaplní ~155 znaků:

1. **Disciplína a délky** — `Pěší turistika 10–40 km.`
2. **Termín a místo** — `5. 9. 2026, Rakovník, start 6:00–12:00.`
3. **Pořadatel** — `Pořádá KČT, odbor Rakovník.`
4. **Volný text** — začátek `content`, jen pokud po předchozích ještě zbývá místo.

Výsledek pro db_id 23954:

> Pěší turistika 10–40 km. 5. 9. 2026, Rakovník, start 6:00–12:00.
> Pořádá KČT, odbor Rakovník.

**Které pole místa.** Pro titulek i popisek se bere sloupec `place` — jsou to názvy
obcí, průměrně 12 znaků a nejdelší 43. `start.place` je podrobný popis místa srazu
(„Rakovník – Sokolovna, trasa 10 km individuálně na ŽST Lašovice“) a v popisku by
sám spotřeboval celý limit; patří na stránku a do `Event.location.name`, kde na délce
nezáleží a podrobnost pomáhá.

Volný text tedy strojovou část **nenahrazuje, jen doplňuje**. Datum, místo, délka
a pořadatel jsou přesně to, podle čeho se člověk ve výsledcích rozhoduje, a `content`
je často jen jednořádková poznámka („Duatlon : běh (375 m) – kolo (1500 m) – běh (375 m)“),
která sama o sobě neřekne ani kde, ani kdy.

**Čtení `details`.** Pole je JSON se záznamy `{detailid, weight, name, icon, km}`.
`weight` řadí od disciplín k bonusům — 30 Pěší turistika, 50 Cykloturistika,
112 Kočárková trasa, 136 Akce IVV, 180 Sleva KČT. Disciplína je proto **položka
s nejnižší `weight`**; délky se berou ze všech položek s neprázdným `km`. Bonusové
položky (sleva, známky) do popisku nepatří.

Když chybí i `place` (5 akcí z 318), věta o startu se vynechá a popisek zůstane
kratší — prázdné „Start 5. 9. 2026, .“ je horší než nic.

**Kanonická adresa.** Viz kapitola 5.

**Obrázek.** `image` z akce, jinak logo oblasti jako záložní. Bez záložního obrázku
vypadá sdílený odkaz na Facebooku jako holý text.

### `Event` graf

```
@type          Event
name           název akce (bez ročníku — ten patří do titulku, ne do názvu entity)
startDate      start.date + první čas z start.time, jinak jen datum
endDate        finish.date + čas, jen když se liší od startu
eventStatus    EventScheduled — jen u budoucích akcí (viz níže)
location       Place { name: start.place, geo: GeoCoordinates z start.gps_n/gps_e }
organizer      Organization { name: organiser.name }
url            kanonická adresa
image          obrázek akce, jinak logo
description    stejný text jako meta popisek
```

`start.time` je lidský rozsah („6:00–12:00“, „do 18:00“), ne strojový čas. Parsuje se
vedoucí `H:MM`; když se nepodaří, zůstane jen datum — schema.org to připouští a je to
poctivější než hádat.

`eventStatus` se vypisuje **vždy, i u proběhlých akcí**. Původní návrh ho u nich vynechával
s tím, že „EventScheduled o akci, která byla loni, není pravda“ — to je věcně chybné.
Schema.org definuje `EventScheduled` jako „The event is taking place **or has taken place**
on the startDate as scheduled“ a `EventStatusType` žádnou hodnotu pro „proběhlo“ nemá;
alternativy jsou jen Cancelled, Postponed, Rescheduled a MovedOnline. U akce, která se
konala podle plánu, je `EventScheduled` tedy jediná správná hodnota.

Proběhlá akce zůstává indexovatelná (lidé hledají i loňské ročníky) a nad obsahem se
zobrazí poznámka „Tato akce už proběhla {datum}.“

`endDate` se vypíše, jen když je **prokazatelně pozdější** než `startDate`. U 17 akcí je
`finish.date` vyplněné, ale `finish.time` prázdné, takže z něj vyjde holé datum, které se
čte jako půlnoc — tedy konec před startem. Jedna akce (`db_id` 24947) má dokonce cíl o šest
dní dřív než start. Porovnávají se proto okamžiky, ne řetězce.

`finish.time` je navíc u 133 z 318 akcí **rozsah** („13:00–16:00“ = kdy je cíl otevřený).
Pro `endDate` se z něj bere **poslední** čas, ne první — jinak by akce „končila“ ve chvíli,
kdy se cíl teprve otevírá.

## 5. Kanonizace napříč sítí

Kanonický je web **pořádajícího odboru, pokud nějaký v síti existuje**; jinak oblastní
web. Odborový web tak sbírá návštěvnost svých vlastních akcí, oblastní web všechno ostatní.

**Nadřazené pravidlo: existující příspěvek přebíjí virtuální stránku.** Má-li akce
kdekoli v síti příspěvek typu `akce` spárovaný přes `db_id`, je kanonickou adresou
permalink toho příspěvku. Teprve když žádný nemá, platí pravidlo podle odboru.

Bez toho by vznikla duplicita, na kterou původní návrh nemyslel. Ukázkový případ je
`db_id 24074` („Tour de Poděbrady“): patří odboru 102093, jehož kanonický web je
`kctpodebrady`, ale její příspěvek (ID 2088) leží na oblastním webu. Vznikly by tak
dvě indexovatelné stránky téže akce, každá sama sobě kanonická, a ani jedna by
neukazovala na druhou. Příspěvek vyhrává proto, že je editorsky obohacený, má vlastní
slug a už je v `akce-sitemap.xml`.

Mapování vzniká z per-web nastavení `kct_options.id_code`:

```
sokct          102       3 číslice → region  → oblastní web, záložní kanonický cíl
kctricany      102100    6 číslic  → odbor 102100
kctpodebrady   102093    6 číslic  → odbor 102093
kctrakovnik    102131    6 číslic  → odbor 102131
```

`SettingsRepository::code_type()` už rozlišení podle délky umí. Modul projde
`get_sites()`, přečte `id_code` každého webu a sestaví mapu `department → home_url`.
Mapa se ukládá do síťového transientu na 12 hodin — `get_sites()` s přepínáním
blogů je pro každý požadavek zbytečně drahé. Transient se zahazuje při uložení
nastavení pluginu a při přidání nebo smazání webu v síti.

Pro akci s `department = 102100` je pak kanonická adresa
`https://kctricany.cz/akce-db/23954/` bez ohledu na to, na kterém webu se stránka
zobrazuje. Cíl vždy existuje, protože detail akce renderují všechny weby.

Stejné pravidlo platí pro sitemapu: **každý web vypisuje jen akce, pro které je
kanonický.** Při dnešním stavu dat to znamená 60 akcí na odborové weby (102131 má 28,
102033 šestnáct, 102093 a 102100 po osmi, 102126 zatím žádnou) a zbylých 258 na oblastní.

## 6. Sitemapa

Vlastní provider registrovaný přes `rank_math/sitemap/providers`, výsledkem je
`akce-db-sitemap.xml` v indexu. Obsahuje všechny akce, pro které je daný web
kanonický — budoucí i proběhlé.

**`lastmod` se nevypisuje vůbec.** Původní návrh počítal se sloupcem `changed`,
ten ale v tabulce **neexistuje** — `wp_db_events` má jen `date` (datum konání).
Použít datum konání jako `lastmod` nejde: u 129 z 249 akcí by ležel v budoucnosti
(až 2027-12-31), což je pro Google signál, že se `lastmod` na tomhle webu nedá věřit,
a může ho začít ignorovat plošně. `lastmod` je v protokolu nepovinný a žádný je lepší
než nepravdivý. Skutečný `lastmod` by vyžadoval nový sloupec plněný importem — to je
samostatná změna.

**Sitemapa se nestránkuje.** Rewrite pravidlo Rank Mathu (`([^/]+?)-sitemap([0-9]+)?\.xml$`)
pustí libovolné číslo, takže provider musí při `$current_page > 1` vrátit prázdno —
jinak `akce-db-sitemap2.xml` i `akce-db-sitemap99.xml` vrátí celý seznam a Rank Math
každou takovou adresu natrvalo zacachuje do souboru bez expirace.

**Cache po importu.** Rank Math zneplatňuje cache jen při uložení příspěvku a typ
`akce-db` mezi ně nepatří. Denní import proto musí zavolat
`Cache::invalidate_storage( EventSitemapProvider::TYPE )`, jinak se nové akce
do sitemapy nedostanou.

Na webech bez Rank Mathu se sitemapa negeneruje. Ty weby dnes žádnou nemají a řešit
to je samostatná věc.

## 7. Ověření funkčnosti

Po nasazení se ověří na lokální kopii:

1. `/akce-db/23981/` (Kralupské kolo, odbor 102073 — vlastní web nemá) má na
   `sokct.test` vlastní titulek, popisek, kanonickou adresu na sebe a jeden blok
   JSON-LD s `Event`.
2. Tentýž detail na `kctricany.test` má kanonickou adresu na `sokct.test`.
3. `/akce-db/23954/` (Krajem nezbedného bakaláře, odbor 102131) má na `sokct.test`
   i na `kctricany.test` kanonickou adresu na `kctrakovnik.sokct.test`.
4. Na `kctpodebrady` (bez Rank Mathu) se vypíšou OG tagy z `StandaloneOutput`
   a nejsou v HTML dvakrát.
5. Single CPT `/akce/51-tour-de-podebrady…/` má `Event` a zároveň se vrátily
   `WebSite`, `WebPage` a `BreadcrumbList`.
6. `akce-db-sitemap.xml` je v indexu a součet URL přes weby sítě odpovídá počtu
   řádků v `wp_db_events`.
7. Ostatní stránky webu se nezměnily — titulek, popisek ani JSON-LD na domovské,
   na příspěvku a na `/akce/` nesmí být jiné než před změnou.

Kontrola strukturovaných dat přes Google Rich Results Test proběhne až na produkci;
lokální doména není zvenčí dostupná.

## 8. Rizika

**Filtry Rank Mathu běží i tam, kde nemají.** `rank_math/frontend/title` se volá na
každé stránce. Když se špatně rozpozná kontext, přepíše se titulek celého výpisu
blogu. Proto se všechny filtry registrují až uvnitř kontextu akce a každý callback
ještě jednou ověří `get_query_var('db_id')` nebo typ příspěvku.

**Mapa webů v transientu může zastarat.** Když se přidá odborový web a transient
se nezahodí, akce se až 12 hodin kanonizují na oblastní web. Nezpůsobí to škodu,
jen zpoždění. Invalidace je proto navázaná na `wp_initialize_site`, `wp_delete_site`
a uložení nastavení.

**Proběhlé akce zvýší počet indexovaných stránek skokem.** Z jedné na ~318. To je
záměr, ale je dobré to čekat v Search Console a nelekat se výkyvu v „Discovered –
currently not indexed“, než Google všechno projde.
