---
title: Sdílecí obrázky
description: Šablona sama vykresluje náhledové obrázky aktualit a akcí pro sociální sítě — co je na nich, kdy se obnoví a proč na to není potřeba plugin.
sidebar:
  order: 8
---

Po přečtení téhle stránky je jasné, odkud se bere obrázek, který se ukáže při
sdílení odkazu z webu na sociální síť, co se do něj propisuje a kdy se
přegeneruje.

## K čemu to je

Když někdo vloží odkaz na aktualitu nebo akci na Facebook, do zprávy nebo do
jiné sociální sítě, zobrazí se u odkazu náhledový obrázek. Bez něj vypadá
odkaz jako holý text a lidé na něj klikají mnohem míň.

Šablona takový obrázek pro každou publikovanou aktualitu a pro každou akci
**sama vykreslí**. Není to jen zvětšená fotka — je to karta s údaji, takže
odkaz na sociální síti vypadá jako akce, ne jako náhodný obrázek. A funguje to
i tam, kde žádná fotka není, což je u většiny akcí z centrální databáze.

Nastavovat se nedá nic a není co zapínat. Redaktor jen napíše obsah.

## Co je na kartě aktuality

- **fotka** — náhledový obrázek aktuality jako pozadí; když žádný není, použije
  se grafické pozadí v barvách šablony;
- **rubrika** aktuality jako štítek nad titulkem;
- **titulek** aktuality, zalomený nejvýš do tří řádků;
- **datum vydání a doba čtení** ve spodním řádku — stejné číslo, jaké ukazuje
  hlavička článku na webu;
- **logo** webu v pravém horním rohu.

## Co je na kartě akce

- **datumová kartička** se dnem a měsícem, u vícedenní akce s rozsahem, stejná
  jako ve výpisu akcí;
- **ročník** akce nad názvem, pokud je vyplněný („46. ročník");
- **název** akce;
- **start, cíl a pořadatel** — tedy totéž, co na detailu akce nesou infoboxy
  pod hlavičkou, včetně místa a okresu;
- **ikony** druhů akce (pěší, cyklo…);
- **délky tras** — na svislé variantě karty, jeden řádek na druh akce, který má
  vyplněné kilometry;
- **logo** webu; když web vlastní logo nastavené nemá, použije se obecné logo
  KČT.

Logo se bere z loga webu nastaveného v Přizpůsobení (viz
[Vzhled webu](/funkce/vzhled-webu/)). Logo ve formátu SVG se do karty
vykreslit nedá, takže se místo něj použije obecné logo KČT — komu na vlastním
logu na kartách záleží, nahraje ho jako PNG.

Fotka akce je na kartě jen jako textura: odbarví se a překryje tmou, aby text
zůstal čitelný. Většina akcí z importu žádnou nemá, takže výchozím stavem je
grafické pozadí, ne fotka. Kartu to nijak nekazí.

## Dvě velikosti

Z každé aktuality a akce vznikají dvě podoby:

- **širokoúhlá karta** 1200 × 630 bodů — ta se ukazuje u odkazu vloženého na
  sociální síť nebo do zprávy;
- **svislý plakát** 1080 × 1350 bodů — ten používá
  [Sdílení na Facebook](/funkce/sdileni-na-facebook/), když příspěvek odesílá
  jako fotku. Na výšku se do karty vejde víc údajů, třeba právě délky tras.

## Kdy se obrázek vyrobí a kdy přegeneruje

Obrázek vzniká hned při **uložení publikované** aktuality nebo akce, ať na něj
nečeká první návštěvník.

Přegeneruje se pokaždé, když se změní něco, co je na něm vidět — titulek,
datum, místo, pořadatel, náhledová fotka, rubrika. Název souboru totiž nese
otisk všech vykreslovaných údajů: jiný obsah znamená jiný název, tedy i jinou
adresu obrázku. Sociální sítě si díky tomu sáhnou pro novou podobu a nezůstane
jim v paměti ta stará. Předchozí verze se z disku smaže, takže se soubory
nehromadí.

Obrázky leží ve složce nahraných souborů webu (`uploads/kct-og/`) a v knihovně
médií se neobjevují — nejsou to fotky ke vkládání do článků, ale automaticky
generovaný výstup. V síti webů má každý web vlastní složku.

## Když se obrázek nevykreslí

Vykreslování potřebuje na serveru grafickou knihovnu (Imagick) a písma
šablony. Když chybí, karta se nevyrobí a šablona tiše spadne na to, co má —
u aktuality na její náhledový obrázek, u akce na fotku z importu, a když není
ani ta, na obecné logo KČT. Web ani sdílení se tím nerozbijí; sdílecí obrázek
je ozdoba, ne funkce.

Když karty nikde nevznikají, je to na serveru a patří to správci webu, viz
[Řešení problémů](/spravce/reseni-problemu/).

## Proč na to není potřeba plugin

Doplnit náhledové obrázky pro sociální sítě umí i řada pluginů. Na webech KČT
by to ale byla zbytečná vrstva navíc: pluginy pracují s náhledovým obrázkem
příspěvku, kdežto většina akcí je z centrální databáze, vlastní příspěvek nemá
a náhledový obrázek taky ne. Šablona kreslí kartu z **dat akce**, takže
funguje i tam, kde by plugin neměl z čeho vyjít.

Instalovat kvůli sdílecím obrázkům cokoli dalšího proto není potřeba a spíš to
uškodí — dva zdroje značek pro sociální sítě se navzájem přebíjejí. Přehled je
v kapitole [Pluginy, které nepotřebujete](/spravce/zbytecne-pluginy/).

Značky, které se kolem obrázku vypisují do stránky, a strukturovaná data
popisuje [SEO](/funkce/seo/).
