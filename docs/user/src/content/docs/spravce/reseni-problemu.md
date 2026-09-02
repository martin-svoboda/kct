---
title: Řešení problémů
description: Nejčastější potíže na webech se šablonou KČT a postup, jak je odstranit.
sidebar:
  order: 6
---

Po přečtení téhle stránky bude jasné, co dělat u nejčastějších potíží — a kdy
už je namístě obrátit se na někoho, kdo má k webu hlubší přístup.

## Nenačítají se akce

Výpis akcí je prázdný, i když v centrální databázi KČT akce jsou.

Akce se stahují podle kódu odboru nebo oblasti. Ten se zadává v **Nastavení →
KČT** do pole *Kód oblasti / odboru* a musí mít přesně tři číslice (oblast),
nebo šest číslic (odbor). Nevyplněné, chybně opsané nebo o číslici delší číslo
znamená prázdný výpis, ne chybovou hlášku. Postup je popsaný na stránce
[První nastavení webu](/zaciname/prvni-nastaveni/).

Když je kód správný a akce se přesto neobjeví, stojí za to počkat — akce se
načítají v dávkách, ne při každém zobrazení stránky.

## Příspěvek se neodeslal na Facebook

1. V **Nastavení → KČT** stisknout tlačítko **Ověřit připojení**. Vypíše se
   název stránky, ke které token patří, nebo chyba od Facebooku.
2. Nejčastější příčina je **propadlý nebo zneplatněný token**. Platnost končí
   sama, změnou hesla u účtu, který ho vydal, nebo odebráním práv aplikaci.
   Řešením je vygenerovat token nový.
3. Odeslání neprobíhá okamžitě — u aktualit se odesílá s krátkým odstupem po
   publikaci, u akcí podle nastaveného počtu dní před akcí a nastavené hodiny.
   Chvíli po publikaci tedy ještě není důvod k poplachu.
4. Neúspěšný pokus se sám opakuje. Stav odeslání i důvod neúspěchu je vidět
   u příspěvku.

Podrobnosti jsou na stránce
[Sdílení na Facebook](/funkce/sdileni-na-facebook/), uložení tokenu řeší
stránka [Bezpečnost](/spravce/bezpecnost/).

## Změna na webu není vidět

V administraci je uložená, na webu stará podoba. Prakticky vždy jde o
stránkovou cache: hotové HTML se ukládá a návštěvníkům se servíruje uložená
kopie, dokud nevyprší.

- Načíst stránku s obejitím cache — tvrdým obnovením (Ctrl+F5, na macOS
  Cmd+Shift+R), nebo připojením otazníku a libovolného parametru za adresu.
- Zkusit stránku v anonymním okně; přihlášený uživatel často dostává obsah
  mimo cache a rozdíl to ukáže.
- Když se změna po obejití cache zobrazí správně, stačí vyprázdnit cache ve
  WP Optimize.

Totéž platí i obráceně: hlášení „u mě to je špatně“ může být jen stará uložená
kopie u jednoho návštěvníka.

## Nahraná fotka vypadá jinak

Fotka je menší, než jaká se nahrávala, nebo má jinou příponu. Tak to má být:
při nahrání se delší strana zmenší na 2048 px a odvozené velikosti fotek se
ukládají ve formátu WebP, který je při stejném rozlišení znatelně menší.
Zároveň se z fotek odstraňují data fotoaparátu.

Změna se týká jen nově nahrávaných souborů, starší knihovna zůstává, jak byla.
Celé je to popsané na stránce
[Média a fotky](/zaklady-wordpressu/media-a-fotky/).

## Když nic z toho nepomůže

Před dotazem se hodí připravit si: adresu stránky, kde se to děje, co přesně se
dělalo, co se čekalo a co se stalo, čas a případný snímek obrazovky s chybovou
hláškou.

S čím se obrátit dál — na samostatném webu na správce webu, tedy na toho, kdo
má přístup k hostingu a souborům; je-li web součástí spravované sítě, na
provozovatele sítě:

- chyby, které se objeví na celém webu nebo znemožní přihlášení,
- zásah do souboru `wp-config.php` (například konstanta pro facebookový token),
- obnova ze zálohy,
- vypnutí či instalace pluginu, který je v síti aktivovaný pro všechny weby,
- podezření, že se na web dostal někdo cizí.
