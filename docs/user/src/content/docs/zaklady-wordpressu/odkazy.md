---
title: Odkazy
description: Jak v editoru vytvořit odkaz na vlastní stránku, na cizí web a na soubor v knihovně médií.
sidebar:
  order: 6
---

Po přečtení téhle stránky je jasné, jak v textu vytvořit odkaz na vlastní stránku, na cizí web i na soubor ke stažení a jaké adresy do odkazu naopak nikdy nepatří.

## Vložení odkazu

Odkaz vzniká z označeného textu. Postup je vždycky stejný:

1. Označit slovo nebo několik slov, která mají být odkazem.
2. Kliknout na ikonu řetízku v panelu nad blokem, nebo stisknout Ctrl+K (na Macu Cmd+K).
3. Do pole zadat cíl.
4. Potvrdit klávesou Enter nebo tlačítkem se šipkou.

Hotový odkaz se upraví kliknutím do něj a použitím tužky; křížek nebo ikona přeškrtnutého řetízku odkaz zruší a text zůstane.

Jako text odkazu se používá popis cíle, ne slova „zde“ nebo „klikněte sem“. Vhodné je „přihláška na letní táboření“, nevhodné „přihlášku najdete zde“. Kromě čitelnosti to pomáhá tomu, kdo web poslouchá čtečkou obrazovky a slyší jen odkazy vytržené z textu.

## Odkaz na vlastní stránku

Do pole odkazu se **nevkládá adresa** — napíše se pár písmen z názvu stránky. Editor prohledá obsah webu a nabídne, co odpovídá; ze seznamu se vybere správná položka.

Tenhle způsob je jediný správný pro vlastní obsah. Odkaz vytvořený z nabídky se drží té konkrétní stránky, takže vydrží i pozdější změnu adresy nebo přejmenování.

Nabídka najde stránky, aktuality i akce.

## Externí odkaz

U cizího webu se do pole vloží celá adresa i s `https://`, například `https://www.kct.cz`.

Pod polem je přepínač **Otevřít v nové kartě**. Pravidlo, které se osvědčuje:

- **Odkaz na vlastní web** — nechat vypnuté. Čtenář zůstává v jednom okně a funguje mu tlačítko Zpět.
- **Odkaz na cizí web** — zapnout. Čtenář o rozečtenou stránku odboru nepřijde.
- **Odkaz na soubor ke stažení** — zapnout. PDF nebo GPX by jinak nahradilo obsah okna.

Adresu je vždycky lepší zkopírovat z adresního řádku prohlížeče než ji přepisovat ručně.

## Odkaz na soubor v knihovně médií

Dokumenty ke stažení — pozvánka v PDF, propozice, stanovy, záznam trasy v GPX — se nejdřív nahrají do knihovny médií, viz [Média a fotky](/zaklady-wordpressu/media-a-fotky/). Teprve pak se na ně dá odkazovat.

Adresa souboru se získá takto:

1. Otevřít **Média → Knihovna** a kliknout na soubor.
2. V podrobnostech vpravo dole najít pole **Adresa souboru**.
3. Použít tlačítko **Kopírovat adresu do schránky**.
4. Vrátit se do editoru a adresu vložit do odkazu.

Rychlejší cesta vede přes blok **Soubor**. Ten se vloží tlačítkem `+`, vybere se soubor z knihovny a blok sám vytvoří odkaz s názvem souboru a tlačítkem ke stažení.

Záznam trasy ve formátu GPX se vkládá stejnou cestou jako PDF: nahrát do knihovny médií a odkázat na něj z textu akce nebo stránky. Účastníci si ho pak stáhnou do mapy nebo do hodinek.

## Proč nekopírovat adresy z administrace

Nejčastější chyba při vytváření odkazů: rozepsaná stránka je otevřená v editoru, adresa z prohlížeče se zkopíruje a vloží jako odkaz.

Taková adresa **není adresa stránky**. Je to adresa jejího editoru — poznat se dá podle `wp-admin` uprostřed. Kdo na ni klikne, uvidí v lepším případě přihlašovací formulář, v horším chybu o nedostatečném oprávnění. Členům odboru bez účtu na webu tenhle odkaz nefunguje nikdy.

Podobně nepoužitelné jsou:

- **Adresa z tlačítka Náhled** — obsahuje jednorázový klíč, který po čase přestane platit.
- **Adresa s otazníkem a číslem**, například `?p=137` — funguje, ale je nesrozumitelná a při přesunu obsahu se snadno rozbije.

Správná adresa vlastní stránky je ta, kterou prohlížeč ukazuje **při běžné návštěvě webu**, ne v administraci. U vlastního obsahu je ale stejně jednodušší nekopírovat nic a vybrat stránku z nabídky, jak popisuje odstavec výše.

## Kontrola

Před publikováním se vyplatí odkazy proklikat v [náhledu](/zaklady-wordpressu/publikovani/). Nefunkční odkaz na pozvánku k akci je horší než žádný.
