# Zprovoznění nápovědy na napoveda.sokct.cz — postup

**Datum:** 2026-09-02

**Navazuje na:** [`2026-09-01-uzivatelska-dokumentace.md`](2026-09-01-uzivatelska-dokumentace.md) (Task 14)

Nápověda se nasazuje na **GitHub Pages** s vlastní doménou `napoveda.sokct.cz`.
Sestavení i nasazení obstarává `.github/workflows/docs.yml`, spouštěné změnou
v `docs/user/**` na větvi `main`.

---

## Co je hotové (nic z toho už dělat nemusíš)

- `docs/user/astro.config.mjs` má `site: 'https://napoveda.sokct.cz'` — díky
  tomu se generuje mapa webu.
- `.github/workflows/docs.yml` staví dokumentaci na Node 22, validuje interní
  odkazy a nasazuje na Pages ve dvou úlohách (`build` → `deploy`).
- Workflow má oprávnění `pages: write` a `id-token: write`, prostředí
  `github-pages` a `concurrency: pages`, aby si dvě nasazení nepřepisovala
  výsledek.
- `deploy.yml` vylučuje celou složku `docs` z produkce i z instalačního ZIPu,
  takže se dokumentace nikdy nedostane do pluginu.
- `docs/user/public/CNAME` nese `napoveda.sokct.cz`. Astro kopíruje obsah
  `public/` do kořene sestaveného webu, takže vlastní doména jde na Pages
  přímo s artefaktem a nestojí jen na políčku v nastavení.

## Co je potřeba udělat

**Pořadí není libovolné.** Pages musí být zapnuté dřív, než workflow poprvé
proběhne — jinak krok nasazení skončí chybou `Get Pages site failed`.

---

### Krok 1 — Zapnout GitHub Pages (dřív než cokoli pushneš)

1. `https://github.com/martin-svoboda/kct/settings/pages`
2. **Build and deployment → Source:** přepnout na **GitHub Actions**.
   (Ne „Deploy from a branch" — ta varianta by chtěla větev `gh-pages`, kterou
   nemáme a nechceme.)
3. Zatím nic dalšího nevyplňuj, vlastní doména přijde v kroku 3.

### Krok 2 — DNS záznam

U správce DNS domény `sokct.cz` založit:

```
typ:    CNAME
název:  napoveda
cíl:    martin-svoboda.github.io
TTL:    3600
```

Poznámky:

- Cíl je **uživatelské jméno**, ne název repozitáře.
- **Bez koncové tečky.** Tečka na konci je zápis ze zónového souboru
  (`martin-svoboda.github.io.`) a znamená plně kvalifikované jméno. Webová
  rozhraní správců DNS si ji doplňují sama a ručně zadanou ji odmítají jako
  neplatnou doménu. Zadávat tedy `martin-svoboda.github.io`. Ve výstupu
  `dig` se ta tečka objeví, i když jsi ji nezadal — tak to má být.
- Nezakládej k tomu žádný `A` záznam. Ty se používají jen pro doménu druhého
  řádu (`sokct.cz`), pro subdoménu je správně CNAME.
- **Pozor na wildcard.** Pokud má `sokct.cz` v DNS `*.sokct.cz` mířící na
  server s WordPressem (kvůli multisite), konkrétní CNAME pro `napoveda` má
  přednost — konkrétnější záznam vyhrává. Ověř si ale, že tam wildcard
  skutečně není nastavený jako `A` přímo na `napoveda`.

Než budeš pokračovat, počkej na propsání a ověř:

```bash
dig napoveda.sokct.cz CNAME +short
# očekávaný výstup: martin-svoboda.github.io.
# (tečka na konci je v odpovědi vždy, i když se zadávala adresa bez ní)
```

### Krok 3 — Vlastní doména v nastavení Pages

1. Zpátky na `https://github.com/martin-svoboda/kct/settings/pages`
2. **Custom domain:** vyplnit `napoveda.sokct.cz` a dát **Save**.
3. GitHub ověří DNS — u zeleného zaškrtnutí je hotovo. Když hlásí chybu,
   nejspíš se ještě nepropsal CNAME; počkat a dát **Check again**.

   Díky souboru `docs/user/public/CNAME` se doména do nastavení propíše i sama
   při prvním nasazení. Tenhle krok je tedy pojistka — pokud už je políčko
   vyplněné, není co dělat.

   **Jak poznat, že doména ještě nefunguje:** web běží na
   `martin-svoboda.github.io/kct/`, stránky se načtou bez stylů a odkazy
   nikam nevedou. Není to chyba buildu — web je postavený pro kořen domény, a
   pod cestou `/kct/` proto soubory `/_astro/…` nesedí. Jakmile doména naběhne,
   spraví se to samo.
4. **Enforce HTTPS** zaškrtnout, jakmile to jde. Certifikát od Let's Encrypt
   se vystavuje automaticky a může to trvat od pár minut do 24 hodin — dokud
   není hotový, je to políčko zašedlé. To je normální, není to chyba.

### Krok 4 — Commit a push

Verzování si děláš sám. Do commitu patří:

```
docs/user/                        celý nový projekt dokumentace
.github/workflows/docs.yml        nový workflow
.github/workflows/deploy.yml      doplněný --exclude="docs"
.gitignore                        vzory pro docs/user
CLAUDE.md                         pravidlo o souběžné aktualizaci dokumentace
package.json                      skripty docs, docs:build, docs:preview
kct.php                           oprava kct_theme_is_active()
src/Plugin.php                    delegace na tu opravenou funkci
src/Repositories/DbEventRepository.php   oprava duplikace akcí
docs/superpowers/                 spec, plán a tenhle runbook
```

Zkontroluj, že se **nedostane do commitu** `docs/user/node_modules/`,
`docs/user/dist/` ani `docs/user/.astro/` — `.gitignore` je vylučuje, ověřit
se to dá takhle:

```bash
cd wp-content/plugins/kct
git status --porcelain docs/user | grep -E "node_modules|/dist/|\.astro" && echo "POZOR: něco z toho není ignorované" || echo "OK — nic nechtěného"
```

Push do `main` spustí workflow, protože mění `docs/user/**` i samotný
`.github/workflows/docs.yml`.

### Krok 5 — Zkontrolovat běh

1. `https://github.com/martin-svoboda/kct/actions` → workflow **Dokumentace**.
2. Úloha `build` musí projít krokem **Build docs** — ten zároveň validuje
   interní odkazy. Když spadne na `Links validation failed`, ve výpisu je
   přesně který odkaz a v jakém souboru.
3. Úloha `deploy` na konci vypíše adresu nasazení.

### Krok 6 — Ověřit výsledek

```bash
curl -sI https://napoveda.sokct.cz/ | head -1
# očekávaný výstup: HTTP/2 200

curl -s https://napoveda.sokct.cz/ | grep -o '<title>[^<]*</title>'
# očekávaný výstup: <title>Nápověda k šabloně KČT</title>

curl -sI https://napoveda.sokct.cz/spravce/zbytecne-pluginy/ | head -1
# očekávaný výstup: HTTP/2 200 — ověří, že fungují i vnořené adresy
```

V prohlížeči pak zkontrolovat, že **funguje vyhledávání** (ikona lupy nahoře).
To je jediná část, která se pozná až na sestaveném webu — v `npm run docs` je
vyhledávací index prázdný.

---

## Když něco nesedí

| Příznak | Příčina a řešení |
| --- | --- |
| `Get Pages site failed` v úloze deploy | Pages nejsou zapnuté, nebo je Source nastavený na „Deploy from a branch". Krok 1. |
| Web běží na `martin-svoboda.github.io/kct/`, ne na vlastní doméně | Custom domain není uložená v nastavení Pages. Krok 3. |
| Po nasazení se vlastní doména z nastavení vyresetovala | Ověřit, že existuje `docs/user/public/CNAME` s jediným řádkem `napoveda.sokct.cz` a že se dostal do sestavení (`cat docs/user/dist/CNAME` po `npm run docs:build`). |
| Stránky se načtou bez stylů a odkazy nikam nevedou | Web běží na `martin-svoboda.github.io/kct/` místo na vlastní doméně — vlastní doména není aktivní. Ověřit: `curl -sI http://napoveda.sokct.cz/` vrací od GitHubu 404, když doménu nezná, a `curl -sv https://napoveda.sokct.cz/` hlásí, že certifikát na jméno nesedí. Krok 3. |
| Stránky se načtou, ale bez stylů | Chybný `base`. Web běží v kořeni domény, takže se `base` nemá nastavovat vůbec — v `astro.config.mjs` je správně jen `site`. |
| `napoveda.sokct.cz` ukazuje WordPress | DNS míří na server s multisite. Multisite má `SUBDOMAIN_INSTALL` a `DOMAIN_CURRENT_SITE` z `HTTP_HOST`, takže si subdoménu vezme. Ověřit CNAME z kroku 2. |
| Certifikát neplatí, `Enforce HTTPS` nejde zaškrtnout | Vystavení certifikátu trvá až 24 hodin. Když to trvá déle, v nastavení doménu smazat, uložit, znovu vyplnit a uložit — tím se vystavení nastartuje znovu. |
| Build spadne na `Links validation failed` | Rozbitý interní odkaz. Ve výpisu je soubor i adresa. Lokálně se to najde přes `npm run docs:build`. |

---

## Provoz

**Publikace změny** = úprava v `docs/user/` a push do `main`. Workflow se
spustí sám, nasazení trvá jednotky minut. Žádný ruční krok navíc.

**Před pushem** se vyplatí ověřit lokálně, z kořene pluginu:

```bash
npm run docs           # dev server na http://localhost:4321
npm run docs:build     # totéž co dělá CI, včetně validace odkazů
npm run docs:preview   # náhled sestaveného webu — jediný režim s funkčním hledáním
```

**Pravidlo údržby** je v `CLAUDE.md`: mění-li se uživatelsky viditelné
chování, mění se ve stejném PR i `docs/user/`.

---

## Co ještě zbývá mimo tenhle postup

- **Prezentační miniweb** na `sablona.sokct.cz` je zatím jen na lokále (stránka
  „Šablona KČT", slug `uvod`, nastavená jako úvodní). Do produkce se překlápí
  ručně. Chybí v něm kontaktní e-mail a fotky.
- Na lokální subsite zůstávají výchozí zbytky WordPressu — stránka „Zkušební
  stránka" a příspěvek „Ahoj všichni!".
