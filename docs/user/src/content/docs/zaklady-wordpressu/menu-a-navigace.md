---
title: Menu a navigace
description: Jak se sestaví hlavní menu webu, jak se do něj přidávají položky a jak se vnořují do rozbalovacích úrovní.
sidebar:
  order: 4
---

Po přečtení téhle stránky je jasné, jak sestavit menu webu odboru, přidat do něj stránku, rubriku i cizí odkaz a jak vytvořit rozbalovací úroveň.

## Kde se menu skládá

Menu se nesestavuje samo z existujících stránek — skládá se ručně a je to dobře, protože ne všechno, co na webu je, do menu patří.

Nastavení je v **Vzhled → Menu**.

Při první návštěvě tam žádné menu není. Vytvoří se odkazem **vytvořte nové menu**: zadá se název (stačí pracovní, například „Hlavní“, návštěvníkovi se nikde nezobrazí) a potvrdí se tlačítkem **Vytvořit menu**.

## Přidání položek

V levé části obrazovky jsou rozbalovací boxy se vším, na co se dá odkazovat. Položka se vybere zaškrtnutím a přidá tlačítkem **Přidat do menu**. Objeví se vpravo, na konci seznamu.

**Stránky** — nejčastější případ. V boxu je seznam publikovaných stránek; koncepty se v něm neobjeví, ty je potřeba nejdřív publikovat. Záložka **Zobrazit vše** ukáže i starší stránky, které se do prvního seznamu nevešly.

**Rubriky** — odkaz na výpis všech aktualit z jedné rubriky. Hodí se, když má odbor například rubriku Zprávy z výletů a chce ji mít v menu jako samostatnou položku.

**Vlastní odkazy** — libovolná adresa. Vyplní se **URL adresa** a **Text odkazu**. Používá se pro odkaz na cizí web (ústředí KČT, obecní úřad, spřátelený spolek) i pro odkaz na vlastní výpis akcí nebo na soubor v knihovně médií. Jak takové adresy získat správně, popisuje kapitola [Odkazy](/zaklady-wordpressu/odkazy/).

Boxy pro **Akce**, **Odbory** nebo další typy obsahu se zobrazí až po rozbalení volby **Nastavení obrazovky** vpravo nahoře, pokud v seznamu chybí.

## Pořadí a vnořování

Vpravo je struktura menu. Položky se přetahují myší:

- **Nahoru a dolů** se mění pořadí.
- **Doprava, mírně pod předchozí položku**, se položka vnoří. V seznamu se odsadí a označí jako „podpoložka“. Na webu se pak objeví v rozbalovací nabídce pod svým rodičem.

Vnořovat víc než jednu úroveň se nedoporučuje. Menu tří úrovní se na mobilu ovládá špatně a návštěvník se v něm ztrácí.

Kliknutím na šipku u položky se rozbalí její nastavení. Je tam **Navigační titulek** — text, který se zobrazí v menu. Dá se změnit, aniž by se přejmenovala samotná stránka, takže dlouhý název „Historie našeho odboru od roku 1923“ může být v menu jen „Historie“. Stejným rozbalením se položka i odstraní, odkazem **Odstranit**.

Vnoření stránek pod sebe v nastavení stránky (viz [Stránky a aktuality](/zaklady-wordpressu/stranky-a-aktuality/)) je něco jiného než vnoření v menu. Ovlivňuje adresu stránky, ne podobu nabídky. V menu se musí vnořit zvlášť.

## Zobrazení menu na webu

Samotné vytvoření menu ještě neznamená, že se objeví na webu. Musí se přiřadit k pozici v šabloně.

Šablona KČT má jednu pozici a jmenuje se **Hlavní menu**. Přiřadí se buď v sekci **Nastavení menu** dole na stránce zaškrtnutím u **Hlavní menu**, nebo v záložce **Spravovat pozice** nahoře.

Nakonec je potřeba uložit tlačítkem **Uložit menu** vpravo dole. Bez toho se nic z provedených změn neprojeví.

## Na co si dát pozor

- **Menu není seznam všeho.** Pět až osm položek je akorát. Co se do menu nevejde, patří na rozcestníkovou stránku nebo do patičky — viz [Postranní sloupec a patička](/zaklady-wordpressu/postranni-sloupec-a-paticka/).
- **Smazání stránky nesmaže položku v menu.** Zůstane tam a povede na chybovou stránku. Po smazání obsahu se vyplatí menu zkontrolovat.
- **Změna adresy stránky rozbije vlastní odkaz**, který na ni ukazoval. Odkazovat na vlastní stránky přes box Stránky, ne přes Vlastní odkazy — takový odkaz se drží stránky, ne adresy.
- **Aktuality do menu jednotlivě nepatří.** Jsou datované a rychle zastarají. Do menu patří odkaz na jejich výpis nebo na rubriku.
