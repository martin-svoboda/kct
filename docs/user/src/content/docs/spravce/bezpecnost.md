---
title: Bezpečnost
description: Účty, hesla, pluginy z neznámých zdrojů a bezpečné uložení facebookového tokenu.
sidebar:
  order: 5
---

Po přečtení téhle stránky bude jasné, čím se dá web odboru nejsnáz ohrozit a
jaká opatření tomu předejdou.

Naprostá většina napadených WordPress webů nepadne kvůli chybě v jádře, ale
kvůli slabému heslu nebo kvůli pluginu, který na web nepatřil.

## Účty a hesla

- **Silné heslo.** Nejlépe to, které WordPress sám nabídne při zakládání účtu, a
  uložené ve správci hesel. Heslo použité i jinde na internetu je slabé bez
  ohledu na to, jak vypadá.
- **Dvoufaktorové ověření.** Kde je k dispozici, je vhodné ho zapnout — samotné
  heslo pak k přihlášení nestačí.
- **Jeden účet = jeden člověk.** Sdílený účet znamená, že se nedá zjistit, kdo
  co změnil, a při odchodu jednoho člověka je potřeba měnit heslo všem.
- **Odebírat účty lidí, kteří skončili.** Účet bývalého redaktora je otevřené
  dveře, o kterých nikdo neví. Přehled toho, co která role smí, je na stránce
  [Role uživatelů](/zaciname/role-uzivatelu/).
- **Účet správce jen pro správu.** Pro běžné psaní obsahu stačí redaktorská
  role.

## Pluginy z neznámých zdrojů

Instalovat je vhodné jen pluginy z oficiálního katalogu WordPressu, a i tam se
vyplatí kouknout na datum poslední aktualizace a počet instalací. Placené
pluginy stažené zdarma z pochybných webů („nulled“) obsahují upravený kód
prakticky vždy.

Než se něco nového nainstaluje, stojí za to ověřit v přehledu
[Pluginy, které nepotřebujete](/spravce/zbytecne-pluginy/), jestli to šablona
neumí sama.

## Facebookový token

Ke sdílení na Facebook slouží dlouhodobý přístupový token stránky. Je to
plnohodnotný klíč: kdo ho má, může za stránku publikovat.

Zadat se dá dvěma způsoby a nejsou rovnocenné:

- **Do pole v Nastavení → KČT.** Funguje, ale token pak leží v databázové
  option, která se načítá při každém požadavku na web, a je součástí každé zálohy
  databáze. Kdokoli se dostane k záloze, dostane se i k tokenu.
- **Do konstanty `KCT_FB_PAGE_TOKEN` v souboru `wp-config.php`.** Bezpečnější
  varianta a doporučený postup. Token je pak mimo databázi, tedy i mimo její
  zálohy a exporty.

Když je konstanta nastavená, přebíjí uloženou hodnotu a nastavení místo pole
vypíše informaci, že hodnota přichází z `wp-config.php`; dříve uložený token se
přitom z databáze odstraní. Na samostatném webu se k souboru `wp-config.php`
dostane správce sám — přes FTP nebo souborového správce hostingu. Je-li web
součástí spravované sítě, je soubor společný pro všechny weby sítě a konstanta
by v něm platila pro všechny najednou, takže její nastavení je potřeba domluvit
s provozovatelem. Jak sdílení funguje dál, popisuje stránka
[Sdílení na Facebook](/funkce/sdileni-na-facebook/).

Pokud se token někdy objevil ve sdíleném dokumentu, e-mailu nebo chatu, je
namístě vygenerovat v Meta aplikaci nový — starý tím okamžitě přestane platit.
