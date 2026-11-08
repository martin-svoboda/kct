# Šablona KČT

Web na klíč pro odbory a oblasti Klubu českých turistů. Repozitář obsahuje
plugin i vzhled: plugin dodává funkce, šablona v `themes/kct` jejich podobu.
Jedno bez druhého nedává smysl, proto se vydávají spolu.

- **Příručka pro správce webu:** <https://napoveda.sokct.cz>
- **Web šablony:** <https://sablona.sokct.cz>

## Co plugin umí

- **Kalendář akcí z centrální databáze KČT.** Akce se importují z exportu
  ústředí, dají se doplnit vlastním příspěvkem a výpisy se filtrují podle
  druhu turistiky, termínu a pořadatele.
- **Evidence odborů** s kontakty, polohou a odkazem na vlastní web.
- **Sdílení na Facebook** — ruční u příspěvku i denní úloha pro akce, které
  vlastní příspěvek nemají, včetně generovaných sdílecích obrázků.
- **SEO pro akce a odbory**, včetně stránek, které vznikají z dat a žádný
  SEO plugin o nich neví.
- **Zpracování obrázků při nahrání** — strop rozměrů, převod do WebP a úklid
  metadat; a příkaz, který podle stejných pravidel srovná i starší knihovnu.

## Požadavky

- WordPress 6.5 a novější
- PHP 8.0 a novější
- Rank Math je volitelný; bez něj si šablona vypíše vlastní SEO výstup sama

## Instalace

Balíček `kct-<verze>.zip` je přílohou každého
[vydání na GitHubu](https://github.com/martin-svoboda/kct/releases) a instaluje
se v administraci přes **Pluginy → Přidat plugin → Nahrát plugin**. Šablona se
nainstaluje spolu s ním, aktivuje se v **Vzhled → Šablony**.

Další verze se pak nabízejí samy v přehledu pluginů, stejně jako u kteréhokoli
jiného pluginu.

## Vývoj

```bash
composer install     # závislosti PHP (scoper je součástí)
npm ci               # závislosti buildu
npm run build        # bloky a styly
npm run docs         # příručka na http://localhost:4321
```

Podrobnosti o nasazení, dokumentaci a pastích, na které se v tomhle projektu
dá narazit, jsou v [CLAUDE.md](CLAUDE.md).

## Licence

GPL v2 nebo novější, viz [LICENSE](LICENSE).
