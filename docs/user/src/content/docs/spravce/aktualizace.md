---
title: Aktualizace šablony
description: Jak se plugin se šablonou KČT nainstaluje a aktualizuje na samostatném webu i na spravované síti a kde se zjistí běžící verze.
sidebar:
  order: 3
---

Po přečtení téhle stránky bude jasné, jak se šablona na web dostane, kdo se
stará o její aktualizace a kde se zjistí, jaká verze na webu běží.

## Plugin nese šablonu vzhledu uvnitř sebe

Šablona vzhledu se nedistribuuje zvlášť — je součástí pluginu KČT, který ji po
aktivaci přidá do nabídky šablon WordPressu. Aby web vypadal, jak má, musí být
zapnuté obojí: plugin v přehledu **Pluginy** a šablona KČT ve **Vzhled →
Šablony**. Samotná aktivace pluginu vzhled webu ještě nezmění.

## Instalace na samostatném webu

1. Stáhnout soubor ZIP z posledního vydání na
   [github.com/martin-svoboda/kct/releases](https://github.com/martin-svoboda/kct/releases).
2. V administraci **Pluginy → Instalace pluginu → Nahrát plugin** vybrat
   stažený soubor a nainstalovat.
3. Plugin aktivovat.
4. Ve **Vzhled → Šablony** aktivovat šablonu KČT.

Plugin potřebuje PHP 8.0 nebo novější.

Je-li web součástí spravované sítě, instalaci ani aktivaci odbor neřeší — web
dostane připravený.

## Aktualizace pluginu a šablony

Plugin není v adresáři WordPress.org, ale novou verzi hlásí sám — běžnou
cestou WordPressu. Je-li venku novější vydání, objeví se u položky Kct
v přehledu **Pluginy** hláška s odkazem na podrobnosti a tlačítkem
**Aktualizovat**. Aktualizovat jde i hromadně přes **Nástěnka →
Aktualizace** a u pluginu se dají zapnout automatické aktualizace.

Šablona vzhledu se aktualizuje spolu s pluginem, protože je uvnitř balíčku —
zvlášť se nenahrává. Nastavení, obsah ani evidence odeslaných příspěvků se
aktualizací neztratí.

Před větší aktualizací je rozumné mít po ruce zálohu. Ruční cesta přes
**Pluginy → Instalace pluginu → Nahrát plugin** funguje dál a hodí se, když
je potřeba vrátit se ke starší verzi: starší balíčky zůstávají ke stažení na
[github.com/martin-svoboda/kct/releases](https://github.com/martin-svoboda/kct/releases).

**Je-li web součástí spravované sítě**, plugin i šablonu nasazuje provozovatel
sítě na všechny weby najednou. Odbor se o to nestará a nová verze prostě
jednoho dne je. Součástí nasazení je i úklid po aktualizaci: srovnají se
odkazy, aktualizují překlady a vymaže se stránková cache, aby se změny hned
projevily.

V obou případech platí, že plugin ani šablonu nemá smysl upravovat přímo na
webu — příští aktualizace soubory přepíše.

## Co se při kontrole aktualizací posílá

Aby web poznal, že je venku novější verze, ptá se dvakrát denně serveru
`updates.sokct.cz`. V dotazu jde:

- adresa webu (u sítě adresa sítě a počet webů v ní),
- verze pluginu, WordPressu a PHP,
- jazyk administrace.

Žádné údaje o návštěvnících ani o obsahu webu se neposílají. Záznamy
jednotlivých dotazů se mažou po 90 dnech, trvale se drží jen poslední známý
stav každé domény. Slouží to k jedinému: vědět, které weby šablonu používají
a jestli někde neběží dávno zastaralá verze s chybou, která je už opravená.

Samotný balíček se stahuje přímo z GitHubu, ne z `updates.sokct.cz`.

Kontroly se dají vypnout — do `wp-config.php` nad řádek
`require_once ABSPATH . 'wp-settings.php';` patří:

```php
define( 'KCT_DISABLE_UPDATES', true );
```

Pak se ale aktualizace přestanou nabízet a je potřeba je hlídat ručně.

## O co se stará správce webu vždycky

Běžnou cestou, tedy přes **Nástěnka → Aktualizace**, se řeší:

- jádro WordPressu,
- plugin KČT se šablonou,
- ostatní pluginy (Rank Math, WP Optimize a další),
- překlady.

Aktualizace je vhodné dělat průběžně, ne jednou za rok najednou — malé kroky se
snáz vrací zpátky, když se něco pokazí. Před větší aktualizací je rozumné mít po
ruce zálohu.

## Jaká verze na webu běží

Číslo verze pluginu je v administraci v přehledu **Pluginy** u položky Kct.
Šablona vzhledu nese stejné číslo a najde se v **Vzhled → Šablony** v detailu
šablony KČT. Obě čísla se při vydání nastavují společně, takže se za běžného
stavu shodují.

Co se v jednotlivých verzích změnilo, shrnuje stránka
[Změny v šabloně](/spravce/zmeny-v-sablone/).
