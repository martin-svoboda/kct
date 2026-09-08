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
stránky [Aktualizace šablony](/spravce/aktualizace/). Historie začíná u verze
2.1.2, starší vydání se zpětně nedoplňují.

## 2.2.1 — 31. 8. 2026

- **Sdílení akcí z centrální databáze KČT na Facebook.** Dosud se automaticky
  sdílely jen aktuality a akce založené jako vlastní příspěvek, což je zlomek
  toho, co web vypisuje. Nově se odesílají i akce převzaté z databáze KČT, které
  žádný příspěvek nemají. Obstarává to denní úloha, která odešle akce, jimž
  právě nastal den odeslání podle nastavení. Podrobnosti jsou na stránce
  [Sdílení na Facebook](/funkce/sdileni-na-facebook/).

## 2.2.0 — 31. 8. 2026

- **Vlastní sdílecí obrázky.** Aktualitám i akcím se automaticky vykresluje
  obrázek pro sdílení — u aktuality fotografická karta s titulkem, u akce datová
  karta s termínem a místem, která nese to podstatné, i když akce žádnou fotku
  nemá. Dřív se sdílela holá fotka bez kontextu, nebo logo klubu. Viz stránka
  [Sdílecí obrázky](/funkce/sdileci-obrazky/).
- **Sdílení fotkou místo odkazu.** Příspěvek na Facebooku má nově podobu fotky
  ve formátu na výšku, která ve feedu zabere výrazně víc místa; odkaz je součástí
  textu příspěvku. Vedlejším důsledkem je, že se sdílené obrázky ukládají do
  fotoalba stránky — tak se fotopříspěvky na Facebooku chovají.
- **Časování odeslání akcí.** V **Nastavení → KČT** přibylo, kolik dní před
  akcí a v kolik hodin se má pozvánka odeslat. Dřív odcházela hned po publikaci,
  takže pozvánka na pochod za půl roku zapadla dřív, než byla aktuální.
- **Oddělený výchozí stav sdílení pro aktuality a akce.** Jedno nastavení se
  rozdělilo na dvě, takže se dá zapnout automatické sdílení jen pro jeden z obou
  druhů obsahu.
- **Zrušené nastavení „Výchozí náhledový obrázek“.** Nemělo už co dělat —
  aktuality i akce mají vlastní generovanou kartu.

## 2.1.2 — 29. 8. 2026

- **Vyhledávání v hlavičce.** Volitelná ikona lupy vedle menu, po kliknutí se
  rozbalí vyhledávací pole. Zapíná se v přizpůsobení vzhledu a ve výchozím stavu
  je vypnutá — hlavička je na každém webu zaplněná jinak. Bez ní se na webu dá
  hledat jen zadáním adresy s parametrem vyhledávání. Viz stránka
  [Vzhled webu](/funkce/vzhled-webu/).
- **Úpravy hlavičky a patičky.** Drobné sjednocení vzhledu a úklid v šabloně,
  bez dopadu na nastavení.
