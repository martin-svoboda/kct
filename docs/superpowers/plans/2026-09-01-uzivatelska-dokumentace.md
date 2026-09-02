# Uživatelská dokumentace šablony — implementační plán

> **Pro agentní workery:** POVINNÝ SUB-SKILL: použij `superpowers:subagent-driven-development`
> (doporučeno) nebo `superpowers:executing-plans` a projdi plán úkol po úkolu.
> Kroky jsou zaškrtávací (`- [ ]`).

**Cíl:** Postavit uživatelskou příručku šablony KČT jako statický web (Astro
Starlight) uložený v repozitáři pluginu a sestavovaný v GitHub Actions.

**Architektura:** Samostatný Astro projekt v `docs/user/` uvnitř repozitáře
pluginu `kct`. Obsah jsou Markdown soubory ve čtyřech sekcích, navigace se
generuje z adresářové struktury. Sestavení hlídá validátor interních odkazů,
takže rozbitý odkaz shodí build. Nasazuje samostatný workflow spouštěný
změnou v `docs/user/**`.

**Tech stack:** Astro 7, `@astrojs/starlight` 0.41, `starlight-links-validator`,
Node 22, GitHub Actions.

**Vychází ze specifikace:** [`../specs/2026-09-01-uzivatelska-dokumentace-design.md`](../specs/2026-09-01-uzivatelska-dokumentace-design.md)

---

## Pravidla pro tenhle plán

**Žádné commity.** Verzování si dělá Martin sám — kroky plánu proto nikde
nekončí `git commit` ani `git push`. Každý úkol končí ověřením, že to funguje,
a změny zůstanou v pracovním stromu.

**Jazyk obsahu je čeština** a cílový čtenář je správce webu odboru nebo
oblasti KČT, ne vývojář. Pravidla stylu, závazná pro všechny obsahové úkoly
(6–11):

- Tykání ne, vykání ne — infinitivy a neosobní formulace („Kód odboru se
  zadává v…"). Stejný rejstřík má i administrace šablony.
- Žádné názvy tříd, hooků a souborů v textu pro redaktora. Do sekce „Pro
  správce" patří, do „Základů WordPressu" ne.
- Každá stránka začíná jednou větou, co čtenář po jejím přečtení bude umět.
- Kde kód nese odůvodnění (proč 2048 px, proč 12 dní), převyprávět ho — je to
  to nejcennější, co dokumentace může nabídnout, a jinde to nikde není.

**Kontext o šabloně** (worker ho nemá): „šablona KČT" jsou dvě věci nasazované
společně — plugin `wp-content/plugins/kct` (typy obsahu, bloky, importy,
sdílení) a šablona `wp-content/plugins/kct/themes/kct` (vzhled). Web běží jako
WordPress multisite, každý odbor nebo oblast je jedna subsite.

---

## Struktura souborů

Nově vzniká pouze uvnitř `docs/user/`, s výjimkou tří souborů v kořeni repa.

```
docs/user/
  package.json                 vlastní závislosti, nemíchá se s wp-scripts buildem pluginu
  astro.config.mjs             konfigurace Starlightu: čeština, sidebar, validátor odkazů
  tsconfig.json
  .nvmrc                       22
  src/
    content.config.ts          kolekce docs (loader + schema Starlightu)
    content/docs/
      index.mdx                rozcestník
      zaciname/                sekce 1 — 4 stránky
      zaklady-wordpressu/      sekce 2 — 7 stránek
      funkce/                  sekce 3 — 8 stránek + podsložka
      funkce/bloky/            sekce 3 — 7 stránek
      spravce/                 sekce 4 — 7 stránek
    assets/                    screenshoty administrace
  public/

.gitignore                     doplnit vzory pro docs/user
.github/workflows/docs.yml     nový workflow
CLAUDE.md                      nový, pravidlo o souběžné aktualizaci dokumentace
```

Rozdělení do souborů kopíruje navigaci: jedna stránka = jeden soubor = jedno
téma. Sekce 3 má vlastní podsložku pro bloky, protože sedm stránek o blocích
by jinak v postranním panelu utopilo zbytek sekce.

Pracovní adresář všech příkazů je `wp-content/plugins/kct`, není-li uvedeno
jinak.

---

## Task 1: Skeleton projektu Starlight

**Soubory:**
- Vytvořit: `docs/user/` (generuje `create-astro`)
- Upravit: `docs/user/astro.config.mjs`
- Smazat: ukázkový obsah z šablony

- [ ] **Krok 1: Ověřit verzi Node**

```bash
node -v
```

Očekávaný výstup: `v22.` nebo vyšší. Astro 7 má v `engines` `node >=22.12.0`;
na starší verzi se projekt nesestaví. Pokud je verze nižší, přepnout přes
`nvm use 22` a teprve pokračovat.

- [ ] **Krok 2: Vygenerovat projekt ze šablony Starlight**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
npm create astro@latest docs/user -- --template starlight --install --no-git --skip-houston --typescript strict --yes
```

Instalace závislostí trvá jednotky minut. `--no-git` je podstatné: repozitář
už existuje a `create-astro` by v podsložce založil druhý.

- [ ] **Krok 3: Ověřit, že skeleton vznikl a sestaví se**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
test -f astro.config.mjs && test -f package.json && test -d src/content/docs
npm run build
```

Očekávaný výstup: build skončí bez chyby a vypíše `Complete!`; vznikne
`docs/user/dist/index.html`.

- [ ] **Krok 4: Smazat ukázkový obsah šablony**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
rm -rf src/content/docs/guides src/content/docs/reference
rm -f src/assets/houston.webp src/assets/star*.webp
ls src/content/docs
```

Očekávaný výstup: zůstane pouze `index.mdx`.

- [ ] **Krok 5: Nastavit `.nvmrc`**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
echo "22" > .nvmrc
```

- [ ] **Krok 6: Přepsat `astro.config.mjs`**

Soubor `docs/user/astro.config.mjs`:

```js
// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
	// Doména se doplní, až se rozhodne o hostingu (viz Task 14).
	// Do té doby zůstává prázdná — Starlight z ní staví jen sitemap a
	// absolutní odkazy, na sestavení ani na náhled nemá vliv.
	integrations: [
		starlight({
			title: 'Nápověda k šabloně KČT',
			description:
				'Uživatelská příručka k webové šabloně pro odbory a oblasti Klubu českých turistů.',
			// Jediný jazyk webu je čeština. Locale `root` znamená, že stránky
			// leží přímo v src/content/docs a v adresách není jazyková předpona.
			locales: {
				root: { label: 'Čeština', lang: 'cs' },
			},
			sidebar: [
				{ label: 'Začínáme', items: [{ autogenerate: { directory: 'zaciname' } }] },
				{
					label: 'Základy WordPressu',
					items: [{ autogenerate: { directory: 'zaklady-wordpressu' } }],
				},
				{ label: 'Funkce šablony', items: [{ autogenerate: { directory: 'funkce' } }] },
				{ label: 'Pro správce', items: [{ autogenerate: { directory: 'spravce' } }] },
			],
			pagination: true,
			lastUpdated: true,
		}),
	],
});
```

- [ ] **Krok 7: Ověřit, že se sestaví s novou konfigurací**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run build
```

Očekávaný výstup: `Complete!`, žádné varování o chybějících složkách sidebaru.
Prázdné `autogenerate` složky zatím neexistují — Starlight je zatím jen
přeskočí, sekce se objeví, jakmile v nich bude první stránka.

- [ ] **Krok 8: Ověřit lokální náhled**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run dev
```

Očekávaný výstup: server na `http://localhost:4321/`, titulek stránky
„Nápověda k šabloně KČT", jazyk stránky `cs`. Ukončit `Ctrl+C`.

---

## Task 2: Izolace od repozitáře a nasazení pluginu

Cílem je doložit dvě věci: že se `node_modules` a build dokumentace nedostanou
do gitu, a že se `docs/` nedostane ani na produkci, ani do instalačního ZIPu.
Druhé je předpoklad, na kterém stojí celé umístění dokumentace uvnitř repa —
patří ho ověřit, ne mu věřit.

**Ověřeno při provádění plánu:** ZIP `docs` vylučuje, **rsync na produkci ne**.
Krok `Deploy` v `deploy.yml` má jen výjimky `/.*`, `*.scss`, `*.sh`,
`node_modules`, `tests`, `/dist` a `/kct-*.zip`. Bez zásahu by se `docs/user/`
včetně sestaveného `dist/` kopíroval na oba produkční weby — `--exclude="/dist"`
je kotvené na kořen a na podsložku nesedne. Doplnění výjimky je proto součást
tohohle úkolu.

**Soubory:**
- Upravit: `.gitignore`
- Upravit: `.github/workflows/deploy.yml` (krok `Deploy`, jeden řádek)

- [ ] **Krok 1: Ověřit, že vzory dnes nesedí**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
git check-ignore -v docs/user/node_modules || echo "NEIGNOROVÁNO"
```

Očekávaný výstup: `NEIGNOROVÁNO`. Kořenový vzor `/node_modules/` je kotvený na
kořen repa a na podsložku nesedne.

- [ ] **Krok 2: Doplnit vzory do `.gitignore`**

Na konec `.gitignore` přidat:

```gitignore
# Uživatelská dokumentace (docs/user) je samostatný projekt Astro.
# Kořenové vzory výš jsou kotvené na kořen repa a na podsložku nesednou.
/docs/user/node_modules/
/docs/user/dist/
/docs/user/.astro/
```

- [ ] **Krok 3: Ověřit, že vzory zabraly**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
git check-ignore -v docs/user/node_modules docs/user/dist docs/user/.astro
git status --porcelain docs/user | grep -c node_modules || echo "0 — v pořádku"
```

Očekávaný výstup: `git check-ignore` vypíše všechny tři cesty s odkazem na
nová pravidla; `git status` na `node_modules` neukáže nic.

- [ ] **Krok 4: Doplnit výjimku do rsyncu na produkci**

V `.github/workflows/deploy.yml`, v kroku `Deploy`, do seznamu výjimek rsyncu
přidat řádek `--exclude="docs" \` hned za `--exclude="tests" \`. Výsledek:

```yaml
            rsync -av --delete \
              --exclude="/.*" \
              --exclude="*.scss" \
              --exclude="*.sh" \
              --exclude="node_modules" \
              --exclude="tests" \
              --exclude="docs" \
              --exclude="/dist" \
              --exclude="/kct-*.zip" \
              . "$SSH_USER@$SSH_SERVER:$SITE_PATH/wp-content/plugins/kct/"
```

Rsync běží s `--delete`, takže se `docs/` po nejbližším nasazení z produkčních
webů rovnou smaže — což je žádoucí, dnes tam leží `docs/superpowers/`.

- [ ] **Krok 5: Ověřit obě větve nasazení**

Výjimky z obou kroků `deploy.yml` puštěné nasucho do dočasných adresářů:

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct

# a) balíček ZIP — výjimky z kroku „Build plugin ZIP"
ZIP_OUT=$(mktemp -d)
rsync -a \
  --exclude="/.*" --exclude="*.scss" --exclude="*.sh" \
  --exclude="node_modules" --exclude="tests" --exclude="dist" \
  --exclude="docs" --exclude="tmp-*" \
  --exclude="package.json" --exclude="package-lock.json" \
  --exclude="webpack.config.js" \
  --exclude="composer*.json" --exclude="composer*.lock" \
  ./ "$ZIP_OUT/kct/"

# b) produkce — výjimky z kroku „Deploy" po úpravě z kroku 4
PROD_OUT=$(mktemp -d)
rsync -a \
  --exclude="/.*" --exclude="*.scss" --exclude="*.sh" \
  --exclude="node_modules" --exclude="tests" --exclude="docs" \
  --exclude="/dist" --exclude="/kct-*.zip" \
  ./ "$PROD_OUT/kct/"

test ! -d "$ZIP_OUT/kct/docs"  && echo "OK — docs není v ZIPu"
test ! -d "$PROD_OUT/kct/docs" && echo "OK — docs se nenasazuje na produkci"
test -f "$PROD_OUT/kct/kct.php" && echo "OK — plugin se nasazuje dál"
rm -rf "$ZIP_OUT" "$PROD_OUT"
```

Očekávaný výstup: všechny tři hlášky `OK`. Druhá je ta podstatná — bez úpravy
z kroku 4 by selhala.

---

## Task 3: Validátor interních odkazů

Dokumentace o třiceti stránkách se prokliká sama do rozbitých odkazů. Tohle je
jediná automatická kontrola, kterou plán zavádí — a je to zároveň jediný test,
který u statického webu něco skutečně dokazuje.

**Soubory:**
- Upravit: `docs/user/package.json` (závislost), `docs/user/astro.config.mjs`
- Dočasně: `docs/user/src/content/docs/index.mdx`

- [ ] **Krok 1: Nainstalovat validátor**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm install starlight-links-validator
```

- [ ] **Krok 2: Zapojit ho do konfigurace**

V `docs/user/astro.config.mjs` doplnit import a `plugins`:

```js
import starlightLinksValidator from 'starlight-links-validator';
```

a do volání `starlight({ ... })` přidat, hned za `title` a `description`:

```js
			plugins: [starlightLinksValidator()],
```

- [ ] **Krok 3: Napsat selhávající případ**

Na konec `docs/user/src/content/docs/index.mdx` dočasně přidat odkaz, který
nikam nevede:

```markdown
[Rozbitý odkaz na neexistující stránku](/tahle-stranka-neexistuje/)
```

- [ ] **Krok 4: Spustit build a ověřit, že selže**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run build
```

Očekávaný výstup: build **selže** s hlášením o neplatných odkazech a zmínkou
`/tahle-stranka-neexistuje/`. Návratový kód je nenulový — právě to potřebujeme,
aby na tom v Actions spadl i workflow.

- [ ] **Krok 5: Rozbitý odkaz odstranit**

Smazat řádek přidaný v kroku 3.

- [ ] **Krok 6: Spustit build a ověřit, že projde**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run build
```

Očekávaný výstup: `Complete!`, žádná chyba validátoru.

---

## Task 4: Workflow pro sestavení dokumentace

**Soubory:**
- Vytvořit: `.github/workflows/docs.yml`

- [ ] **Krok 1: Vytvořit workflow**

Soubor `.github/workflows/docs.yml`:

```yaml
# Sestavení uživatelské dokumentace (docs/user).
#
# Záměrně oddělené od deploy.yml: ten běží na tag, protože nasazení pluginu je
# vydání verze. Dokumentace se ale opravuje i mezi vydáními — překlep nemá
# čekat na release.
name: Dokumentace

on:
  push:
    branches:
      - main
    paths:
      - 'docs/user/**'
      - '.github/workflows/docs.yml'
  workflow_dispatch:

jobs:
  build:
    runs-on: ubuntu-22.04
    defaults:
      run:
        working-directory: docs/user

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      # Node 22, ne 18 jako v deploy.yml: Astro 7 má v engines node >=22.12.0.
      # Jsou to dva nezávislé buildy, deploy.yml o dokumentaci neví.
      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: 22
          cache: npm
          cache-dependency-path: docs/user/package-lock.json

      - name: Install dependencies
        run: npm ci

      # Build zároveň validuje interní odkazy (starlight-links-validator),
      # takže rozbitý odkaz shodí tenhle krok.
      - name: Build docs
        run: npm run build

      # Nasazení se doplní, až se rozhodne o hostingu (viz plán, Task 14).
      # Do té doby zůstává výsledek k dispozici jako artifact, ať je pipeline
      # od začátku funkční a otestovatelná.
      - name: Upload build
        uses: actions/upload-artifact@v4
        with:
          name: docs
          path: docs/user/dist
          retention-days: 7
```

- [ ] **Krok 2: Ověřit, že je YAML platný a filtry sedí**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
python3 -c "
import yaml
d = yaml.safe_load(open('.github/workflows/docs.yml'))
on = d.get(True, d.get('on'))
assert 'docs/user/**' in on['push']['paths'], on
assert on['push']['branches'] == ['main']
assert 'workflow_dispatch' in on
steps = d['jobs']['build']['steps']
assert any(s.get('with', {}).get('node-version') == 22 for s in steps), 'Node 22'
print('OK — workflow je platný a spouští se změnou v docs/user')
"
```

Očekávaný výstup: `OK — workflow je platný a spouští se změnou v docs/user`.
(Klíč `on` načte PyYAML jako boolean `True`, proto ten fallback.)

- [ ] **Krok 3: Ověřit, že `deploy.yml` zůstal nedotčený**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
git status --porcelain .github/workflows/deploy.yml
```

Očekávaný výstup: prázdný. Do stávajícího nasazení se nesahá.

---

## Task 5: Úvodní stránka a kostra navigace

**Soubory:**
- Upravit: `docs/user/src/content/docs/index.mdx`

- [ ] **Krok 1: Přepsat úvodní stránku**

Soubor `docs/user/src/content/docs/index.mdx`:

```mdx
---
title: Nápověda k šabloně KČT
description: Uživatelská příručka k webové šabloně pro odbory a oblasti Klubu českých turistů.
template: splash
hero:
  tagline: Jak spravovat web odboru nebo oblasti postavený na šabloně KČT.
  actions:
    - text: Začínáme
      link: /zaciname/co-je-sablona-kct/
      icon: right-arrow
---

import { Card, CardGrid } from '@astrojs/starlight/components';

<CardGrid>
	<Card title="Začínáme" icon="rocket">
		Co šablona umí, jak se přihlásit do administrace a co nastavit hned po
		převzetí webu.
	</Card>
	<Card title="Základy WordPressu" icon="open-book">
		Editor bloků, stránky, aktuality, fotky a menu — jen to, co při správě
		webu odboru opravdu potřebujete.
	</Card>
	<Card title="Funkce šablony" icon="star">
		Akce, odbory, trasy, bloky, vzhled webu a sdílení na Facebook.
	</Card>
	<Card title="Pro správce" icon="setting">
		Které pluginy instalovat, které naopak nepotřebujete, aktualizace a
		zálohy.
	</Card>
</CardGrid>
```

- [ ] **Krok 2: Ověřit sestavení**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run build
```

Očekávaný výstup: build **selže** ve validátoru odkazů — odkaz na
`/zaciname/co-je-sablona-kct/` zatím nikam nevede. To je v pořádku a čeká na
Task 6; workflow se ale do té doby nesmí pouštět s očekáváním zelené.

Kdo chce mít mezitím zelený build, ať dočasně zakomentuje blok `actions:` v
hlavičce a vrátí ho po dokončení Tasku 6.

---

## Task 6: Sekce „Začínáme"

Čtyři stránky. `sidebar.order` určuje pořadí v postranním panelu — bez něj by
se řadily podle abecedy.

**Soubory:**
- Vytvořit: `docs/user/src/content/docs/zaciname/co-je-sablona-kct.md`
- Vytvořit: `docs/user/src/content/docs/zaciname/prihlaseni-a-administrace.md`
- Vytvořit: `docs/user/src/content/docs/zaciname/role-uzivatelu.md`
- Vytvořit: `docs/user/src/content/docs/zaciname/prvni-nastaveni.md`

- [ ] **Krok 1: Napsat „Co je šablona KČT"**

Tahle stránka je vzor pro všechny další — stejná struktura hlavičky, stejný
rejstřík, stejná délka odstavců. Soubor
`docs/user/src/content/docs/zaciname/co-je-sablona-kct.md`:

```markdown
---
title: Co je šablona KČT
description: Z čeho se šablona skládá, co obstarává za vás a co po vás naopak chce.
sidebar:
  order: 1
---

Po přečtení téhle stránky bude jasné, co všechno je na webu odboru součástí
šablony — a proč se řada věcí nastavuje jinde, než se v běžném WordPressu
čeká.

## Dvě části, jeden celek

Šablona KČT jsou dvě věci, které se nasazují společně a bez sebe nefungují:

- **Plugin KČT** přidává to, co běžný WordPress neumí: typy obsahu Akce,
  Odbory, Trasy a Místa, vlastní bloky do editoru, načítání akcí z centrální
  databáze KČT a odesílání pozvánek na Facebook.
- **Šablona vzhledu KČT** určuje, jak web vypadá — hlavičku, patičku, výpisy
  akcí, tři varianty stylu.

Obojí se aktualizuje najednou a spravuje to správce sítě. Web odboru je jeden
z několika webů provozovaných na společné instalaci, takže část nastavení je
sdílená a k části se běžný redaktor nedostane.

## Co šablona dělá sama

Řadu věcí, na které se ve WordPressu běžně instalují pluginy, obstarává
šablona bez nastavování:

- zmenšuje a převádí nahrané fotky, aby se web nezpomalil,
- otevírá obrázky v galeriích na kliknutí,
- vytváří náhledové obrázky pro sdílení na sociálních sítích,
- načítá akce z centrální databáze KČT,
- zobrazuje mapy tras a akcí.

Kompletní seznam včetně toho, co z něj plyne pro instalaci pluginů, je na
stránce [Pluginy, které nepotřebujete](/spravce/zbytecne-pluginy/).

## Co je naopak na vás

- vyplnit kód odboru nebo oblasti, aby se načítaly správné akce,
- nahrát logo a vybrat styl webu,
- psát obsah — aktuality, stránky a vlastní akce,
- udržovat menu.

Kudy na to vede stránka [První nastavení webu](/zaciname/prvni-nastaveni/).
```

- [ ] **Krok 2: Napsat „Přihlášení a administrace"**

Soubor `docs/user/src/content/docs/zaciname/prihlaseni-a-administrace.md`,
hlavička:

```markdown
---
title: Přihlášení a administrace
description: Jak se dostat do administrace webu a co v ní kde je.
sidebar:
  order: 2
---
```

Musí pokrýt:
- adresa přihlášení (`/wp-admin/`), zapomenuté heslo, odhlášení
- levé menu administrace a co v které položce je: Příspěvky (aktuality), Akce,
  Odbory, Trasy, Místa, Média, Stránky, Vzhled, Nastavení
- horní lišta a přepínání mezi administrací a webem
- kde je „Nastavení → KČT" a co se za tou položkou skrývá (jen odkaz na sekci
  Funkce šablony, ne popis polí — ten patří do stránek o funkcích)

- [ ] **Krok 3: Napsat „Role uživatelů"**

Soubor `docs/user/src/content/docs/zaciname/role-uzivatelu.md`, hlavička:

```markdown
---
title: Role uživatelů
description: Kdo co smí a jakou roli komu přidělit.
sidebar:
  order: 3
---
```

Musí pokrýt:
- role WordPressu a co která smí: Redaktor, Autor, Spolupracovník, Šéfredaktor,
  Správce
- doporučení pro odbor: běžný přispěvatel = Autor, člověk spravující celý web
  = Šéfredaktor
- proč roli Správce sítě nemá nikdo z odboru a na koho se obrátit
- upozornění, že sdílení na Facebook a nastavení KČT vyžadují oprávnění
  `manage_options`, tedy roli Správce (zdroj: `capability` v
  `src/Settings.php`)

- [ ] **Krok 4: Napsat „První nastavení webu"**

Soubor `docs/user/src/content/docs/zaciname/prvni-nastaveni.md`, hlavička:

```markdown
---
title: První nastavení webu
description: Co vyplnit hned po převzetí webu, aby fungoval a vypadal jako váš.
sidebar:
  order: 4
---
```

Musí pokrýt, jako číslovaný postup:
1. Název a popis webu (Nastavení → Obecné)
2. Kód odboru nebo oblasti (Nastavení → KČT) — tři číslice pro oblast, šest
   pro odbor; bez něj se nenačtou akce (zdroj: pole `id_code` v
   `src/Settings.php`)
3. Logo a druhé logo v hlavičce (Vzhled → Přizpůsobit → Název webu)
4. Styl šablony a primární barva (Vzhled → Přizpůsobit → Vzhled šablony)
5. Hlavní menu (Vzhled → Menu)
6. Widgety do postranního sloupce a patičky

Každý bod odkazem na podrobnou stránku, kde existuje.

- [ ] **Krok 5: Ověřit sestavení a navigaci**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run build
```

Očekávaný výstup: build **selže** ve validátoru odkazů. Texty výš odkazují na
`/spravce/zbytecne-pluginy/` a další stránky, které vzniknou až v Tascích 7–11
— dokud nejsou, jsou to rozbité odkazy a validátor je právě proto zachytí.

Zelený build tedy nečekat dřív než po Tasku 11. Ověřit se teď dá jen to, že
stránky vznikly a mají platnou hlavičku:

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
ls src/content/docs/zaciname | wc -l
grep -L "^title:" src/content/docs/zaciname/*.md || echo "OK — všechny mají title"
```

Očekávaný výstup: `4` a `OK — všechny mají title`.

---

## Task 7: Sekce „Základy WordPressu"

Sedm stránek. Rozsah je vymezený tím, co uživatel KČT skutečně dělá — revize,
import/export ani správa uživatelů sem nepatří.

**Soubory:** vše v `docs/user/src/content/docs/zaklady-wordpressu/`

- [ ] **Krok 1: `editor-bloku.md`** (`order: 1`, title „Editor bloků")

Musí pokrýt: co je blok; vložení blokem `+`; přesouvání a mazání; panel
nastavení bloku vpravo; nejpoužívanější bloky jádra (odstavec, nadpis, obrázek,
galerie, sloupce, tlačítko, seznam); uložení a náhled. Bloky šablony sem
nepatří, ty mají vlastní sekci — jen odkaz na ni.

- [ ] **Krok 2: `stranky-a-aktuality.md`** (`order: 2`, title „Stránky a aktuality")

Musí pokrýt: rozdíl mezi stránkou (stálý obsah, v menu) a aktualitou (datovaný
příspěvek ve výpisu); kdy použít co na webu odboru; rubriky a štítky;
hierarchie stránek. Zmínit, že Akce jsou třetí, samostatný typ obsahu s vlastní
stránkou v sekci Funkce šablony.

- [ ] **Krok 3: `media-a-fotky.md`** (`order: 3`, title „Média a fotky")

Nejdůležitější stránka celé sekce — chování šablony u obrázků je nezvyklé a
uživatel by ho jinak nechápal.

Musí pokrýt: nahrání do knihovny médií; **že se nahrané fotky automaticky
zmenší na 2048 px delší strany a převedou do formátu WebP**, včetně vysvětlení
proč (zdroj: docblock a konstanty v `src/Features/ImageUploads.php` — 2048 px
proto, že největší zobrazovaná velikost na webu je 2048 px a snímek z
fotoaparátu ve 2560 px zabere kolem 1 MB proti 585 kB); že se z fotek maže
technický popis fotoaparátu (zdroj: `src/Features/ImageMetadata.php`); že se
starší knihovna zpětně nepřevádí a proč; alternativní text a popisek a k čemu
slouží; doporučení nahrávat originály, ne předem zmenšené soubory.

- [ ] **Krok 4: `menu-a-navigace.md`** (`order: 4`, title „Menu a navigace")

Musí pokrýt: Vzhled → Menu; přidání stránky, rubriky a vlastního odkazu;
vnořování položek do rozbalovacího menu; přiřazení k pozici „Hlavní menu"
(zdroj: `register_nav_menus` v `themes/kct/functions.php`).

- [ ] **Krok 5: `postranni-sloupec-a-paticka.md`** (`order: 5`, title „Postranní sloupec a patička")

Musí pokrýt: dvě oblasti pro widgety — „Postranní sloupec" a „Patička" (zdroj:
`register_sidebar` v `themes/kct/functions.php`); vkládání widgetů; co se do
patičky na webech odborů obvykle dává (kontakt, odkazy).

- [ ] **Krok 6: `odkazy.md`** (`order: 6`, title „Odkazy")

Musí pokrýt: odkaz na stránku vlastního webu; externí odkaz a otevírání v nové
kartě; odkaz na soubor v knihovně médií (PDF, GPX); proč nekopírovat adresy z
administrace.

- [ ] **Krok 7: `publikovani.md`** (`order: 7`, title „Publikování")

Musí pokrýt: koncept vs. publikováno; náhled před publikací; naplánování na
později; skrytí a soukromý obsah; odstranění do koše. Upozornit, že u aktualit
a akcí může být zapnuté automatické sdílení na Facebook, takže publikace není
bez následků — odkaz na stránku o sdílení.

- [ ] **Krok 8: Ověřit počet stránek**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
ls src/content/docs/zaklady-wordpressu | wc -l
```

Očekávaný výstup: `7`.

---

## Task 8: Funkce šablony — typy obsahu

Čtyři stránky. `sidebar.order` 1–4, bloky pak dostanou 5 a zbytek 6–9.

**Soubory:** vše v `docs/user/src/content/docs/funkce/`

- [ ] **Krok 1: `akce.md`** (`order: 1`, title „Akce")

Musí pokrýt:
- že akce mají dva zdroje: vlastní, zakládané ručně v administraci, a
  importované z centrální databáze akcí KČT
- že importovaných je drtivá většina — na sokct.cz má z 319 vypisovaných akcí
  vlastní příspěvek 12 (zdroj: docblock v `src/Features/DbEventShare.php`)
- že se importované akce při aktualizaci přepisují, takže ruční úpravy v nich
  nepřežijí, a jak se z importované akce udělá vlastní
- pole vlastní akce: termín od–do, místo, typ akce, popis, obrázek, trasa
- jak se akce zobrazují: výpis `/akce/`, mapa nad výpisem, blok Kalendář akcí
- kde se nastavuje kód odboru, na kterém import stojí

- [ ] **Krok 2: `odbory.md`** (`order: 2`, title „Odbory")

Musí pokrýt: k čemu typ obsahu Odbory slouží (výpis odborů na webu oblasti);
že se seznam načítá z centrální databáze KČT (zdroj: `import_departments()` v
`src/Features/Departments.php`); výpis `/odbory/` a jeho nadpis nastavitelný v
Přizpůsobení.

> **Zrušeno při provádění (2026-09-02).** Trasy a Místa jsou trvale vypnuté
> (`PostTypesManager` je neinstancuje), takže se nedokumentují. Stránky
> `trasy-a-gpx.md` a `mista.md` vznikly a byly zase odstraněny; povolení
> nahrávat `.gpx` se přesunulo do `zaklady-wordpressu/media-a-fotky.md`.
> Kroky 3 a 4 se neprovádějí.

- [ ] **Krok 3: `trasy-a-gpx.md`** (`order: 3`, title „Trasy a soubory GPX")

Musí pokrýt: typ obsahu Trasy; že šablona povoluje nahrání souborů `.gpx` do
knihovny médií, které WordPress sám odmítá (zdroj: `allow_gpx_upload()` v
`src/Features/Roads.php`); jak se trasa připojí k akci; že se z GPX vykreslí
mapa.

- [ ] **Krok 4: `mista.md`** (`order: 4`, title „Místa")

Musí pokrýt: k čemu typ obsahu Místa slouží a jak se váže na akce a trasy.

- [ ] **Krok 5: Ověřit**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
ls src/content/docs/funkce/*.md* | wc -l
```

Očekávaný výstup: `2` (akce a odbory; Trasy a Místa se nedokumentují).

---

## Task 9: Funkce šablony — bloky

Sedm stránek v podsložce, aby v postranním panelu netvořily plochý seznam.
Popis, název i pole každého bloku se berou z jeho `block.json` — texty tam už
česky jsou a musí sedět s tím, co uživatel vidí v editoru.

**Soubory:** vše v `docs/user/src/content/docs/funkce/bloky/`

- [ ] **Krok 1: Založit rozcestník podsložky**

Soubor `docs/user/src/content/docs/funkce/bloky/index.md`:

```markdown
---
title: Bloky šablony
description: Sedm bloků, které šablona přidává do editoru, a k čemu se který hodí.
sidebar:
  order: 5
---

Šablona přidává do editoru vlastní bloky ve skupině **KČT**. Vloží se stejně
jako každý jiný blok — tlačítkem `+` a vyhledáním názvu.

| Blok | K čemu je |
| --- | --- |
| [Úvodní obrázek](/funkce/bloky/uvodni-obrazek/) | Velký obrázek s nadpisem, textem a tlačítkem na začátku stránky |
| [CTA blok](/funkce/bloky/cta-blok/) | Výzva k akci s obrázkem na pozadí a tlačítkem |
| [Kalendář akcí](/funkce/bloky/kalendar-akci/) | Výpis akcí z databáze KČT |
| [Aktuality](/funkce/bloky/aktuality/) | Výpis nejnovějších příspěvků |
| [Předtitulek](/funkce/bloky/predtitulek/) | Malý popisek s trikolórou nad nadpisem sekce |
| [Obrázek s obsahem vedle](/funkce/bloky/obrazek-s-obsahem/) | Obrázek a libovolný obsah vedle něj |
| [Info karta](/funkce/bloky/info-karta/) | Karta s obrázkem, nadpisem, textem a odkazem |
```

- [ ] **Krok 2: `uvodni-obrazek.md`** (`order: 1`, title „Úvodní obrázek")

Blok `kct/cover`. Pole: obrázek na pozadí, nadpis, text, odkaz tlačítka.
Zmínit souvislost s volbou „Průhledné menu přes hero" v Přizpůsobení.

- [ ] **Krok 3: `cta-blok.md`** (`order: 2`, title „CTA blok")

Blok `kct/action`. Pole: nadpis, text, odkaz, obrázek na pozadí, obrázek v
popředí, umístění obrázku, přechod (gradient).

- [ ] **Krok 4: `kalendar-akci.md`** (`order: 3`, title „Kalendář akcí")

Blok `kct/events`. Dynamický — obsah se skládá při zobrazení stránky, v
editoru se tedy nezobrazuje výsledný výpis. Pole: časové období, počet akcí,
tlačítko. Odkaz na stránku Akce, odkud se data berou.

- [ ] **Krok 5: `aktuality.md`** (`order: 4`, title „Aktuality")

Blok `kct/news`. Také dynamický. Pole: tlačítko. Vypisuje nejnovější
příspěvky.

- [ ] **Krok 6: `predtitulek.md`** (`order: 5`, title „Předtitulek")

Blok `kct/eyebrow`. Pole: text. Umísťuje se nad nadpis sekce.

- [ ] **Krok 7: `obrazek-s-obsahem.md`** (`order: 6`, title „Obrázek s obsahem vedle")

Blok `kct/image-content`. Pole: obrázek, umístění obrázku, pozadí. Dovnitř se
vkládají další bloky.

- [ ] **Krok 8: `info-karta.md`** (`order: 7`, title „Info karta")

Blok `kct/infobox-item`. Pole: obrázek, nadpis, text, odkaz, barva. Hodí se do
sloupců — popsat postup: vložit blok Sloupce, do každého sloupce jednu Info
kartu.

Na konec stránky odstavec o starším bloku **Info boxy** (`kct/infobox`): má v
metadatech `"inserter": false`, takže ho v editoru nejde vložit. Existuje jen
kvůli staršímu obsahu — když na něj někdo narazí ve starší stránce, může ho
dál upravovat, ale nový už nevytvoří. Náhradou jsou Info karty ve sloupcích.

- [ ] **Krok 9: Ověřit**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
ls src/content/docs/funkce/bloky | wc -l
grep -L "^title:" src/content/docs/funkce/bloky/*.md || echo "OK — všechny mají title"
```

Očekávaný výstup: `8` (sedm bloků + rozcestník) a `OK — všechny mají title`.

---

## Task 10: Funkce šablony — vzhled, sdílení a SEO

Čtyři stránky, `sidebar.order` 6–9.

**Soubory:** vše v `docs/user/src/content/docs/funkce/`

- [ ] **Krok 1: `vzhled-webu.md`** (`order: 6`, title „Vzhled webu")

Zdroj: `themes/kct/inc/customizer.php`. Popsat každou volbu pod jejím
skutečným názvem z administrace, aby se dala najít:

- **Styl šablony** (Vzhled → Přizpůsobit → Vzhled šablony): tři varianty —
  Obrazový, Časopisový, Kartový; výchozí je Obrazový
- **Průhledné menu přes hero**: menu se u hero sekcí překrývá přes obrázek,
  při scrollu se podbarví
- **Nadpis stránky Akce** a **Nadpis stránky Odbory**: prázdné pole znamená
  výchozí „Akce" a „Odbory"
- **Zobrazit mapu ve výpisu akcí**: mapa nad výpisem na `/akce/`, dá se vypnout
- **Vyhledávání v hlavičce**: výchozí vypnuto, protože hlavička je na každém
  webu jinak zaplněná; bez něj se dá hledat přes adresu `/?s=výraz`
- **Odkaz a text tlačítka v hlavičce**: tlačítko „Stát se členem"; prázdný
  odkaz = tlačítko se nezobrazí
- **Primární barva** (Přizpůsobit → Barvy): prázdné = výchozí barva zvoleného
  stylu
- **Druhé logo v hlavičce** (Přizpůsobit → Název webu): Žádné, KČT, Vaše dobrá
  značka

- [ ] **Krok 2: `sdileni-na-facebook.md`** (`order: 7`, title „Sdílení na Facebook")

Zdroj: `src/Settings.php`, `src/Features/FacebookShare.php`,
`src/Features/DbEventShare.php`. Musí pokrýt:

- co funkce dělá: sama odesílá aktuality a pozvánky na akce na facebookovou
  stránku odboru
- nastavení (Nastavení → KČT): ID facebookové stránky, page access token,
  „Sdílet automaticky — aktuality", „Sdílet automaticky — akce", „Kolik dní
  před akcí odeslat", „V kolik hodin odeslat", tlačítko „Ověřit připojení"
- **proč je výchozích 12 dní**: sobotní akce tím vyjde na pondělí a nedělní na
  úterý, tedy na začátek týdne, kdy lidé plánují další víkend
- že se odesílají i akce z centrální databáze, které vlastní příspěvek nemají
- že se sdílení dá u konkrétního příspěvku zapnout a vypnout
- upozornění pro správce: token uložený v nastavení leží v databázi a v každé
  její záloze; bezpečnější je konstanta `KCT_FB_PAGE_TOKEN` ve `wp-config.php`,
  což je práce pro správce sítě — odkaz do sekce Pro správce

- [ ] **Krok 3: `sdileci-obrazky.md`** (`order: 8`, title „Sdílecí obrázky")

Zdroj: `src/Features/OgImages.php`, `src/Og/`. Musí pokrýt: že se pro každou
aktualitu a akci sám vykreslí náhledový obrázek pro sociální sítě; co se do něj
propíše (nadpis, termín, fotka); že se přegeneruje po změně obsahu; že se kvůli
tomu nemusí instalovat žádný plugin.

- [ ] **Krok 4: `seo.md`** (`order: 9`, title „SEO")

Zdroj: `src/Seo/`. Musí pokrýt: že šablona sama doplňuje strukturovaná data u
akcí a odborů a přidává akce do mapy webu; že funguje jak s pluginem Rank Math,
tak bez něj; co dělat, když je Rank Math nainstalovaný (nepřenastavovat, co už
řeší šablona).

- [ ] **Krok 5: Ověřit**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
ls src/content/docs/funkce/*.md | wc -l
```

Očekávaný výstup: `8`.

---

## Task 11: Sekce „Pro správce"

Sedm stránek. Jádrem je stránka o zbytečných pluginech — kvůli ní z velké části
dokumentace vzniká.

**Soubory:** vše v `docs/user/src/content/docs/spravce/`

- [ ] **Krok 1: `doporucene-pluginy.md`** (`order: 1`, title „Doporučené pluginy")

Musí pokrýt seznam pluginů, které se na webech sítě používají a proč: Rank Math
(SEO, volitelně), Query Monitor (jen pro ladění, na produkci vypnout), WP
Optimize (úklid databáze), Disable Comments, Better Search Replace (jen při
stěhování). U každého jedna věta, k čemu je a kdy ho odbor opravdu potřebuje.

- [ ] **Krok 2: `zbytecne-pluginy.md`** (`order: 2`, title „Pluginy, které nepotřebujete")

Nejdůležitější stránka sekce. Tabulka „co chcete řešit → co už to řeší → co
tedy neinstalovat":

| Co byste chtěli řešit | Řeší to šablona | Neinstalujte |
| --- | --- | --- |
| Zvětšování obrázků po kliknutí | vestavěný lightbox WordPressu, zapnutý pro všechny obrázky a galerie | Lightbox PhotoSwipe, Simple Lightbox, FooBox |
| Zmenšování a komprese fotek | strop 2048 px a převod do WebP při nahrání | Smush, ShortPixel, EWWW, Imagify |
| Odstranění dat fotoaparátu z fotek | úklid při nahrání | pluginy na čištění EXIF |
| Náhledové obrázky pro Facebook | automaticky vykreslované karty | pluginy na Open Graph |
| Odesílání příspěvků na Facebook | vestavěné sdílení včetně časování | Jetpack Publicize, Blog2Social |
| Kalendář a výpis akcí | typ obsahu Akce a import z databáze KČT | The Events Calendar, Modern Events Calendar |
| Mapy | vestavěné mapy tras a akcí | pluginy s Google Maps |
| Galerie fotek | blok Galerie v editoru + lightbox | NextGEN Gallery, Envira |

Pod tabulkou odstavec, proč na tom záleží: každý plugin navíc je věc, která se
aktualizuje, zpomaluje web a může se rozbít; a dva pluginy dělající totéž si
navzájem přebíjejí výstup.

Přidat větu, že pokud je některý z těch pluginů na webu už nainstalovaný,
jeho vypnutí je věc správce sítě — jen ho vypnout může znamenat ztrátu obsahu
(typicky galerie NextGEN).

- [ ] **Krok 3: `aktualizace.md`** (`order: 3`, title „Aktualizace šablony")

Musí pokrýt: že se plugin i šablona aktualizují centrálně a odbor se o to
nestará; že se aktualizace WordPressu a ostatních pluginů řeší běžnou cestou;
kde se pozná, jaká verze šablony na webu běží; odkaz na stránku Změny v
šabloně.

- [ ] **Krok 4: `zalohy.md`** (`order: 4`, title „Zálohy")

Musí pokrýt: co všechno se zálohuje (databáze + soubory) a kdo to dělá; proč
je knihovna médií to jediné, co odbor nemá nikde jinde; doporučení stáhnout si
zálohu před velkým zásahem do obsahu.

- [ ] **Krok 5: `bezpecnost.md`** (`order: 5`, title „Bezpečnost")

Musí pokrýt: silná hesla a dvoufaktorové ověření; nesdílet jeden účet mezi
lidmi; odebrat účty lidí, kteří odešli; neinstalovat pluginy z neznámých
zdrojů; kde je uložený facebookový token a proč patří spíš do `wp-config.php`
než do nastavení (zdroj: `desc` u pole `fb_page_token` v `src/Settings.php`).

- [ ] **Krok 6: `reseni-problemu.md`** (`order: 6`, title „Řešení problémů")

Musí pokrýt nejčastější situace formou „příznak → co s tím":
- nenačítají se akce → zkontrolovat kód odboru v Nastavení → KČT
- příspěvek se neodeslal na Facebook → tlačítko „Ověřit připojení", platnost
  tokenu
- změna na webu není vidět → stránková cache, načíst s obejitím cache
- nahraná fotka vypadá jinak → automatické zmenšení a převod do WebP
- na koho se obrátit, když nic z toho nepomůže

- [ ] **Krok 7: `zmeny-v-sablone.md`** (`order: 7`, title „Změny v šabloně")

Uživatelský výtah z vydaných verzí — co přibylo a co se změnilo z pohledu
správce webu, ne seznam commitů. Zdroj: vydání na
`https://github.com/martin-svoboda/kct/releases`. Založit s posledními třemi
verzemi; starší historie se nedoplňuje zpětně.

- [ ] **Krok 8: Ověřit celou dokumentaci**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
find src/content/docs -name '*.md*' | wc -l
npm run build
```

Očekávaný výstup: `35` souborů (rozcestník, 4 + 7 + 8 stránek, 8 bloků včetně
jejich rozcestníku, 7 pro správce) a build skončí `Complete!` — tedy včetně
validátoru odkazů, který teď poprvé prochází kompletní síť odkazů mezi
sekcemi. Pokud selže, vypíše, který odkaz kam nevede; opravit a spustit znovu.

- [ ] **Krok 9: Projít výsledek v prohlížeči**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run dev
```

Zkontrolovat: čtyři sekce v postranním panelu ve správném pořadí, uvnitř
každé stránky seřazené podle `sidebar.order`, funkční vyhledávání (Pagefind se
sestavuje až v `build`, v `dev` je prázdné — hledání ověřit přes
`npm run preview` po buildu).

---

## Task 12: Screenshoty

Dělá se až po dopsání textů — teprve tehdy je jasné, které obrazovky obrázek
opravdu potřebují. Cíl není nafotit celou administraci, ale místa, kde by slovní
popis selhal.

**Soubory:**
- Vytvořit: `docs/user/src/assets/*.png`
- Upravit: stránky, do kterých se obrázky vkládají

- [ ] **Krok 1: Spustit lokální web**

```bash
cd /Users/martin/Sites/sokct
ddev start
```

Screenshoty se pořizují z lokální instance (`sablona.sokct`), ne z produkce —
na obrázcích nemá být reálný obsah ani osobní údaje.

- [ ] **Krok 2: Pořídit snímky**

Okno prohlížeče v šířce 1440 px, světlý režim. Minimální sada:
- `admin-nastaveni-kct.png` — Nastavení → KČT s vyplněným kódem odboru
- `admin-prizpusobit-vzhled.png` — Přizpůsobit → Vzhled šablony s výběrem stylu
- `admin-editor-bloky-kct.png` — vkladač bloků s otevřenou skupinou KČT
- `admin-akce-detail.png` — editace akce s poli termínu a místa
- ~~`admin-fb-metabox.png` — panel sdílení na Facebook u příspěvku~~

**Nepořízeno, a nejde to.** Panel Facebook se u příspěvku registruje jen
tehdy, když jsou v Nastavení → KČT vyplněné ID stránky a token
(`FacebookShare` ř. 120). Druhý panel „Facebook — stav odeslání" navíc
vyžaduje příspěvek, který už byl odeslán, skončil chybou, nebo má naplánované
odeslání (`ShareMetabox::register()`). Snímek by tedy vyžadoval funkční
napojení na Facebook, ne jen lokální web. Text stránky
`/funkce/sdileni-na-facebook/` panel popisuje slovy včetně všech tří polí.

Ukládat do `docs/user/src/assets/`. Astro je při sestavení samo převede do
WebP — naměřeno 165 kB PNG → 64 kB WebP — a stránka, do které se vkládají,
musí mít příponu `.mdx`.

- [ ] **Krok 3: Vložit do stránek**

Ve stránce, kam obrázek patří, importovat a vložit — Starlight obrázky
zpracuje a zmenší sám:

```mdx
import { Image } from 'astro:assets';
import nastaveni from '../../../assets/admin-nastaveni-kct.png';

<Image src={nastaveni} alt="Stránka Nastavení KČT s vyplněným kódem odboru" />
```

Stránka, do které se vkládá obrázek, musí mít příponu `.mdx`, ne `.md` —
soubor se tedy přejmenuje a odkazy na něj zůstávají beze změny (adresa stránky
se příponou nemění).

- [ ] **Krok 4: Ověřit**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct/docs/user
npm run build
```

Očekávaný výstup: `Complete!`, žádná chyba o nenalezeném obrázku.

---

## Task 13: Pravidlo údržby

Bez tohohle kroku dokumentace zastará — a to je jediná chyba, která ji zabije.

**Soubory:**
- Vytvořit: `CLAUDE.md` (v kořeni repozitáře pluginu, dosud neexistuje)

- [ ] **Krok 1: Vytvořit `CLAUDE.md`**

Soubor `wp-content/plugins/kct/CLAUDE.md`:

```markdown
# Plugin KČT

Plugin se šablonou pro odbory a oblasti KČT. Nasazuje se tagem přes
`.github/workflows/deploy.yml` na sokct.cz i posazavskastezka.cz a zároveň
vzniká instalační ZIP jako příloha vydání.

## Uživatelská dokumentace

`docs/user/` je uživatelská příručka (Astro Starlight), nasazovaná samostatným
workflow `.github/workflows/docs.yml`. Do produkčního balíčku pluginu se
nedostane — `deploy.yml` vylučuje celou složku `docs`.

**Mění-li se uživatelsky viditelné chování, mění se ve stejném PR i
`docs/user/`.** Uživatelsky viditelné je všechno, co správce webu uvidí v
administraci nebo na webu: nové či přejmenované pole v nastavení, nová volba v
Přizpůsobení, nový nebo změněný blok, jiné výchozí chování u obrázků, akcí
nebo sdílení.

Vědomě k tomu není kontrola v CI: nejde strojově poznat, která změna kódu je
uživatelsky viditelná, a kontrola, která se dá obejít prázdnou úpravou, přidá
jen tření bez záruky.

## Dokumentace vývoje

`docs/superpowers/specs/` jsou návrhy, `docs/superpowers/plans/` implementační
plány. Obojí je interní a nikam se nenasazuje.
```

- [ ] **Krok 2: Ověřit, že se soubor nenasazuje**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
OUT=$(mktemp -d)
rsync -a --exclude="/.*" --exclude="docs" --exclude="node_modules" \
  --exclude="*.scss" --exclude="dist" ./ "$OUT/kct/"
if [ -f "$OUT/kct/CLAUDE.md" ]; then
  echo "CLAUDE.md se nasazuje s pluginem"
else
  echo "CLAUDE.md se nenasazuje"
fi
rm -rf "$OUT"
```

Očekávaný výstup: `CLAUDE.md se nasazuje s pluginem`. Výjimky v `deploy.yml`
ho nevylučují, takže do balíčku půjde — stejně jako dnes `README.md`. Nic to
nerozbíjí, je to textový soubor o pár kilobajtech; uvedeno jen proto, aby to
nebylo překvapení. Kdyby vadil, doplní se `--exclude="CLAUDE.md"` do obou
rsynců v `deploy.yml`.

---

## Task 14: Nasazení

**Rozhodnuto 2026-09-02: GitHub Pages.** Repozitář `martin-svoboda/kct` je
veřejný (API vrací 200), takže jsou Pages zdarma a odpadá práce na serveru i
riziko, že si `napoveda.sokct.cz` vezme WordPress kvůli `SUBDOMAIN_INSTALL`.

Kód je hotový: `astro.config.mjs` má `site: 'https://napoveda.sokct.cz'`
(od té chvíle se generuje i mapa webu) a `docs.yml` nasazuje na Pages ve dvou
úlohách `build` → `deploy`.

Zbývající kroky jsou v nastavení GitHubu a v DNS, tedy mimo repozitář. Popisuje
je samostatný postup:

**→ [Zprovoznění nápovědy na napoveda.sokct.cz](2026-09-02-zprovozneni-napovedy-runbook.md)**

Ve zkratce: zapnout Pages se zdrojem „GitHub Actions" (nutně **před** prvním
pushem, jinak nasazení selže na `Get Pages site failed`), založit v DNS
`CNAME napoveda → martin-svoboda.github.io.`, vyplnit vlastní doménu v
nastavení Pages a zapnout Enforce HTTPS, jakmile je vystavený certifikát.
