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

## 2.1.2 — 8. 11. 2026

- **Úklid knihovny už nerozbije obrázek ve stránce.** Při převodu starších PNG
  do WebP se mění název souboru a odkazy v obsahu se přepisují. Přepis se ale
  u webu odboru v síti minul cílem a obrázek pak ve stránce zmizel, i když
  soubor na disku byl. Nově se odkazy dohledají na všech webech sítě, takže
  obstojí i obrázek vložený z knihovny jiného odboru — tak se na web odboru
  dostanou třeba loga nahraná na webu oblasti.

- **Z knihovny se uklidí i druhá kopie plné velikosti.** WordPress si vedle
  plného souboru drží sadu zmenšenin a ta největší z nich po zmenšení knihovny
  vyjde na tentýž obrázek — někdy přesně, častěji o bod jinak kvůli
  zaokrouhlení. Dřív se poznala jen přesná shoda, takže kopie zůstávaly ležet.
  Odkaz na smazanou kopii se v obsahu přepíše na plný soubor, takže se ve
  stránce nic nezmění.

## 2.1.1 — 7. 11. 2026

- **Stránka akce nabízí, kam jít dál.** Pod obsahem akce je navigace na
  předchozí a následující akci — řadí se podle data konání, takže jde projít
  kalendář dopředu i zpátky. Pod ní je **Mohlo by vás zajímat** se třemi
  nejbližšími budoucími akcemi; přednost mají akce stejného druhu. Na konci
  postranního oddílu přibyly **Další ročníky**, tedy tatáž akce v jiných
  letech, od nejnovějšího ročníku k nejstaršímu. Ročníky se poznají podle
  názvu akce a pořádajícího odboru, takže fungují i u akce, která v centrální
  databázi vůbec není. Popisuje to stránka [Akce](/funkce/akce/).

- **Ikony druhů turistiky se zobrazují u všech akcí.** U části akcí chyběly,
  protože je centrální databáze u některých záznamů neposílá. Nově se doplní
  z číselníku druhů turistiky, který se stahuje spolu s akcemi. Načítají se
  navíc přímo z webu, ne ze serveru ústředí.

- **Zrušené akce mizí z webu spolehlivěji.** Import odstraní i akci, kterou
  centrální databáze přestala nabízet bez ohlášení — typicky když ústředí
  nahradí chybný záznam novým a na webu pak visely obě verze vedle sebe.
  Maže se jen dopředu: proběhlé akce v archivu zůstávají, i když je centrální
  databáze už nenabízí.

- **Import nepoloží jediný vadný znak.** Export z centrální databáze nebyl
  vždy platné XML a import kvůli tomu tiše skončil bez jediné akce. Teď si
  takovou vadu opraví sám a zapíše ji do chybového logu; když se export přečíst
  opravdu nedá, napíše do logu proč, místo aby mlčel.

- **Administrace mluví česky.** Akce mají v menu položku **Kalendář akcí**
  s ikonou kalendáře, odbory ikonu skupiny. Hlášky typu „Add New Akce"
  a „Edit Odbor" jsou pryč.

- **Obrázky PNG se po nahrání převádějí do WebP, stejně jako fotky.** Dosud
  se převáděly jen soubory JPEG a PNG zůstávalo beze změny, protože se čekalo,
  že na logách a schématech bude komprese dělat viditelné šmouhy. Měření na
  skutečné knihovně to nepotvrdilo — odchylka barvy od předlohy není poznat ani
  při dvojnásobném zvětšení a objem přitom výrazně spadne. Průhlednost se
  převodem neztrácí,
  takže logo s průhledným pozadím zůstane průhledné. Podrobnosti popisuje
  stránka [Média a fotky](/zaklady-wordpressu/media-a-fotky/).

- **Starší knihovnu jde srovnat se současnými pravidly.** Fotky nahrané dřív,
  než šablona zavedla strop 2048 pixelů, zabírají místo navíc, které se na
  obrazovku nikdy nedostane. Nově je správce sítě umí zmenšit jednorázově,
  i zpětně, a zároveň s tím převést starší PNG do WebP. Ze stránky Média se to
  vyvolat nedá, spouští se to z příkazové řádky a dá se to nejdřív pustit
  nanečisto. Komu se knihovna nafoukla, ať se ozve.

## 2.1.0 — 6. 11. 2026

- **Šablona si novou verzi hlásí sama.** Je-li venku novější vydání, objeví se
  u pluginu Kct v přehledu **Pluginy** hláška s tlačítkem **Aktualizovat** —
  stejně jako u kteréhokoli jiného pluginu. Stahovat balíček z GitHubu a
  nahrávat ho ručně už není potřeba; ruční cesta zůstává pro návrat ke starší
  verzi. Podrobnosti i to, co se při kontrole odesílá, popisuje stránka
  [Aktualizace šablony](/spravce/aktualizace/).

  :::note[Platí až od příští aktualizace]
  Aby se nová verze uměla nabídnout sama, musí na webu nejdřív běžet tahle.
  Přechod z 2.0.x na 2.1.0 se tedy ještě dělá ručně.
  :::

- **Údaje odboru jdou vyplnit celé v administraci.** Dřív šla část polí
  přepsat jen v centrální Databázi akcí KČT. Její export už ale není v
  provozu, takže se všechno edituje přímo na webu — i u odborů, které vznikly
  importem. Popisuje to stránka [Odbory](/funkce/odbory/).

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
