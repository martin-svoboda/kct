# Automatické sdílení příspěvků na Facebook

**Datum:** 2026-08-05
**Rozsah:** jeden web v multisite (pilot), post types `post` a `event`
**Priorita:** funkční sdílení bez ruční práce redaktora + slušně vypadající náhled odkazu

## 1. Cíl

Po publikaci aktuality nebo události se automaticky odešle příspěvek na Facebook
stránku odboru. Redaktor může u konkrétního příspěvku sdílení vypnout nebo přepsat
text. Stav odeslání (úspěch / chyba) je vidět přímo v editoru.

Součástí je i doplnění Open Graph tagů — web žádné nemá, takže by Facebook u sdílených
odkazů skládal náhledovou kartu bez obrázku a s náhodným textem.

### Co je mimo rozsah

Plánování příspěvků na později, statistiky dosahu, Instagram, sdílení do skupin,
opětovné odeslání při editaci již publikovaného článku, hromadné sdílení staršího
obsahu. Nasazení na další weby v multisite není součástí implementace — díky
per-site nastavení jde jen o vyplnění hodnot na dalším webu.

## 2. Předpoklady a omezení

**Facebook API umí publikovat pouze na stránky (Pages), ne na osobní profily.**
Uživatel musí být administrátorem cílové stránky.

Potřebné jednorázové kroky mimo kód (dělá Martin, ne implementace):

1. Vytvořit aplikaci na developers.facebook.com (typ Business).
2. Vygenerovat uživatelský token s oprávněními `pages_manage_posts`
   a `pages_read_engagement`, směnit ho za dlouhodobý (60 dní) a z něj získat
   **page access token** — ten už nevyprší, dokud se nezmění heslo účtu nebo se
   oprávnění neodvolá.
3. Zjistit Page ID cílové stránky.

Dokud appku používá jen její vlastní administrátor na vlastní stránce, **není potřeba
App Review**. Až se bude sdílení rozjíždět pro odbory s vlastními FB stránkami a
vlastními správci, App Review a ověření firmy potřeba bude — to je samostatný projekt,
který je dobré mít na paměti při rozhodování o pilotu.

**Verze Graph API se v klientovi napevno zapíše** (konstanta) a při implementaci se
ověří, která je aktuální. Meta verze vyřazuje zhruba po dvou letech.

**Konfigurace je per-site a čte ji jediná třída `Credentials`.** Page ID a token se
ukládají do nastavení pluginu (`kct_options`), ale konstanty `KCT_FB_PAGE_ID`
a `KCT_FB_PAGE_TOKEN` ve `wp-config.php` mají přednost — token pak vůbec neleží
v databázi ani v zálohách. Je-li konstanta definovaná, pole v nastavení se zobrazí
jen jako informace, odkud hodnota přichází, a dříve uložená hodnota se z databáze
smaže.

**Odeslání chrání zámek proti souběhu.** Mezi kontrolou „už odesláno?" a zápisem
výsledku leží celé HTTP volání Graph API (timeout 20 s), takže dva souběžné běhy
(dvě spuštění wp-cronu nad toutéž událostí, cron a ruční odeslání) by bez zámku
obojí prošly kontrolou a na zdi by vznikl duplicitní příspěvek. Zámek drží řádek
v tabulce options (`kct_fb_sending_{post-id}`) zapsaný prostým `INSERT`, takže
o vítěze rozhodne unikátní index; po pěti minutách smí zámek převzít další běh, aby
spadlý běh neblokoval odeslání navždy.

**Testovat nejde lokálně.** Facebook si musí umět stáhnout sdílený odkaz, aby
vygeneroval náhled. Ostrý test proběhne na produkci nebo veřejně dostupném stagingu;
na ddev je použitelný jen `--dry-run` režim CLI příkazu.

## 3. Architektura

Nové soubory v `wp-content/plugins/kct`:

```
src/Features/FacebookShare.php    # hooky, plánování, orchestrace, metabox
src/Features/OpenGraph.php        # OG tagy do wp_head
src/Facebook/Credentials.php      # čtení konfigurace (nastavení + konstanty)
src/Facebook/GraphClient.php      # HTTP klient Graph API
src/Facebook/MessageComposer.php  # složení textu z článku / události
src/Facebook/ShareState.php       # čtení a zápis stavu do post meta + zámek
src/Facebook/ShareMetabox.php     # výpis stavu odeslání v editoru
```

Obě features se přidají do konstruktoru `src/Managers/FeaturesManager.php`, kontejner
(PHP-DI, `config.php`) je sestaví sám. Žádná změna v `config.php` není potřeba —
třídy nemají konstruktorové parametry, které by kontejner neuměl vyřešit.

**Hranice odpovědnosti:**

- `Credentials` — jediné místo, které zná klíče nastavení a konstanty ve
  `wp-config.php`. Vrací Page ID, token, výchozí stav přepínače a výchozí OG
  obrázek. Žádné HTTP, žádné hooky.
- `GraphClient` — jen HTTP. Zná URL, verzi API a tvar odpovědi Graph API. Nezná WP
  hooky ani post types. Metody: `publish( string $page_id, string $token, string $message, ?string $link ): array`
  a `verify( string $token ): array`. Používá `wp_remote_post()` / `wp_remote_get()`.
- `MessageComposer` — dostane `WP_Post` a vrátí string. Žádné HTTP, žádný zápis do DB.
- `ShareState` — jediné místo, které zná názvy meta klíčů. Drží i zámek proti
  souběžnému odeslání (`claim()`, `release()`, `is_locked()`) — zámek je stav
  odesílání jako každý jiný.
- `ShareMetabox` — vykreslí stav odeslání v editoru. Jen čte `ShareState`.
- `FacebookShare` — lepidlo: hooky, plánování cronu, metabox, obsluha chyb.

**`FacebookShare` je jediný orchestrátor.** Pravidla sdílení — podmínky, zámek,
obsluha selhání a opakování — mají jediné místo a nikdo jiný si je nekopíruje.
Týká se to i WP-CLI: příkaz `wp kct fb_share` mimo `--dry-run` volá
`FacebookShare::share()` a jen vypisuje, co feature vyhodnotila. Vlastní cestu má
jen `--force` (musí umět odeslat i příspěvek, který už odeslaný je), ale i ta
zabírá stejný zámek přes `ShareState` a po chybě volá `FacebookShare::handle_failure()`.

Výsledek pokusu se nevrací návratovou hodnotou — `share()` je obsluha cron události
a nemá komu ho vracet. Kdo potřebuje vědět, co se stalo (tlačítko „Zkusit znovu",
WP-CLI), pořídí si před pokusem `snapshot()` a po něm zavolá `outcome()`, které
rozliší **odesláno / selhalo / neodesláno kvůli podmínce** a složí hlášku pro
člověka.

Post types (`PostPostType`, `EventPostType`) se **nemění** — metabox registruje feature
sama, takže o Facebooku nevědí nic.

## 4. Tok dat

### Publikace

1. `transition_post_status` → nový stav `publish`.
2. Podmínky pro naplánování (všechny musí platit):
   - předchozí stav **není** `publish` — editace publikovaného článku nespustí nové sdílení
   - post type je `post` nebo `event`
   - příspěvek není chráněný heslem
   - příspěvek ještě nemá `kct_fb_post_id`
   - v nastavení je vyplněné Page ID i token

   Přepínač `kct_fb_share` se tady **nekontroluje**: `transition_post_status` běží
   dřív, než se uloží metabox, takže by hodnota byla ještě z minulého uložení.
   Definitivní kontrola je až v `share()`, kde už je meta uložená.
3. `wp_schedule_single_event( time() + 60, 'kct_facebook_share', [ $post_id ] )`.
   Minutové zpoždění drží editor svižný a dá WordPressu čas douložit metadata
   a náhledový obrázek.
4. Handler zkontroluje **celé podmínky znovu** — argument cron události pochází
   z options, ne z ověřeného requestu, a příspěvek mezitím mohl změnit typ, stav
   i heslo. Navíc se tady kontroluje přepínač `kct_fb_share`: chybí-li meta úplně
   (příspěvek vznikl mimo editor, třeba importem), rozhoduje globální nastavení
   „Sdílet automaticky". Aby přepínač u nového příspěvku odpovídal globálnímu
   nastavení, má položka v metaboxu `default` odvozený z něj — knihovna
   wpify/custom-fields hodnotu zapisuje při každém uložení příspěvku, takže bez
   `default` by se od prvního uložení sdílelo podle napevno vypnutého přepínače.
5. Handler zabere zámek, složí text přes `MessageComposer` a zavolá
   `GraphClient::publish()`.
6. Výsledek:
   - **úspěch** → `kct_fb_post_id`, `kct_fb_shared_at`, smaže se případná chyba
     i upozornění na neplatný token
   - **chyba** → `kct_fb_error` (kód + text), naplánuje se opakování
7. Zámek se uvolní v `finally`, ať pokus dopadl jakkoli.

### Opakování při chybě

Tři pokusy s odstupem 5 min → 30 min → 2 h, počítadlo v `kct_fb_attempts`. Po třetím
neúspěchu se to vzdá, chyba zůstane viditelná v editoru a redaktor může spustit
odeslání ručně tlačítkem.

Dvě výjimky se **neopakují**:

- **Chyba tokenu** (Graph API kód 190) — dokud se token nevymění, nemá to smysl.
  Místo toho se rovnou zobrazí upozornění v administraci.
- **Chyba spojení** (timeout, nedostupná síť) — `WP_Error` nerozliší timeout, při
  kterém příspěvek na zdi vzniknout mohl, od nedostupné sítě, kdy nevznikl.
  Duplicitní příspěvek na veřejné zdi je horší než neodeslaný, o kterém je
  v editoru vidět proč. Metabox u této chyby upozorní, že se odeslání samo
  neopakuje a že se má redaktor před opakováním podívat na zeď.

Opakuje se tedy jen to, co má šanci projít: běžné chyby API.

## 5. Skládání textu

**Aktualita** (`post`, bez příznaku `short_news`):

```
{titulek}

{perex zkrácený na ~300 znaků}
```

Odkaz se předává API zvlášť v parametru `link`, Facebook z něj vyrobí náhledovou kartu.

**Krátká aktualita** (`post` s `short_news`):

Krátké aktuality nemají proklik na detail — celý obsah se zobrazuje ve výpise a web
na detailní stránku nikde neodkazuje. Posílá se proto **celý obsah jako text, bez odkazu**
(parametr `link` se vynechá). Obsah se jen zbaví HTML.

Text se u krátkých aktualit **nekrátí** — bez odkazu by useknutý konec nebyl kde
dočíst. Jediný strop je tvrdá pojistka proti limitu Graph API, ne redakční limit.

**Událost** (`event`):

```
{titulek}
Kdy: sobota 12. 7. 2026, 9:00
Kde: {místo}

{perex}
```

- datum: `start.date`, fallback `date`; formátováno česky přes `wp_date()`
- čas: `start.time`, řádek se vynechá, pokud chybí
- místo: `start.place`, fallback `place`; řádek se vynechá, pokud chybí

Redaktorem zadaný vlastní text má vždy přednost před vygenerovaným.

## 6. Uživatelské rozhraní

### Metabox „Facebook"

Postranní panel u `post` i `event`, registrovaný přes `CustomFields::create_metabox()`
(stejně jako stávající metabox `kct_page_layout`).

| pole | typ | chování |
|---|---|---|
| `kct_fb_share` | toggle | „Sdílet na Facebook". Výchozí stav (`default`) podle globálního nastavení. |
| `kct_fb_message` | textarea | Vlastní text. Prázdné = použije se vygenerovaný. |

Stav odeslání **není pole knihovny**, ale samostatný nativní metabox „Facebook —
stav odeslání" (`src/Facebook/ShareMetabox.php`). Pole typu `html` se v knihovně
wpify/custom-fields skládá při registraci, kdy ještě není známý editovaný
příspěvek, takže by nemělo co vypsat. Vlastní metabox se zaregistruje až
v `add_meta_boxes`, a to jen tehdy, když je co ukázat — po publikaci čas odeslání
a odkaz na příspěvek na Facebooku, po chybě text chyby a tlačítko „Zkusit znovu".

Všechny klíče sdílení (stavové i obě pole redaktora) jsou přes filtr
`is_protected_meta` označené jako chráněné: typ „akce" má v supports
`custom-fields`, takže by je jinak šlo přepsat v boxu Vlastní pole.

Pole pro vlastní text má jen popisek, ne živý náhled vygenerovaného textu. Metabox
se registruje dřív, než je známý editovaný příspěvek, takže by náhled vyžadoval
vlastní React pole — na to, co přináší, je to moc práce. Redaktor si vygenerovaný
text ověří tím, že pole nechá prázdné.

Tlačítko „Zkusit znovu" použije stávající pattern `kct-action` z `Settings.php`:
odkaz s query parametry `kct-action=fb_retry`, `post={id}` a nonce; feature ho
odchytí, vynuluje počítadlo pokusů, zruší čekající cron událost a zkusí odeslat
rovnou v tomto requestu — redaktor má výsledek vidět hned.

Výsledek se hlásí stejně jako u tlačítka „Ověřit připojení": uloží se do transientu
vázaného na uživatele a po přesměrování zpět do editoru se vypíše jako upozornění
v administraci. Rozlišuje se **odesláno** (zelené), **znovu selhalo** i s důvodem
od Facebooku (červené) a **neodesláno kvůli podmínce** — vypnutý přepínač, koncept,
heslo, chybějící nastavení, právě probíhající jiné odeslání (žluté). Bez toho by
tlačítko umělo tiše neudělat nic.

### Nastavení

Nová sekce v `src/Settings.php` (options page KČT):

| pole | poznámka |
|---|---|
| Page ID | přebito konstantou `KCT_FB_PAGE_ID`, pokud je definovaná |
| Page access token | typ password; přebito konstantou `KCT_FB_PAGE_TOKEN` |
| Sdílet automaticky | výchozí stav přepínače v editoru |
| Výchozí OG obrázek | fallback pro příspěvky bez náhledového obrázku |
| Ověřit připojení | tlačítko → `GET /me`, vypíše název připojené stránky nebo chybu |

Je-li definovaná konstanta, pole v nastavení se zobrazí jen pro čtení s poznámkou,
že hodnota přichází z `wp-config.php`.

## 7. Open Graph tagy

`src/Features/OpenGraph.php`, hook `wp_head` (priorita 5). Vykresluje:

`og:title`, `og:description`, `og:url`, `og:type`, `og:image` (+ `og:image:width`
a `og:image:height`), `og:site_name`, `og:locale` z `get_locale()`,
`twitter:card` = `summary_large_image`.

- obrázek: náhledový obrázek příspěvku ve velikosti `full`, fallback na výchozí
  obrázek z nastavení. Registrovanou velikost (`large`) by WordPress prohnal přes
  `image_constrain_size_for_editor()` a omezil na `$content_width` tématu (640 px)
  — pod doporučenými 1200×630 pro Facebook, který kartu skládá z deklarovaných
  rozměrů ještě před stažením obrázku
- popis: perex, fallback na zkrácený obsah
- na homepage se použije název a popis webu, `og:url` je `home_url( '/' )`
- na archivech (rubrika, štítek, jiná taxonomie, archiv typu příspěvku, autor) se
  `og:title` bere z `get_the_archive_title()` (zbaveného HTML) a `og:description`
  z `get_the_archive_description()`, s fallbackem na název a popis webu; `og:url`
  míří na skutečnou adresu archivu, ne na homepage — Facebook bere `og:url` jako
  kanonickou adresu sdíleného odkazu, takže by jinak sdílení archivu přiřadil
  homepage
- na vyhledávání je `og:title` „Výsledky hledání: {dotaz}“ a `og:url` adresa
  výsledků hledání

Feature se sama vypne, pokud je aktivní Yoast SEO nebo Rank Math (kontrola existence
tříd `WPSEO_Options` / `RankMath`), aby tagy nebyly v HTML dvakrát.

## 8. Meta klíče

| klíč | obsah |
|---|---|
| `kct_fb_share` | bool — sdílet tento příspěvek |
| `kct_fb_message` | string — vlastní text redaktora |
| `kct_fb_post_id` | string — ID příspěvku na FB (`{page-id}_{post-id}`) |
| `kct_fb_shared_at` | int — timestamp odeslání |
| `kct_fb_error` | array — kód a text poslední chyby |
| `kct_fb_attempts` | int — počet neúspěšných pokusů |

Klíče jsou bez podtržítka kvůli čitelnosti ve `wp post meta list`. Stavové klíče se
registrují přes `register_post_meta()` se `show_in_rest => false` a `auth_callback`
na právo `edit_post` konkrétního příspěvku, aby se nedostaly do REST API
a needitoval je kdokoli. `kct_fb_share` a `kct_fb_message` registruje knihovna
wpify/custom-fields sama (včetně výchozí hodnoty přepínače).

Mimo post meta plugin drží ještě option `kct_fb_token_error` (text chyby tokenu pro
upozornění v administraci), krátkodobé transienty s výsledky tlačítek
(`kct_fb_verify_result_{user}`, `kct_fb_retry_result_{user}`) a zámky odesílání
(`kct_fb_sending_{post-id}`).

### Úklid při deaktivaci a odinstalaci

Deaktivace zruší naplánované události (`kct_facebook_share`, `kct_update_events`)
a smaže provozní data, která bez běžícího pluginu nemá kdo uklidit — option
s chybou tokenu, transienty a zbylé zámky. Nastavení ani stav odeslání příspěvků
se nemažou: deaktivace bývá dočasná. Teprve odinstalace smaže i stavová meta všech
příspěvků a nastavení Facebooku z `kct_options`. Na multisite je odinstalace vždy
síťová akce a síťová může být i deaktivace, takže úklid v obou případech projde
weby sítě jeden po druhém (`switch_to_blog()`) — options i post meta jsou per-web.

## 9. Ověření funkčnosti

V projektu není PHPUnit ani jiná testovací infrastruktura, ověření proto stojí na
WP-CLI příkazech (rozšíření `src/CLI.php`) a ručním testu:

```
wp kct fb_check                      # ověří token, vypíše název připojené stránky
wp kct fb_share <post_id> --dry-run  # vypíše text, který by se odeslal, nic neodešle
wp kct fb_share <post_id>            # vypíše text a odešle přes FacebookShare::share()
wp kct fb_share <post_id> --force    # odešle i příspěvek, který už odeslaný je
```

Příkaz vždy napíše, co se stalo: `Success:` u odeslání, `Error:` s důvodem od
Facebooku u chyby a `Error: Neodesláno: …` s vysvětlením, když se kvůli některé
podmínce neodesílalo. Neúspěch končí nenulovým návratovým kódem.

Ruční testovací scénář na stagingu / produkci:

1. `wp kct fb_check` → vrátí název stránky.
2. Publikovat testovací aktualitu s náhledovým obrázkem → do minuty příspěvek na FB
   s obrázkovou kartou, v editoru čas odeslání a odkaz.
3. Publikovat krátkou aktualitu → text bez odkazu.
4. Publikovat událost s datem a místem → řádky „Kdy" a „Kde" v textu.
5. Upravit a znovu uložit publikovaný článek → **nic se neodešle**.
6. Dočasně rozbít token → chyba v editoru, upozornění v administraci, žádné opakování.
7. Zkontrolovat OG tagy přes debugger na developers.facebook.com/tools/debug.

## 10. Rizika

- **Expirace tokenu.** Page token sice nevyprší sám, ale zneplatní ho změna hesla
  účtu, odebrání práv ke stránce nebo odvolání oprávnění. Proto zvláštní obsluha
  chyby 190 s upozorněním v administraci — jinak by sdílení tiše přestalo fungovat.
- **Změny Graph API.** Verze se vyřazují po ~2 letech, jednou za čas bude potřeba
  zvednout konstantu a ověřit funkčnost.
- **WP-Cron.** Na webu s malou návštěvností se naplánovaná událost spustí až při
  dalším requestu. Zpoždění je řádově minuty, což je pro tento účel v pořádku;
  pokud by vadilo, řešením je systémový cron.
- **App Review při rozšíření na síť.** Viz sekce 2 — u pilotu se neřeší, u rolloutu
  je to týdny administrativy navíc.
