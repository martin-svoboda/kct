# Plugin KČT

Plugin se šablonou pro odbory a oblasti KČT. Nasazuje se tagem přes
`.github/workflows/deploy.yml` na sokct.cz i posazavskastezka.cz a zároveň
vzniká instalační ZIP jako příloha vydání.

## Šablona mluví univerzálně

**V repozitáři se nemluví o konkrétní síti a neuvádějí se naměřená čísla.**
Šablona se instaluje na weby odborů, kterým je naše síť stejně cizí jako
jim navzájem — a to platí pro uživatelskou příručku i pro komentáře v kódu,
protože `src/` a `themes/` se vydávají v balíčku.

Pryč tedy patří jména konkrétních webů („na kctvltavin.cz“, „web bez Rank
Mathu (kctpodebrady)“), inventury naší knihovny a databáze („z 318 akcí má
vlastní příspěvek 12“, „84 MB metadat“) i výsledky jednotlivých měření
(„278 kB proti 174 kB“). Důvod rozhodnutí se zapisuje slovy, co z měření
plyne: „u malých náhledů zabírají metadata víc místa než samotný obrázek“.
Takový text navíc nezastará, kdežto číslo zastará hned.

Zůstávají dvě věci. **Čísla, která jsou vlastností šablony** — strop 2048 px,
rozměry sdílecích karet, kvalita WebP 82, povolené rozsahy v nastavení; to
není měření, to je chování. A **adresy vlastních služeb šablony** —
`Update URI` v `kct.php` a `UpdateChecker::ENDPOINT` (server aktualizací),
`DocsLinks::DEFAULT_URL` (příručka, přebitelné konstantou `KCT_DOCS_URL`
nebo filtrem `kct_docs_url`) a `Theme URI` ve `themes/kct/style.css`
(sablona.sokct.cz, web šablony). Na ty kód skutečně sahá, respektive jimi
šablona odkazuje sama na sebe.

Jako příklad obce i odboru se používá **Dolní Lhota**, aby se v textu
nepoznal žádný skutečný odbor.

## Uživatelská dokumentace

`docs/user/` je uživatelská příručka (Astro Starlight), sestavovaná samostatným
workflow `.github/workflows/docs.yml`. Do produkčního balíčku pluginu se
nedostane — `deploy.yml` vylučuje celou složku `docs` z obou větví nasazení,
z rsyncu na produkci i z instalačního ZIPu.

**Mění-li se uživatelsky viditelné chování, mění se ve stejném PR i
`docs/user/`.** Uživatelsky viditelné je všechno, co správce webu uvidí v
administraci nebo na webu: nové či přejmenované pole v nastavení, nová volba v
Přizpůsobení, nový nebo změněný blok, jiné výchozí chování u obrázků, akcí
nebo sdílení.

Vědomě k tomu není kontrola v CI: nejde strojově poznat, která změna kódu je
uživatelsky viditelná, a kontrola, která se dá obejít prázdnou úpravou, přidá
jen tření bez záruky.

Build dokumentace validuje interní odkazy (`starlight-links-validator`), takže
odkaz na neexistující stránku shodí workflow. Nové stránky se zakládají do
`docs/user/src/content/docs/` a v postranním panelu se řadí podle
`sidebar.order` ve frontmatteru.

### Odkazy z administrace do příručky

Administrace odkazuje na konkrétní stránky nápovědy dvěma způsoby — vestavěnou
záložkou „Nápověda“ a viditelnými odkazy u polí a panelů. Všechny adresy drží
`src/Help/DocsLinks.php`; jinde se URL nápovědy nepíší.

Základní adresa je přebitelná konstantou `KCT_DOCS_URL` ve `wp-config.php`
nebo filtrem `kct_docs_url`, aby web mimo síť KČT neodkazoval na cizí doménu.

**Tuhle hranici žádný validátor nehlídá.** Odkazy uvnitř příručky kontroluje
`starlight-links-validator` při jejím sestavení, ale o pluginu neví. Když se
stránka v příručce přejmenuje nebo zruší, odkaz z administrace tiše zamíří na
404. Po každé takové změně tedy:

```bash
npm run docs:build
grep -oE "'/[a-z0-9/-]+/'" src/Help/DocsLinks.php | tr -d "'" | sort -u | while read p; do
  [ -f "docs/user/dist${p}index.html" ] && echo "OK    $p" || echo "CHYBÍ $p"
done
```

### Náhled při vývoji

Dokumentace se **nespouští v ddev**. Node má nativní binárky vázané na
platformu (sharp, esbuild, rollup), takže jedny `node_modules` nemůžou
obsluhovat macOS host i linuxový kontejner zároveň — a CI ji stejně staví
na linuxu samostatně. Běží tedy na hostu:

```bash
npm run docs           # dev server na http://localhost:4321
npm run docs:build     # sestavení včetně validace odkazů
npm run docs:preview   # náhled sestaveného webu (funguje i hledání)
npm run docs:install   # npm ci v docs/user
```

Hledání (Pagefind) se sestavuje až v `build`, v `dev` je prázdné — ověřuje se
přes `docs:preview`.

Dokumentace běží na Node 22 — Astro 7 má v `engines` `node >=22.12.0`. To je
záměrný rozchod s `deploy.yml`, který staví bloky pluginu na Node 18; jsou to
dva nezávislé buildy.

## Aktualizace pluginu

Weby mimo nasazení z `deploy.yml` dostávají aktualizace přes
`plugin-update-checker` (`src/Updates/UpdateChecker.php`) a vlastní endpoint
na `https://updates.sokct.cz`. Kód endpointu je v repozitáři v
`server/updates/`, nasazuje ho `.github/workflows/updates-server.yml`.
`deploy.yml` celou složku `server` vylučuje z rsyncu i z instalačního ZIPu.

**Co se nenasazuje, drží jediný seznam: `.github/deploy-exclude.txt`.** Oba
rsyncy v `deploy.yml` ho načítají přes `--exclude-from`; vlastní `--exclude`
má každý jen na to, co platí opravdu jen pro něj (`dist`, hotový ZIP). Dva
oddělené seznamy tu kdysi byly, s komentářem, že se musí držet shodné — a
právě se rozešly: produkční rsync pak na obě produkce posílal `DOCKER_ENV`,
výpis prostředí runneru i s platným tokenem GitHubu, veřejně čitelný na
`/wp-content/plugins/kct/DOCKER_ENV`. Nový soubor do toho seznamu, ne vedle něj.

Druhá věc, která se tím neřeší: **vyloučená cesta je zároveň chráněná před
`--delete`.** Co už na produkci leží a pak se vyloučí, rsync sám neuklidí —
musí se smazat ručně.

Instalační ZIP se stahuje z přílohy vydání na GitHubu, ne z našeho serveru.
Výpadek endpointu tedy znamená jen to, že se nenabídne aktualizace, ne
přerušenou instalaci.

**`plugin-update-checker` je v `composer.json`, ne v `composer-deps.json`, a
nejde přes scoper. Není to opomenutí.** Knihovna si skládá jména tříd
z řetězců (verzované jmenné prostory `…\v5pX\…`) a php-scoper jméno slepené
za běhu přepsat neumí — scopovaná kopie by se rozbila až v runtime. Ten
verzovaný namespace je navíc přímo mechanismus, kterým knihovna řeší souběh
víc pluginů s různými verzemi; scopování by ho vyřadilo a nahradilo vlastním,
neověřeným.

Adresa endpointu je na dvou místech, která se musí shodovat: konstanta
`UpdateChecker::ENDPOINT` a hlavička `Update URI` v `kct.php`. Podle hlavičky
WordPress od 5.8 pozná, že se na aktualizace tohoto pluginu nemá ptát
WordPress.org — bez ní by stačilo, aby tam někdo vydal plugin se složkou
`kct`, a instalace by si stáhly cizí kód.

Konstanta `KCT_DISABLE_UPDATES` ve `wp-config.php` kontroly vypíná. Patří
**jen na vývojovou kopii**: tam má plugin v hlavičce nenahrazený zástupný
text `KCT_VERSION`, který `version_compare()` vyhodnotí jako nižší než
jakékoli vydání — aktualizace by se nabídla a její spuštění by složku pluginu
i s `.git` nahradilo obsahem ZIPu.

Na sokct.cz ani posazavskastezka.cz konstanta **vědomě není**. Nasazují se
rsyncem na verzi posledního tagu, takže se jim žádná aktualizace nenabídne,
a v přehledu instalací je chceme vidět — mimo jiné proto, že zaseknutá verze
tam prozradí nepovedené nasazení.

Změny v `server/updates/` ověřuje `php server/updates/public/test.php` —
testuje čistou logiku, bez sítě a databáze. Workflow ho spouští před
nasazením.

**Aktualizaci nikdy netestovat na vývojové kopii.** Plugin tam leží jako git
pracovní kopie a aktualizace WordPressu složku pluginu smaže a nahradí
obsahem ZIPu — i s `.git`. Na ověření patří samostatná WordPress instalace.

## Dokumentace vývoje

`docs/superpowers/specs/` jsou návrhy, `docs/superpowers/plans/` implementační
plány. Obojí je interní a nikam se nenasazuje — složka je navíc v `.gitignore`,
takže zůstává jen na disku a do veřejného repozitáře nejde.
