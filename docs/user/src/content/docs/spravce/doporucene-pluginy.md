---
title: Doporučené pluginy
description: Které pluginy se na webech se šablonou KČT používají, k čemu slouží a kdy je odbor opravdu potřebuje.
sidebar:
  order: 1
---

Po přečtení téhle stránky bude jasné, které pluginy se na webech odborů
osvědčily a podle čeho se pozná, jestli je konkrétní odbor potřebuje.

Šablona KČT obstarává většinu toho, kvůli čemu se pluginy obvykle instalují —
obrázky, sdílení, akce, mapy i galerie. Seznam toho, co se tím pádem nemá
instalovat, je na stránce
[Pluginy, které nepotřebujete](/spravce/zbytecne-pluginy/). Následující pluginy
jsou naopak ty, které své místo mají. Instalace žádného z nich není povinná —
na samostatném webu si je doinstaluje správce podle potřeby, na spravované síti
bývají připravené.

## Rank Math (SEO)

Stará se o titulky, popisky a strukturovaná data pro vyhledávače. Šablona s ním
umí spolupracovat — sama mu dodává údaje o akcích a sdílecí obrázky. Instalace
je volitelná: web funguje i bez něj, s ním se dá do výsledků vyhledávání mluvit
podrobněji. Podrobnosti jsou na stránce [SEO](/funkce/seo/).

## Query Monitor

Ladicí nástroj, který ukazuje databázové dotazy, chyby PHP a průběh
požadavku. Hodí se, když se něco chová divně a je potřeba zjistit proč. Na
běžném provozu nemá co dělat — po doladění je vhodné ho vypnout, protože ke
každé stránce v administraci přidává práci navíc.

## WP Optimize

Úklid databáze — maže staré revize příspěvků, koše a zbytky po odinstalovaných
pluginech, a obstarává stránkovou cache. Stačí ho pouštět občas, ne trvale
plánovaně na každý den.

## Disable Comments

Vypne diskuse a formuláře pro komentáře. Weby odborů diskuse zpravidla
nepoužívají a bez plošného vypnutí se do nich sype spam.

## Better Search Replace

Hromadně nahrazuje text v databázi — typicky adresu webu. Potřebný je jen při
stěhování webu na jinou doménu nebo při zakládání webu z kopie. Mimo tyhle
situace nemá důvod být zapnutý; hromadná náhrada v databázi je nevratná.
