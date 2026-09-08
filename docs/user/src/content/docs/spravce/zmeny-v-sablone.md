---
title: Změny v šabloně
description: Co přinesly poslední vydané verze šablony KČT z pohledu správce webu.
sidebar:
  order: 7
---

Po přečtení téhle stránky bude jasné, co přinesly poslední vydané verze šablony
KČT a jestli je kvůli nim potřeba na webu odboru něco nastavit.

Přehled je uživatelský výtah — popisuje, co se změnilo pro toho, kdo web
spravuje, ne seznam úprav v kódu. Jaká verze na webu právě běží, se zjistí podle
stránky [Aktualizace šablony](/spravce/aktualizace/).

Historie začíná u verze 2.0.0, kterou se šablona začala vydávat veřejně.
Předchozí vydání vznikala jen pro weby provozované přímo autorem šablony a
zpětně se nedoplňují — všechno, co uměla, je součástí verze 2.0.0.

## 2.0.0 — 8. 9. 2026

První veřejné vydání. Šablonu si nově může nainstalovat kterýkoli odbor, ne
jen weby ve spravované síti.

- **Adresa exportu z Databáze akcí KČT se zadává v nastavení.** V
  **Nastavení → KČT → Obecné** přibylo pole **Adresa exportu z Databáze akcí
  KČT**. Dřív byla adresa v šabloně napevno, teď ji vyplňuje správce webu.
  Adresa není veřejná a **sdělí ji na vyžádání ústředí KČT** — napište si o ni
  stejnou cestou, jakou odbor s ústředím komunikuje běžně. Postup popisuje
  stránka [První nastavení webu](/zaciname/prvni-nastaveni/).

  :::caution[Po aktualizaci je potřeba zasáhnout]
  Dokud pole zůstane prázdné, akce se z Databáze akcí nenačítají a jejich výpis
  bude prázdný. Na webu, který běžel na některé z dřívějších verzí, je proto
  potřeba adresu po aktualizaci doplnit — samo se nepřenese.
  :::

- **Sjednocené číslování verzí.** Číslování začíná od 2.0.0. Web, který dřív
  hlásil vyšší číslo, tedy po aktualizaci ukáže 2.0.0, přestože je novější —
  žádná funkce se tím neztratila.

Co šablona umí, popisují jednotlivé stránky v části **Funkce** — od
[akcí](/funkce/akce/) přes [sdílení na Facebook](/funkce/sdileni-na-facebook/)
a [sdílecí obrázky](/funkce/sdileci-obrazky/) po
[vzhled webu](/funkce/vzhled-webu/).
