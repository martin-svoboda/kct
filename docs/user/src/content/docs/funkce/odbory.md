---
title: Odbory
description: K čemu slouží typ obsahu Odbory, jak se vyplňují údaje odboru a co se z nich kde zobrazí.
sidebar:
  order: 2
---

Po přečtení této stránky je jasné, k čemu typ obsahu Odbory slouží, jak se údaje odboru vyplňují a kde se pak na webu objeví.

## K čemu Odbory slouží

Typ obsahu Odbory drží seznam odborů jedné oblasti — adresu, kontakty, polohu a číslo odboru. Z něj se skládá výpis odborů a stránka každého z nich.

Na samostatném webu jsou Odbory dostupné. Je-li web součástí sítě více webů, existují **jen na jejím hlavním webu** — na podřízeném webu jednotlivého odboru se položka v administraci vůbec neobjeví, a je to tak správně: odbor sám seznam odborů nevede.

## Odkud se odbory vzaly

Seznam odborů se do webu původně načetl importem z centrální databáze KČT. Ten už data nedodává — XML export z centrální databáze skončil.

Odbory se proto vedou ručně: nový odbor se zakládá tlačítkem **Přidat odbor** a všechny údaje u něj jsou v administraci normálně editovatelné, u založených importem stejně jako u ručně přidaných. Žádné pole není zamčené a opravy se nikam jinam neposílají.

:::caution
Kdyby import někdy znovu běžel, údaje, které z něj pocházejí, přepíše svými. Ručně přidané odbory a pole, která import nezná — logo, náhledový obrázek a vlastní text — se ho netýkají.
:::

## Co se u odboru vyplňuje

Nadpis příspěvku je název odboru. V panelu **Data odboru** pod editorem se vyplňuje zbytek:

- **Číslo odboru** — registrační číslo odboru v KČT. Zobrazí se v záhlaví stránky odboru a ve výpisu.
- **Číslo oblasti** — oblast, pod kterou odbor patří.
- **Adresa** — ulice a číslo popisné, PSČ, obec, stát.
- **Telefony a e-maily** — dá se jich přidat víc, tlačítkem pod polem. Telefony se vypíšou jako odkazy k vytočení, e-maily skrytě, aby je z webu nesbírali roboti.
- **Web odboru** — adresa včetně `https://`. Zobrazí se jako tlačítko.
- **Poloha** — zeměpisná šířka a délka jako desetinná čísla (např. 50.2306 a 14.0875). Bez obou souřadnic se u odboru nezobrazí mapka a odbor nebude mít značku v mapě nad výpisem.

Mimo panel Data odboru se ještě vyplňuje:

- **Logo odboru** — pole v pravém sloupci editoru. Zobrazí se v záhlaví stránky odboru a ve výpisu.
- **Náhledový obrázek** — použije se jako velké pozadí záhlaví stránky odboru.
- **Vlastní text** — obsah napsaný v editoru bloků se na stránce odboru zobrazí pod výpisem nejbližších akcí.

Odbor, který se má z webu ztratit, se přesune do koše jako každý jiný příspěvek.

## Výpis odborů

Výpis je na adrese `/odbory/`. Nad seznamem je mapa se značkami všech odborů, pod ní seznam s logem, názvem, číslem odboru a obcí. Značky se neprocházejí klávesou Tab — každý odbor je odkaz v seznamu pod mapou. Nadpis stránky se dá změnit v Přizpůsobení, viz [Vzhled webu](/funkce/vzhled-webu/); bez vyplnění se použije výchozí „Odbory“.

Seznam je **řazený abecedně podle názvu**, česky — tedy Čáslav hned za Cheb, ne až za Zruč, jak by vyšlo při řazení podle písmen bez diakritiky.

Od osmi odborů výš je nad seznamem **políčko pro hledání** v názvu a obci. Hledá se bez ohledu na diakritiku a velikost písmen, takže „ricany“ najde i Říčany, a počet nad seznamem se podle napsaného průběžně mění. Políčko přidává teprve JavaScript: kde není, je seznam celý na stránce a dá se v něm hledat prohlížečem.

## Stránka odboru

Stránka jednoho odboru ukazuje v záhlaví logo, číslo odboru a název, v obsahu tři nejbližší akce odboru a pod nimi vlastní text. V bočním sloupci jsou kontaktní údaje — adresa, telefony, e-maily, odkaz na web odboru a mapka.

Odkud se berou akce vypsané na stránce odboru, popisují [Akce](/funkce/akce/).
