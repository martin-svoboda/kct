---
title: Stránky a aktuality
description: Rozdíl mezi stránkou a aktualitou, kdy použít co a jak se obsah třídí do rubrik.
sidebar:
  order: 2
---

Po přečtení téhle stránky je zřejmé, kdy na webu odboru založit stránku a kdy aktualitu, a jak se obsah třídí do rubrik a štítků.

## Dva různé typy obsahu

WordPress rozlišuje **stránky** a **příspěvky**. V šabloně KČT se příspěvkům říká **aktuality** a v administraci mají v levém menu vlastní položku.

**Stránka** je stálý obsah, který nezastarává. Nemá datum vydání, nikde se sama neobjeví ve výpisu a čtenář se k ní dostane přes menu nebo přes odkaz. Typicky: O odboru, Kontakt, Členství, Historie, Klubovna.

**Aktualita** je datovaný příspěvek. Zveřejněním se automaticky zařadí do výpisu aktualit, nejnovější nahoře, a po čase přirozeně sjede dolů. Typicky: pozvánka na brigádu, zpráva z výletu, výsledky členské schůze, informace o změně termínu.

## Kdy použít co

Pomůcka: **bude ten text platit i za dva roky?**

- Ano — je to stránka. Informace o odboru, kontakty na výbor, popis pravidelných akcí.
- Ne, váže se k datu — je to aktualita. Cokoliv, co má smysl číst hlavně teď.

Časté chyby, které se pak špatně napravují:

- Zprávy z akcí zakládané jako stránky. Nikde se neobjeví ve výpisu, menu se jimi zaplní a po roce v něm visí patnáct položek.
- Kontakt založený jako aktualita. Za půl roku ho zavalí novější příspěvky a nikdo ho nenajde.

## Třetí typ obsahu: akce

Šablona KČT přidává **Akce** jako samostatný typ obsahu, oddělený od stránek i aktualit. Akce má termín, místo, trasu a další údaje, které se vyplňují do připravených polí, a zobrazuje se v kalendáři akcí. Pozvánka na výlet tedy nepatří do aktualit, ale mezi akce — podrobně v kapitole [Akce](/funkce/akce/).

Aktualita zůstává na to ostatní: co se stalo, co se mění, co je potřeba oznámit.

Kromě akcí přidává šablona ještě [Odbory](/funkce/odbory/).

## Rubriky a štítky

Aktuality se dají třídit. Slouží k tomu dva nástroje, které se pletou:

**Rubrika** je zařazení do jedné tematické skupiny. Každá aktualita by měla mít právě jednu rubriku, výjimečně dvě. Rubriky se dají vnořovat a dá se na ně odkazovat z menu, takže si čtenář může zobrazit třeba jen zprávy z výletů. Rubriky vznikají v **Aktuality → Rubriky** nebo přímo v panelu vpravo při psaní.

**Štítek** je volné klíčové slovo. Štítků může být na jedné aktualitě víc a nemají žádnou hierarchii.

Na webu odboru se osvědčuje střídmost: tři až šest rubrik, které se opravdu používají, je lepší než dvacet, kde v každé leží jeden příspěvek. Štítky se dají klidně vůbec nepoužívat.

Stránky rubriky ani štítky nemají.

## Nastavení stránky

U stránky přibývá v pravém sloupci editoru panel **Nastavení stránky** se
čtyřmi přepínači, kterými se upravuje rozvržení jedné konkrétní stránky:

- **Skrýt nadpis stránky** — nadpis se nevypíše. Hodí se, když stránka začíná
  blokem Úvodní obrázek, který svůj vlastní nadpis už nese.
- **Bez bočního panelu** — stránka se roztáhne přes celou šířku a postranní
  sloupec s widgety se nezobrazí.
- **Bez horního odsazení** a **Bez spodního odsazení** — odeberou mezeru mezi
  obsahem a hlavičkou nebo patičkou. Používá se, když má první nebo poslední
  blok navazovat přímo na okraj.

Přepínače jsou vypnuté a nastavují se u každé stránky zvlášť. U aktualit
panel není — ty mají rozvržení dané jednotně.

## Hierarchie stránek

Stránky se na rozdíl od aktualit dají vnořovat pod sebe. V panelu vpravo, v záložce **Stránka**, je pole **Nadřazená stránka**. Když se u stránky Klubovna nastaví jako nadřazená stránka O odboru, promítne se to do adresy — z `/klubovna/` se stane `/o-odboru/klubovna/`.

Vnořování má smysl u obsahu, který k sobě opravdu patří a je ho víc. U webu s deseti stránkami je zbytečné. Pozor na to, že změna nadřazené stránky změní adresu, takže dosud fungující odkazy přestanou platit.

Vnoření samo o sobě neudělá rozbalovací menu — to se skládá zvlášť, viz [Menu a navigace](/zaklady-wordpressu/menu-a-navigace/).

## Náhledový obrázek

Stránky, aktuality i akce mají v panelu vpravo pole **Náhledový obrázek**. Ten se ukazuje ve výpisech a při sdílení na sociálních sítích. U aktualit se vyplatí ho vyplnit vždycky — výpis bez obrázků působí prázdně. Jak si šablona s obrázky poradí, popisuje kapitola [Média a fotky](/zaklady-wordpressu/media-a-fotky/).
