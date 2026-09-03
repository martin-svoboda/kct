# Odkazy na nápovědu v administraci — návrh

**Datum:** 2026-09-03

**Navazuje na:** [`2026-09-01-uzivatelska-dokumentace-design.md`](2026-09-01-uzivatelska-dokumentace-design.md)

## Problém

Nápověda běží na `napoveda.sokct.cz`, ale v administraci na ni nic neodkazuje.
Uživatel se o ní musí dozvědět odjinud a pak si v ní najít místo, které řeší
právě tu obrazovku, na které stojí. Dokumentace, kterou je nutné hledat, se
nečte.

## Rozsah

**Je v rozsahu:** kontextové odkazy na obrazovkách, které přidává šablona —
Nastavení → KČT, výpis a editor Akcí, výpis a editor Odborů, panel Facebook u
příspěvků a akcí — a v Přizpůsobení u voleb šablony.

**Není v rozsahu:** obrazovky jádra WordPressu (Média, Menu, Widgety, Stránky,
Pluginy) a editor bloků. Odkaz u jednotlivého bloku by znamenal zásah do JS a
nové sestavení assetů; když se ukáže, že chybí, přidá se zvlášť.

## Dvojí podoba

Odkaz se objeví dvakrát, protože každá forma pokrývá jiného uživatele:

- **Vestavěná záložka „Nápověda"** vpravo nahoře. Idiomatické místo, kam
  WordPress patří, a unese i delší text. Nevýhodou je, že je schovaná za
  tlačítkem, které řada lidí nikdy nerozklikne.
- **Viditelný odkaz přímo v rozhraní** — v nastavení nad poli, v panelech
  metaboxů, v popisu sekce Přizpůsobení. Malý, nevtíravý, ale na očích právě
  ve chvíli, kdy uživatel neví, co má vyplnit.

## Architektura

Dvě nové třídy, každá s jednou odpovědností:

**`Kct\Help\DocsLinks`** — jediný zdroj pravdy o tom, kam se odkazuje. Drží
základní adresu a mapu „kontext → odkazy" a umí složit URL i hotovou značku
odkazu. Nezná WordPress hooky, takže se dá číst i použít samostatně.

**`Kct\Features\AdminHelp`** — věší se na `current_screen` a z mapy skládá
záložku Nápovědy pro danou obrazovku. Nic víc.

Viditelné odkazy si přidávají místa, kterých se týkají, protože jinam než do
vlastní definice polí je vložit nelze: `Settings`, `EventPostType`,
`FacebookShare` a `themes/kct/inc/customizer.php`. Všechna si adresu vyžádají
od `DocsLinks`, žádné natvrdo psané URL v kódu.

### Základní adresa

Konstanta `KCT_DOCS_URL` (výchozí `https://napoveda.sokct.cz`), přebitelná ve
`wp-config.php` a filtrem `kct_docs_url`. Bez toho by web provozovaný mimo síť
KČT odkazoval na cizí doménu, kterou jeho správce neovlivní.

### Mapa obrazovek

| Obrazovka (screen id) | Odkazy |
| --- | --- |
| `settings_page_kct_options` | První nastavení webu · Sdílení na Facebook · Bezpečnost |
| `edit-akce`, `akce` | Akce · Kalendář akcí (blok) |
| `edit-odbory`, `odbory` | Odbory |
| `edit-post`, `post` | Stránky a aktuality · Sdílení na Facebook |
| Přizpůsobení, sekce Vzhled šablony | Vzhled webu |

Odkazy vedou na konkrétní stránku, ne na rozcestník — smysl celé věci je
uspořit hledání.

## Ověření

Adresy nesmí odkazovat do prázdna. Kontrolou je porovnání cest z `DocsLinks`
proti sestavené dokumentaci v `docs/user/dist` — každá cesta musí mít svůj
`index.html`. Je to totéž, co pro dokumentaci dělá validátor odkazů, jen přes
hranici mezi pluginem a příručkou, kterou žádný z obou nástrojů nevidí.
