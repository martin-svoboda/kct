---
title: Kalendář akcí
description: Výpis akcí z databáze KČT s nastavením období, počtu a tlačítka.
sidebar:
  order: 3
---

Na konci téhle stránky je jasné, jak na stránku vložit výpis akcí a proč se
jeho obsah nedá v editoru přepsat ručně.

## K čemu blok je

Kalendář akcí vypíše seznam akcí z databáze KČT. Každá položka ukazuje datum,
obrázek, název akce, pořadatele a místo konání a odkazuje na detail akce.

Hodí se na úvodní stránku a na stránky, kde má být vidět, co odbor chystá.
Odkud se akce berou a jak se do databáze dostávají, popisuje kapitola
[Akce](/funkce/akce/).

Vkládá se tlačítkem `+` a vyhledáním názvu **Kalendář akcí**.

## Blok je dynamický

Seznam akcí se neukládá do stránky. Skládá se až ve chvíli, kdy někdo stránku
otevře — z toho, co je zrovna v databázi. Nová akce se tedy ve výpisu objeví
sama, bez zásahu do stránky, a akce, která proběhla, z něj sama zmizí.

V editoru se ukazuje náhled, který připraví server. Do textu v něm nejde psát a
nejde v něm nic mazat: jediné, co jde nastavit, jsou volby v postranním panelu.
Náhled se navíc může lišit od toho, co uvidí návštěvník později — pokaždé se
sestaví znovu z aktuálních dat.

Když žádná akce nastavení neodpovídá, zobrazí se místo seznamu krátká
informace, že akce nebyly nalezeny.

## Pole bloku

Všechna nastavení jsou v postranním panelu vpravo.

**Časové období** — *Budoucí* vypíše akce, které teprve budou (obvyklá volba),
*Minulé* akce, které už proběhly. Minulé akce se hodí spíš na stránku
s ohlédnutím za činností odboru.

**Počet zobrazených akcí** — posuvník od 1 do 20, ve výchozím stavu 5. Na
úvodní stránku se hodí menší číslo, dlouhý seznam se lépe vyjímá na samostatné
stránce.

**Text tlačítka na kalendář akcí** — popisek tlačítka, které se zobrazí pod
seznamem a vede na výpis všech akcí. Když pole zůstane prázdné, tlačítko se
nezobrazí. Vhodný text je třeba „Všechny akce“.
