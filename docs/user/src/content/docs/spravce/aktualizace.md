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

Plugin není v adresáři WordPress.org, takže se pro něj v přehledu aktualizací
WordPressu nikdy nenabídne tlačítko.

**Na samostatném webu** je aktualizace na správci webu a dělá se ručně: stáhnout
novější soubor ZIP z vydání na GitHubu a nahrát ho stejnou cestou jako při
instalaci, tedy přes **Pluginy → Instalace pluginu → Nahrát plugin**. WordPress
pozná, že plugin už je nainstalovaný, a nabídne nahrazení stávající verze.
Nastavení, obsah ani evidence odeslaných příspěvků se přitom neztratí. Šablona
vzhledu se aktualizuje spolu s pluginem, protože je uvnitř balíčku — zvlášť se
nenahrává.

**Je-li web součástí spravované sítě**, plugin i šablonu nasazuje provozovatel
sítě na všechny weby najednou. Odbor se o to nestará a nová verze prostě
jednoho dne je. Součástí nasazení je i úklid po aktualizaci: srovnají se
odkazy, aktualizují překlady a vymaže se stránková cache, aby se změny hned
projevily.

V obou případech platí, že plugin ani šablonu nemá smysl upravovat přímo na
webu — příští aktualizace soubory přepíše.

## O co se stará správce webu vždycky

Běžnou cestou, tedy přes **Nástěnka → Aktualizace**, se řeší:

- jádro WordPressu,
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
