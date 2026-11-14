# Endpoint aktualizací pluginu KČT

Metadata aktualizací pro weby, které si plugin KČT nainstalovaly samy.
Běží na `https://updates.sokct.cz`, mimo WordPress.

## Kde to na serveru leží

Subdoména je na hostingu vas-hosting (Centrum V2, Debian Bookworm) obyčejná
**složka vedle `www`**, obsluhuje ji **Apache** a vlastní konfiguraci
webserveru nemá:

```
/www/hosting/sokct.cz/
  www/                 ← web sokct.cz, nedotčený
  updates/             ← docroot updates.sokct.cz == obsah public/ z repozitáře
    .htaccess          ← routing, heslo na přehled, zákaz data/
    .htpasswd          ← mimo repozitář, zakládá se ručně
    data/              ← mimo repozitář: SQLite s evidencí instalací, cache GitHubu
```

`data/` je **uvnitř** docrootu schválně: složka vedle `www` by se sama stala
subdoménou (`data.sokct.cz`) a vystavila evidenci instalací. Přístup zvenčí
zakazuje `.htaccess`.

Certifikát je wildcard `*.sokct.cz` spravovaný panelem — pro tuhle subdoménu
se nic nevydává a `certbot certonly` se tu nespouští.

## Nasazení

`.github/workflows/updates-server.yml` — na `push` do `main` v cestě
`server/updates/**` nebo ručně. Před nasazením pouští `php -l` a `test.php`.

`rsync --delete` má dvě výjimky, bez kterých by každé nasazení smazalo
provozní data: `--exclude='/data/'` a `--exclude='.htpasswd'`.

Vlastnictví se nehádá, kopíruje se ze sousední `www`
(`chown -R --reference=…/www`).

Přehled změn v okně „Zobrazit detaily" se bere z **`readme.txt`** pluginu
v daném tagu (sekce `== Changelog ==`), ne z těla vydání na GitHubu. To se
skládá automaticky z názvů commitů, takže správce cizího webu v něm četl větu
psanou vývojářem sobě. Vydání starší než `readme.txt` ho nemají — pro ně zůstává
tělo vydání jako záloha.

Instalační ZIP se **neservíruje odsud**: `download_url` míří na přílohu
vydání na GitHubu. Výpadek tohoto endpointu tedy znamená jen to, že se na
webech neukáže nabídka aktualizace, ne přerušenou instalaci.

## Lokální běh

```bash
cd public && php -S 127.0.0.1:8099 index.php
curl -s 'http://127.0.0.1:8099/kct?installed_version=2.0.0'
```

`.htaccess` vestavěný server PHP ignoruje — routing i heslo se dají ověřit
až na serveru.

`index.php` slouží vestavěnému serveru i jako router, takže přes něj jdou
i dotazy na existující soubory. Proto v něm je `return false` pro
`PHP_SAPI === 'cli-server'` — bez něj by `prehled.php` lokálně skončil na 404.
Na Apachi se taková cesta k `index.php` nedostane, obslouží ji `.htaccess`.

Ze stejného důvodu se knihovny zavádějí `require_once`: při lokálním běhu je
`index.php` a `prehled.php` zavádějí ve stejném procesu. `config.php` naopak
`require` — vrací pole a `require_once` by při druhém zavedení vrátil `true`.

## Testy

```bash
cd public && php test.php
```

Testují čistou logiku v `lib.php` — parsování odpovědi GitHubu, hlaviček
pluginu a dotazu. Nic, co by sahalo na síť nebo databázi.

## Přehled instalací

`https://updates.sokct.cz/prehled.php`, za basic auth z `.htaccess`.
