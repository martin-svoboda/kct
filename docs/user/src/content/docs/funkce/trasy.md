---
title: Trasy
description: Jak na webu vznikne trasa, co u ní patří webu a co aplikaci Turinka, a co se stane, když článek s trasou smažete.
sidebar:
  order: 3
# Trasy jsou zatím vypnuté (viz src/Managers/PostTypesManager.php), takže
# stránka do příručky nepatří — v sestavení se vynechá, na disku zůstává.
draft: true
---

Trasa je na webu běžný příspěvek — má název, text a náhledový obrázek jako
článek. Všechno ostatní, co trasu dělá trasou (stopa, délka, převýšení,
obtížnost, mapa, výškový profil), drží aplikace **Turinka** a zadává se v jejím
formuláři, který se otevře nad stránkou příspěvku.

Výpisy tras si web staví sám, takže vypadají jako ostatní výpisy na webu.
Stránka trasy je postavená jako stránka akce nebo odboru: nahoře hlavička přes
celou šířku s náhledovým obrázkem na pozadí, pod ní dva sloupce — vlevo mapa,
text a pod ním výškový profil s tlačítky, vpravo údaje o trase a rozcestník
ostatních tras — a úplně dole navigace na sousední trasu a karty dalších tras.

## Proč to tak je

Stopa trasy není text, který by šel napsat do článku. Je to soubor GPX, ze
kterého se počítá délka, převýšení a profil, a kreslí se z něj mapa. Tohle
všechno Turinka umí a udržuje — web by to musel dělat znovu a hůř.

Dělba práce je proto jednoduchá: **web vlastní text a odkaz na článek, Turinka
všechno ostatní — včetně názvu trasy.**

## Jak trasu založíte

1. **Trasy → Vytvořit trasu.** Napište název a text a příspěvek **uložte** —
   formulář Turinky potřebuje adresu článku, takže u neuloženého příspěvku je
   tlačítko ještě neaktivní.
2. V panelu **Turinka** po straně klikněte na **Založit trasu v Turince**.
   Otevře se formulář aplikace; název z článku už v něm je předvyplněný a
   dál ho spravujete právě tady, ne v článku.
3. Nahrajte **GPX soubor** se stopou. Délka a převýšení se dopočítají samy.
   Bez stopy trasa založit nejde — proto se trasa zakládá formulářem a ne
   v administraci webu.
4. Doplňte, co chcete: obtížnost, sezónnost, druhy aktivity, vhodnost pro
   rodiny nebo kočárky, hlavní obrázek, galerii a **akce, které po trase
   vedou** (nabízí se vlastní akce vašeho odboru).
5. **Uložit.** Panel Turinka se překreslí a od té chvíle je trasa propojená.

Dál už trasu upravujete tlačítkem **Upravit v Turince** ve stejném panelu.

## Text se mění v článku, název ve formuláři

Tohle je jediné místo, kde se obě strany potkávají, a vyplatí se ho vědět.

**Text trasy posílá web** — do minuty po každém uložení příspěvku. Formulář
Turinky text ukazuje a dovolí ho přepsat, ale při dalším uložení článku ho web
přepíše zpátky. Text tedy pište v článku.

**Název trasy patří formuláři.** Web ho pošle jedinkrát, při prvním odeslání po
založení, aby trasa nezůstala bez jména — a od té chvíle na něj nesahá. Trasu
tedy přejmenujete tlačítkem **Upravit v Turince**; přepsání názvu článku na webu
už jméno trasy v aplikaci nezmění.

:::note
Dřív název posílal web při každém uložení a přejmenování ve formuláři tím
přepisoval. Jestli vám někdy jméno trasy „samo“ naskočilo zpátky na titulek
článku, bylo to tohle.
:::

Ve formuláři patří vám všechno ostatní; na to web nesahá, a to ani náhledovým
obrázkem — hlavní obrázek trasy v Turince je jiný obrázek než náhled článku na
webu.

## Když článek odpublikujete

Trasa v Turince **zůstane**, jen se jí vezme odkaz na váš web: stránka článku
je pryč, takže není kam odkazovat. Jakmile článek zase publikujete, odkaz se
vrátí sám při nejbližším uložení.

## Když článek s trasou smažete

**Smazání článku trasu z Turinky neodebere.** Je to záměr: může se stát, že
článek na webu už nechcete, ale trasa má lidem v aplikaci zůstat.

Po přesunu do koše (i po smazání natrvalo) se proto nahoře objeví hláška
„Článek je pryč z webu. Trasa … v Turince zůstala viditelná.“ s tlačítkem
**Odebrat z Turinky**. Dokud ho nikdo nestiskne, trasa v Turince zůstává.

Když ho stisknete, Turinka trasu schová — lidé ji přestanou vidět, ale nemaže
se. Založíte-li k témuž článku trasu znovu, vrátí se i s daty, která v ní byla.

Totéž rozhodnutí má panel Turinka u příspěvku: červený odkaz **Odebrat
z Turinky** použijete, když chcete článek nechat a trasu stáhnout.

## Výpisy tras

Archiv tras je na adrese `/trasa/` a vypisuje karty jako ostatní archivy —
náhledový obrázek, název a úvodní text; trase bez obrázku se v kartě ukáže
logo webu. Nadpis stránky se dá změnit v Přizpůsobení, viz
[Vzhled webu](/funkce/vzhled-webu/); bez vyplnění se použije výchozí „Trasy“. Do stránky se výpis vkládá blokem
**Trasy** (kategorie KČT), u kterého se nastaví počet, řazení a nepovinné
tlačítko na archiv.

## Rámečky na stránce trasy

Údaje z Turinky jsou na stránce rozdělené do tří rámečků, aby text článku
nezůstal někde až pod mapou a profilem:

- **nad textem** mapa se stopou,
- **v pravém sloupci** údaje o trase — délka, převýšení, odhad času, obtížnost
  a oblast; je to totéž místo, kde má akce svoje údaje,
- **pod textem** výškový profil a tlačítka aplikace (Oblíbit, Sdílet, Stáhnout
  GPX) a pod nimi odkaz na trasu v Turince.

Všechny tři najednou vypnete v **KČT → Nastavení → Turinka** volbou *Widget
Turinky u tras*; zmizí s nimi i odkaz na trasu v Turince.

Nepropojená trasa rámečky nemá a vypíše se jen jako článek — pravý sloupec jí
zůstane s rozcestníkem ostatních tras.
