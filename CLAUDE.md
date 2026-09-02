# Plugin KČT

Plugin se šablonou pro odbory a oblasti KČT. Nasazuje se tagem přes
`.github/workflows/deploy.yml` na sokct.cz i posazavskastezka.cz a zároveň
vzniká instalační ZIP jako příloha vydání.

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

## Dokumentace vývoje

`docs/superpowers/specs/` jsou návrhy, `docs/superpowers/plans/` implementační
plány. Obojí je interní a nikam se nenasazuje.
