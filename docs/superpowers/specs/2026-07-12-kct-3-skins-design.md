# KČT šablona — 3 vizuální styly (skiny)

**Datum:** 2026-07-12
**Rozsah:** celý web (včetně inner stránek), výběr globálně přes WP Customizer
**Priorita:** udržitelnost + jasné vlastnictví souborů („co patří do jakého skinu")

## 1. Cíl

Umožnit v nastavení šablony vybrat jeden ze tří vizuálních stylů, definovaných
v Claude Design projektu `KCT Homepage 1a/1b/1c`:

| design | slug / soubor | popisek v Customizeru | charakter |
|---|---|---|---|
| 1a | `photo` → `photo.css` | **Obrazový** | velké fotografie, dramatické překryvy, foto-karty akcí, modrý akcent, kulaté rohy |
| 1b | `magazine` → `magazine.css` | **Časopisový** | redakční, teplý papír, číslované seznamy, zelený akcent, hranaté rohy |
| 1c | `cards` → `cards.css` | **Kartový** | centrovaný hero, přehledné bílé karty, modrý akcent, kulaté rohy |

Všechny tři sdílejí stejný obsahový skelet, fonty (Oswald + Plus Jakarta Sans)
a trikolóru KČT (červená/žlutá/zelená). Liší se ~85 % v **design tokenech**
a ve **výběru layoutu u několika sekcí**.

Obsah se **nemění**. Do bloků se pouze **doplní** class-hooky / volitelné sloty,
aby šly designy obecně nastylovat.

## 2. Architektura CSS

**Rozhodnutí:** core + skin se buildí do **jednoho self-contained souboru na skin**.
Na stránce se načte přesně jeden skin `<link>` (+ malý linkovaný override, viz níže).
Žádné inline styly.

```
assets/styles/
├── core/                    # sdílený SCSS partial (NEMÁ vlastní webpack entry)
│   ├── _index.scss          # @import všech core partials
│   ├── _variables.scss      # deklarace tokenů + fallbacky, breakpointy, grid
│   ├── _section-head.scss   # sdílená komponenta hlavičky sekce
│   ├── components/          # base, layout, header, footer, button, forms, navigation,
│   │                        #   accessibility, gutenberg, articles, departmentpost,
│   │                        #   eventpost, events-filter, loading, category-menu, gallery, helpers
│   └── blocks/              # cover, action, events, image-content, infoboxes, news
└── skins/
    ├── _contract.scss       # dokumentace: seznam VŠECH tokenů, které skin musí nastavit
    ├── photo.scss           # → build/photo.css     (Obrazový)
    ├── magazine.scss        # → build/magazine.css  (Časopisový)
    └── cards.scss           # → build/cards.css     (Kartový)
```

Každý skin soubor má dvě části:

```scss
// skins/magazine.scss
:root {
  /* 1. KOMPLETNÍ SADA DEFAULT TOKENŮ dle _contract.scss */
  --page-bg: #e4e2db; --surface: #fbfaf6; --text: #1b1b17; --primary: #009640; /* … */
}
@import '../core/index';   /* 2. celá sdílená struktura, čte var(--token) */

/* 3. LAYOUT MODIFIKÁTORY — jen co NEJDE tokenem */
.block-events { /* číslovaný seznam 01–05 místo karet */ }
```

Core používá výhradně `var(--token)` — je skin-agnostický a nikdy se nenačítá sám.
Protože se načítá jen jeden skin, soubor nemusí nic scopovat `.skin-*`; přesto na
`<body>` přidáme třídu `skin-<slug>` (užitečné pro editor a případné výjimky).

### Build (webpack.config.js)

```js
entry: {
  'theme':    './assets/scripts/theme.js',          // už jen JS (styly ze skinů)
  'photo':    './assets/styles/skins/photo.scss',
  'magazine': './assets/styles/skins/magazine.scss',
  'cards':    './assets/styles/skins/cards.scss',
},
copy: [
  {source:'editor-style.css', destination:'themes/kct/editor-style.css'},
  {source:'photo.css',    destination:'themes/kct/photo.css'},
  {source:'magazine.css', destination:'themes/kct/magazine.css'},
  {source:'cards.css',    destination:'themes/kct/cards.css'},
],
```

`theme.scss` (dnešní core entry) se rozdělí: obsah se přesune do `core/_index.scss`
jako partial; samotný `theme.scss` entry zanikne (styly dodávají skiny).

### Výběr v Customizeru + enqueue

- `themes/kct/inc/customizer.php`: nová sekce „Vzhled šablony", setting `kct_skin`
  (radio/select: `photo` | `magazine` | `cards`, popisky Obrazový / Časopisový / Kartový,
  default `photo`), `transport => postMessage` pro živý náhled (přepnutí `<link href>`
  + body class v `customizer.js`).
- `themes/kct/functions.php`, `kct_scripts()`:
  ```php
  $skin = get_theme_mod('kct_skin', 'photo');
  $allowed = ['photo','magazine','cards'];
  if (!in_array($skin, $allowed, true)) { $skin = 'photo'; }
  wp_enqueue_style('kct-style', get_stylesheet_uri(), [], _S_VERSION);          // style.css (hlavička motivu)
  wp_enqueue_style('kct-skin',  get_stylesheet_directory_uri()."/{$skin}.css", ['kct-style'], _S_VERSION);
  wp_enqueue_style('kct-overrides', get_stylesheet_directory_uri().'/dynamic-styles.php', ['kct-skin'], _S_VERSION);
  add_filter('body_class', fn($c) => array_merge($c, ["skin-$skin"]));
  ```
- Editor: `add_editor_style("{$skin}.css")` + `admin_body_class` s `skin-$skin`, aby
  náhled bloků v editoru odpovídal zvolenému skinu.

### Editovatelné hodnoty bez inline (primary_color a další zůstávají editovatelné)

Skin dodává jen **default** tokeny (v souboru). Customizer smí tyto tokeny **přepsat**
(dnešní `primary_color` zůstává, lze vystavit i další). Aby to **nebylo inline** a přitom
zůstalo živě editovatelné:

- `kct_dynamic_styles()` (inline `<style>` v `wp_head`/`admin_head`) se **odstraní**.
- `themes/kct/dynamic-styles.php` se **ponechá a přepoužije**: nastavuje `Content-Type: text/css`
  a vypisuje **pouze tokeny, které admin změnil** (prázdné = nic). Enqueue jako regulérní
  **stylesheet `<link>`** (handle `kct-overrides`) **za** skinem.
- Kaskáda: `skin default` → `admin override`. Když je token v Customizeru nastaven, vyhrává.
  Když ne, platí default ze skinu. Žádný inline `<style>`.

## 3. Token contract

Deklarace + fallbacky v `core/_variables.scss`; default hodnoty v každém skinu;
volitelný override přes Customizer (`kct-overrides`). Trikolóra je **fixní** (sdílená).

| Token | photo (1a) | magazine (1b) | cards (1c) |
|---|---|---|---|
| `--page-bg` | `#dfe3e8` | `#e4e2db` | `#dfe3e8` |
| `--surface` | `#ffffff` | `#fbfaf6` | `#ffffff` |
| `--surface-alt` | `#f4f6f9` | `#f1efe8` | `#f4f6f9` |
| `--surface-invert` | `#0d1926` | `#141311` | `#0d1926` |
| `--text` | `#16202b` | `#1b1b17` | `#16202b` |
| `--text-muted` | `#7b8492` | `#6a655b` | `#7b8492` |
| `--text-faint` | `#9aa4b1` | `#9c968a` | `#9aa4b1` |
| `--primary` | `#1466B0` | `#009640` | `#1466B0` |
| `--primary-contrast` | `#ffffff` | `#ffffff` | `#ffffff` |
| `--hero-eyebrow` | `#8fd0ff` | `#7fe0a3` | `#ffffff` |
| `--line` | `#eef1f5` | `#e6e3da` | `#eef1f5` |
| `--radius-container` | `20px` | `6px` | `20px` |
| `--radius-card` | `16px` | `4px` | `16px` |
| `--btn-radius` | `999px` | `3px` | `999px` |
| `--chip-radius` | `999px` | `3px` | `999px` |
| `--shadow-card` | `0 22px 46px -30px rgba(11,22,34,.5)` | `0 0 0 1px var(--line)` | `0 20px 50px -34px rgba(11,22,34,.5)` |
| `--hero-size` | `88px` | `100px` | `80px` |
| `--hero-align` | `left` | `left` | `center` |
| `--h2-size` | `44px` | `40px` | `42px` |
| `--members-bg` | `#f4f6f9` | `#12261a` | `#0f2233` |
| `--members-text` | `#16202b` | `#e4ede7` | `#ffffff` |
| `--members-accent` | `#009640` | `#7fe0a3` | `#009640` |

Sdílené (v core, ne v tokenech skinu):
`--font-head:'Oswald'`, `--font-body:'Plus Jakarta Sans'`,
`--kct-red:#E4032E`, `--kct-yellow:#FFCC00`, `--kct-green:#009640`.

Tokeny vystavené v Customizeru jako editovatelné (override): minimálně `--primary`
(dnešní `primary_color`); rozsah dalších vystavených tokenů potvrdit při implementaci.

## 4. Co doplnit do bloků (obsah beze změny, jen hooky/sloty)

Cíl: každý blok emituje stabilní, sémantické class-hooky, aby šel nastylovat
napříč skiny bez zásahu do markupu v jednotlivých skinech.

1. **Sdílená `.section-head`** (nová komponenta) — nad každou sekcí:
   `.section-head__eyebrow` (text), `.section-head__mark` (trikolóra), `.section-head__num`
   (číslo pro magazine), `.section-head__title`, `.section-head__link` („Celý kalendář →").
2. **`kct/cover` (Hero):** hooky `.block-cover`, `__eyebrow`, `__title`, `__lead`, `__actions`.
   Volitelné sloty: badge/staty (photo), index sekcí (magazine) — nepovinné, degradují se když chybí.
   `--hero-align` řídí left/center.
3. **`kct/events`:** hooky `.block-events`, `.event`, `.event__date-day`, `.event__date-month`,
   `.event__title`, `.event__meta`, `.event__tags`, `.chip`, `.event__link`.
   Doplnit **volitelný featured obrázek** akce (atribut) — zapne foto-karty ve skinu photo;
   bez obrázku degraduje na barevnou kartu. (Tvar výpisu není striktní.)
4. **`kct/news`** (dnes prázdné `news.scss`): postavit v core kartu s hooky
   `.block-news`, `.news-card`, `.news-card__media`/`img`, `.news-card__cat`, `.news-card__date`,
   `.news-card__title`, `.news-card__excerpt`, `.news-card__link`. Skin photo má volitelný
   asymetrický „featured" layout (přes modifikátor ve skinu).
5. **`kct/action` / členství:** hooky + tokeny `--members-bg/-text/-accent` řídí
   světlý (photo) / tmavě zelený (magazine) / navy (cards) panel.
6. **Tlačítka:** sjednotit `.btn`, `.btn--primary`, `.btn--ghost`, `.btn--on-dark`
   (rádius z `--btn-radius`).
7. **Trikolóra:** komponenta `.kct-tricolor` (řádek 3 barev), použitelná v hero,
   section-head, footeru.

## 5. Nefunkční požadavky

- **Výkon:** 1 skin CSS soubor na stránku (+ malý override), cachovatelné; core duplikován
  do 3 výstupů (cena zanedbatelná).
- **Udržitelnost:** 1 zdroj pravdy pro strukturu (`core/`); nový skin = nový soubor v `skins/`
  s kompletní sadou tokenů dle `_contract.scss` + volitelné overrides.
- **Přístupnost:** kontrast textu vůči surface/invert ověřit pro každý skin (WCAG AA).
- **Fallbacky:** `_variables.scss` má default hodnoty, aby core fungoval i bez skinu.

## 6. Mimo rozsah

- Změny obsahu, textů, struktury stránek.
- Nová homepage šablona (`front-page.php`) — homepage zůstává složená z bloků.
- Per-stránkový výběr skinu (řešeno globálně přes Customizer).

## 7. Rozhodnuto

- **Názvy:** slug `photo`/`magazine`/`cards`, popisky **Obrazový / Časopisový / Kartový**.
- **primary_color a další tokeny zůstávají editovatelné** přes Customizer; doručení
  override přes linkovaný `dynamic-styles.php` (ne inline).
