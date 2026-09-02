---
title: Pluginy, které nepotřebujete
description: Přehled toho, co šablona KČT řeší sama, a pluginů, které se kvůli tomu nemají instalovat.
sidebar:
  order: 2
---

Po přečtení téhle stránky bude jasné, které běžné WordPress pluginy jsou na webu
se šablonou KČT zbytečné — a co místo nich obstarává sama šablona.

Tabulka se čte zleva doprava: co je potřeba vyřešit, čím to už vyřešené je, a co
se tedy nemá instalovat.

| Co byste chtěli řešit | Řeší to šablona | Neinstalujte |
| --- | --- | --- |
| Zvětšování obrázků po kliknutí | vestavěný lightbox WordPressu, zapnutý pro všechny obrázky a galerie — viz [Média a fotky](/zaklady-wordpressu/media-a-fotky/) | Lightbox PhotoSwipe, Simple Lightbox, FooBox |
| Zmenšování a komprese fotek | strop 2048 px a převod do WebP při nahrání — viz [Média a fotky](/zaklady-wordpressu/media-a-fotky/) | Smush, ShortPixel, EWWW, Imagify |
| Odstranění dat fotoaparátu z fotek | úklid při nahrání — viz [Média a fotky](/zaklady-wordpressu/media-a-fotky/) | pluginy na čištění EXIF |
| Náhledové obrázky pro Facebook | automaticky vykreslované karty — viz [Sdílecí obrázky](/funkce/sdileci-obrazky/) | pluginy na Open Graph |
| Odesílání příspěvků na Facebook | vestavěné sdílení včetně časování — viz [Sdílení na Facebook](/funkce/sdileni-na-facebook/) | Jetpack Publicize, Blog2Social |
| Kalendář a výpis akcí | typ obsahu Akce a import z databáze KČT — viz [Akce](/funkce/akce/) | The Events Calendar, Modern Events Calendar |
| Mapy | vestavěné mapy u výpisu i detailu akcí — viz [Akce](/funkce/akce/) | pluginy s Google Maps |
| Galerie fotek | blok Galerie v editoru doplněný lightboxem — viz [Média a fotky](/zaklady-wordpressu/media-a-fotky/) | NextGEN Gallery, Envira |

## Proč na tom záleží

Každý plugin navíc je věc, která se musí aktualizovat, přidává práci ke každému
načtení stránky a může se po vlastní aktualizaci nebo po aktualizaci WordPressu
rozbít. Za pár měsíců nikdo neví, proč tam je, a odinstalovat ho už si nikdo
netroufne.

Horší než zátěž je ale souběh. Dva pluginy, které dělají totéž, si navzájem
přebíjejí výstup: dva lightboxy se navěsí na stejné obrázky a otevřou dvě okna
přes sebe, dva zdroje značek Open Graph vypíšou dva různé obrázky a Facebook si
vybere ten horší, dvě komprese proženou stejnou fotku dvakrát a je z ní kaše.
Výsledek se pak těžko dohledává, protože ani jedna strana není rozbitá — jen se
tlučou.

## Když už některý z nich na webu je

Vypnutí takového pluginu není rozhodnutí od boku. Řada z nich si totiž drží
obsah ve vlastních tabulkách a jen jejich vypnutí znamená, že obsah ze stránek
zmizí — typicky galerie NextGEN, které jsou v příspěvcích vložené vlastní
značkou a bez pluginu se nevykreslí. Obsah je proto potřeba nejdřív převést do
standardních bloků a plugin vypnout až potom. Je-li web
součástí spravované sítě a plugin aktivovaný pro celou síť, vypnout ho může jen
provozovatel sítě.

Pluginy, které naopak smysl mají, jsou popsané na stránce
[Doporučené pluginy](/spravce/doporucene-pluginy/).
