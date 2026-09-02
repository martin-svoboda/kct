# Uživatelská dokumentace šablony — návrh

**Datum:** 2026-09-01

## Problém

Šablona KČT je dnes nezdokumentovaná pro toho, kdo ji používá. Správce odboru
nebo oblasti dostane hotový web a nikde se nedozví, co která volba dělá, kam
se zadává kód odboru, proč se akce načítají samy, ani co po něm šablona
nechce — typicky instaluje pluginy na věci, které už plugin `kct` řeší sám
(lightbox, komprese obrázků, sdílení na Facebook, kalendář akcí). Na tomhle
webu jsou takové pluginy aktivní i dnes.

Zároveň mají uživatelé různou úroveň: část z nich potřebuje i základy práce s
WordPressem, ne jen popis specifik šablony.

Materiál pro velkou část textů přitom existuje — třídy v `src/Features/` mají
obsáhlé české docblocky s odůvodněním rozhodnutí. Chybí jen forma, ve které
se to dostane k uživateli.

## Univerzálnost textů

Příručka platí pro **jakékoli nasazení šablony**, ne jen pro síť sokct.cz.
Šablona se distribuuje jako instalovatelný ZIP (příloha vydání na GitHubu) a
běží stejně tak na běžném samostatném WordPressu jednoho odboru. Multisite je
jedno z nasazení, ne definice.

Není to jen otázka formulací. `is_main_site()` vrací na jednowebové instalaci
`true`, takže funkce v kódu podmíněné hlavním webem — **Odbory a import akcí z
centrální databáze KČT** — jsou na samostatném webu dostupné. Text, který
tvrdí, že „Odbory se na webu odboru neobjeví", platí jen pro podřízený web v
síti.

Kde se chování liší, uvádí se obě varianty jako podmínka a ani jedna se
nepředpokládá jako výchozí. Týká se to hlavně dostupnosti Odborů a importu,
aktualizací, záloh a role správce sítě, která mimo multisite neexistuje.

## Rozsah

**Je v rozsahu:** statická dokumentace postavená na Astro Starlight, uložená v
repozitáři pluginu, sestavovaná a nasazovaná přes GitHub Actions; obsahová
struktura a soupis stránek; pravidlo pro udržování dokumentace v souběhu s
kódem.

**Není v rozsahu:** demo web s ukázkovým obsahem; automatické generování
referenční části z kódu; vícejazyčnost; verzování dokumentace podle verzí
pluginu. Dokumentace popisuje aktuální stav šablony.

Cílová doména je `napoveda.sokct.cz`. Konkrétní způsob nasazení zůstává
otevřený (viz *Otevřené body*) — návrh je postavený tak, aby volba hostingu
byla poslední krok a nic dalšího na ní neviselo.

**Změna oproti prvnímu zadání (2026-09-02):** původně se počítalo se
`sablona.sokct.cz`. Ta doména zůstane prezentačnímu miniwebu šablony —
landing page s odkazy na tuhle nápovědu, na stažení pluginu a podobně.
Prezentační web je samostatný úkol a tenhle spec ho neřeší. Odpadá tím
zároveň otevřený bod, co se stávající subsite: nikam se nestěhuje.

## Umístění v repozitáři

Zdroj bude v `docs/user/` v repozitáři pluginu, tedy pod existující složkou
`docs/`, ne jako nová složka vedle ní.

Důvod je praktický: `deploy.yml` už `docs` vylučuje z instalačního ZIPu, takže
se dokumentace nedostane do balíčku pluginu. Složka `docs/` tím dostává jeden
jasný význam: všechno, co se nenasazuje. `docs/superpowers/` jsou interní
specifikace a plány, `docs/user/` je veřejná příručka.

**Jedna změna v nasazení ale nutná je.** Rsync na produkci (krok `Deploy` v
`deploy.yml`) `docs` nevylučuje — vylučuje ho jen krok `Build plugin ZIP`.
Dnes se tak na produkční weby kopíruje i `docs/superpowers/`, což je pár
kilobajtů Markdownu a nikomu to nevadilo. U `docs/user/` by to ale znamenalo
nasazovat zdroje i sestavený web: výjimka `--exclude="/dist"` je kotvená na
kořen a na `docs/user/dist/` nesedne. Do rsyncu na produkci se proto doplní
`--exclude="docs"`, čímž se obě větve nasazení srovnají.

Uvnitř je samostatný projekt Astro + Starlight s vlastním `package.json`.
Nemíchá se se stávajícím `package.json` pluginu, který patří `wp-scripts`
buildu bloků — jiné závislosti, jiný životní cyklus, jiná četnost změn.

Do `.gitignore` pluginu přibude `/docs/user/node_modules/` a
`/docs/user/dist/`. Kořenový `/node_modules/` a `/dist/` tam sice už jsou, ale
ty vzory jsou kotvené na kořen a na podsložku nesednou.

Konfigurace Starlightu: čeština jako výchozí jazyk (`defaultLocale: 'root'` s
`lang: 'cs'`), bez i18n vrstvy, hledání přes vestavěný Pagefind, tmavý režim
ponechaný ve výchozím chování.

## Sestavení a nasazení

Nový workflow `.github/workflows/docs.yml`, oddělený od `deploy.yml`.

**Spouštění:** `push` na `main` s filtrem `paths: docs/user/**`, plus
`workflow_dispatch` pro ruční spuštění. Záměrně ne na tag: `deploy.yml` běží
na tagu, protože nasazení pluginu je vydání verze. Dokumentace se ale opravuje
i mezi vydáními — překlep nemá čekat na release.

**Kroky:** checkout, Node 22, `npm ci` a `npm run build` v `docs/user`,
nasazení výstupu z `docs/user/dist`.

Node 22 záměrně, i když `deploy.yml` používá 18: Astro 7 má v `engines`
`node >=22.12.0` a na osmnáctce se nesestaví. Verze se tím rozejdou, ale jsou
to dva nezávislé buildy — `deploy.yml` staví bloky přes `wp-scripts` a o
dokumentaci neví.

Dokud není rozhodnutý hosting, poslední krok jen nahraje výsledek jako
artifact. Pipeline je tím funkční a otestovatelná od začátku a doplnění
nasazení je pak změna jednoho kroku.

## Struktura obsahu

Čtyři sekce, přibližně třicet stránek.

### Začínáme

Uvítací část pro člověka, který dostal hotový web a poprvé se do něj přihlásil.

- Co je šablona KČT — plugin `kct` a šablona `kct`, co obstarává která část
- Přihlášení a orientace v administraci
- Role uživatelů a co která smí
- První nastavení webu — kód odboru nebo oblasti, logo, barva, menu

### Základy WordPressu

Rozsah je vymezený tím, co uživatel KČT skutečně dělá. Ne úplná příručka
WordPressu — témata jako revize, import/export nebo správa uživatelů do ní
nepatří, protože se jich běžný redaktor odboru nedotkne.

- Editor bloků — jak vkládat, přesouvat a mazat bloky
- Stránky vs. aktuality — kdy použít co
- Média — nahrávání, rozměry fotek, popisky, alternativní text
- Menu a navigace
- Postranní panel
- Odkazy — interní, externí, na soubory
- Publikování — koncepty, náhled, plánování

### Funkce šablony

Jádro dokumentace. Popis toho, co umí plugin a šablona nad rámec WordPressu.

- Akce — ruční zakládání i automatický import z centrální databáze akcí KČT
- Odbory

Typy obsahu **Trasy a Místa se nedokumentují vůbec.** `PostTypesManager` je
neinstancuje, jsou tedy trvale vypnuté ve všech nasazeních a počítá se s tím,
že je nahradí napojení na aplikaci Turinka. Popisovat vypnutou funkci mate.
Jediné, co z té oblasti zůstává v provozu, je povolení nahrávat soubory `.gpx`
do knihovny médií — to obstarává frontend nezávisle na vypnutém typu obsahu a
je popsané u médií.
- Bloky — samostatná stránka na každý ze sedmi bloků, které jde v editoru
  vložit: Úvodní obrázek (`cover`), CTA blok (`action`), Kalendář akcí
  (`events`), Aktuality (`news`), Předtitulek (`eyebrow`), Obrázek s obsahem
  vedle (`image-content`), Info karta (`infobox-item`). Osmý blok Info boxy
  (`infobox`) má v `block.json` `"inserter": false`, takže ho redaktor vložit
  nemůže — existuje jen kvůli staršímu obsahu. Vlastní stránku nedostane,
  zmíní se na stránce Info karty, aby správce věděl, co to je, když na něj ve
  starší stránce narazí.
- Vzhled — tři skiny, primární barva, průhledná hlavička, mapa akcí,
  vyhledávání v hlavičce, sekundární logo, názvy archivů, odkaz na členství
- Sdílení na Facebook — nastavení ID stránky a tokenu, automatické sdílení
  aktualit a akcí zvlášť, časování pozvánek, ověření připojení
- Sdílecí obrázky — jak vznikají a co je ovlivňuje
- SEO — chování s Rank Math i bez něj

### Pro správce

- Doporučené pluginy
- **Pluginy, které nepotřebujete a proč** — lightbox (řeší `Features\Lightbox`
  přes vestavěný lightbox WordPressu), komprese a zmenšování obrázků (řeší
  `Features\ImageUploads`: strop 2048 px a WebP), čištění EXIF (řeší
  `Features\ImageMetadata`), generování náhledů pro sociální sítě (řeší
  `Features\OgImages`), publikace na Facebook (řeší `Features\FacebookShare` a
  `Features\DbEventShare`), kalendář akcí (řeší vlastní typ obsahu Akce),
  mapy (vestavěný Leaflet a Mapy.cz)
- Aktualizace šablony
- Zálohy
- Bezpečnost
- Řešení problémů
- Změny v šabloně — uživatelský výtah z vydaných verzí

## Zdroje textu

Většina obsahu se nepíše od nuly. Odůvodnění chování už v kódu česky je:

| Zdroj | Co z něj vzniká |
|---|---|
| docblocky v `src/Features/` | sekce „proč to tak je" — strop 2048 px, WebP, čištění EXIF, dvanáctidenní předstih pozvánek |
| popisky polí v `src/Settings.php` | popis stránky Nastavení → KČT |
| labely v `themes/kct/inc/customizer.php` | popis voleb vzhledu |
| `blocks/*/block.json` | popis jednotlivých bloků |

Texty se přebírají významem, ne doslova: docblock mluví k vývojáři, stránka
dokumentace k redaktorovi odboru.

## Screenshoty

Pořizované ručně z lokální instance ddev (hostname `sablona.sokct`), aby se
nedostal na veřejné obrázky reálný obsah a osobní údaje z produkce.

Sjednocené parametry: šířka 1440 px, světlý režim, PNG. Uložené v
`docs/user/src/assets/`, pojmenované podle obrazovky (`admin-nastaveni-kct.png`).

Automatizované pořizování screenshotů se teď nedělá. Přidá se, až se ukáže, že
obrázky zaostávají za administrací — dřív je to nástroj bez doloženého
problému.

## Údržba

V repozitáři pluginu vznikne `CLAUDE.md` (zatím tam žádný není) s pravidlem:
mění-li se uživatelsky viditelné chování, mění se ve stejném PR i
`docs/user/`.

To je celý mechanismus. Vědomě žádná kontrola v CI — nejde strojově poznat,
která změna kódu je uživatelsky viditelná, a kontrola, která se dá obejít
prázdnou úpravou, jen přidá tření bez záruky.

## Otevřené body

**Hosting.** Tři varianty, rozhodne se před doplněním posledního kroku
workflow:

1. **GitHub Pages + CNAME `napoveda.sokct.cz`** — nulová práce na serveru,
   HTTPS zdarma. Podmínka: repozitář `martin-svoboda/kct` musí být veřejný, u
   privátního jsou Pages součástí placeného plánu.
2. **rsync do vlastního docrootu subdomény** — konzistentní se stávajícím
   `deploy.yml`, SSH klíč už v secrets je. Vyžaduje na serveru vhost pro
   `napoveda.sokct.cz` mimo docroot WordPressu. Pozor na multisite:
   `SUBDOMAIN_INSTALL` je zapnuté a `DOMAIN_CURRENT_SITE` se bere z
   `HTTP_HOST`, takže pokud subdomény padají do stejného vhostu wildcardem,
   WordPress si subdoménu vezme pro sebe — u nové subdomény to platí stejně
   jako u té původní.
3. **Cloudflare Pages** — zdarma i pro privátní repozitář, vlastní doména bez
   zásahu do serveru. Za cenu další služby v řetězci.

**Vyřešeno 2026-09-02:** dokumentace jde na `napoveda.sokct.cz`, takže se
stávající subsite `sablona.sokct.cz` nemusí nikam stěhovat — zůstane jí a
promění se v prezentační miniweb šablony. Ten je samostatný úkol.
