---
title: Odbory
description: K čemu slouží typ obsahu Odbory, odkud se seznam odborů načítá a co se u odboru dá doplnit ručně.
sidebar:
  order: 2
---

Po přečtení této stránky je jasné, k čemu typ obsahu Odbory slouží, odkud se seznam bere a co se u odboru dá doplnit ručně.

## K čemu Odbory slouží

Typ obsahu Odbory drží seznam odborů jedné oblasti — adresu, kontakty, polohu a číslo odboru. Z něj se skládá výpis odborů a stránka každého z nich.

Na samostatném webu jsou Odbory dostupné. Je-li web součástí sítě více webů, existují **jen na jejím hlavním webu** — na podřízeném webu jednotlivého odboru se položka v administraci vůbec neobjeví, a je to tak správně: odbor sám seznam odborů nevede.

## Seznam se načítá z centrální databáze

Odbory se nezakládají ručně. Načítají se z centrální databáze KČT, stejně jako akce. Import z ní přebírá:

- název odboru a jeho číslo,
- ulici, PSČ, obec a stát,
- telefony a e-maily,
- adresu webu odboru,
- zeměpisné souřadnice pro mapu.

Které odbory se načtou, určuje kód oblasti v nastavení šablony — bez něj se nenačte nic. Kde se kód zadává, popisuje [První nastavení](/zaciname/prvni-nastaveni/). Import se spouští příkazem na serveru, není k němu tlačítko v administraci.

Odbory, které centrální databáze označí za smazané, přesune import do koše.

## Co se u odboru doplňuje ručně

Údaje z databáze se při každé změně přepíšou, takže je nemá smysl upravovat v administraci — opravy patří do centrální databáze KČT.

Ručně doplněné věci ale import nepřepisuje a zůstávají:

- **Logo odboru** — pole v pravém sloupci editoru. Zobrazí se v záhlaví stránky odboru a ve výpisu.
- **Náhledový obrázek** — použije se jako velké pozadí záhlaví stránky odboru.
- **Vlastní text** — obsah napsaný v editoru bloků se na stránce odboru zobrazí pod výpisem nejbližších akcí.

## Výpis odborů

Výpis je na adrese `/odbory/`. Nad seznamem je mapa se značkami všech odborů, pod ní seznam s logem, názvem, číslem odboru a obcí. Nadpis stránky se dá změnit v Přizpůsobení, viz [Vzhled webu](/funkce/vzhled-webu/); bez vyplnění se použije výchozí „Odbory“.

## Stránka odboru

Stránka jednoho odboru ukazuje v záhlaví logo, číslo odboru a název, v obsahu tři nejbližší akce odboru a pod nimi vlastní text. V bočním sloupci jsou kontaktní údaje z centrální databáze — adresa, telefony a e-maily. E-mailové adresy se vypisují skrytě, aby je z webu nesbírali roboti.

Odkud se berou akce vypsané na stránce odboru, popisují [Akce](/funkce/akce/).
