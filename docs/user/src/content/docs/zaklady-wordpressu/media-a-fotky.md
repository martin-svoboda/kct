---
title: Média a fotky
description: Jak se do webu nahrávají fotky a co s nimi šablona KČT automaticky udělá.
sidebar:
  order: 3
---

Po přečtení téhle stránky je jasné, jak se fotka dostane na web, co se s ní při nahrání automaticky stane a proč — a jak vyplnit alternativní text a popisek.

## Knihovna médií

Všechny nahrané soubory — fotky, PDF, GPX — leží na jednom místě: v levém menu administrace pod **Média → Knihovna**. Odtud se dají prohlížet, přejmenovávat, mazat a dá se z nich zkopírovat adresa pro odkaz.

Soubory `.gpx` se záznamem trasy WordPress ve výchozím stavu odmítá jako neznámý typ. Šablona je povoluje, takže se nahrají stejně jako obrázek nebo PDF a dá se na ně odkázat.

Nahrát soubor jde třemi způsoby:

- v **Média → Přidat nový soubor**,
- rovnou v editoru při vkládání bloku Obrázek nebo Galerie,
- přetažením souboru z počítače do okna prohlížeče.

Knihovna patří vždy jen tomu jednomu webu. Je-li web součástí sítě více webů, weby ostatních odborů do ní nevidí a naopak.

## Co se s fotkou při nahrání stane

Tohle je na šabloně KČT nezvyklé a vyplatí se to vědět předem. Fotka se po nahrání **automaticky upraví** — ještě než se objeví v knihovně:

1. **Zmenší se na 2048 pixelů delší strany**, pokud je větší.
2. **Převede se do formátu WebP**, pokud jde o JPEG.
3. **Vymaže se z ní technický popis fotoaparátu.**

Úprava je automatická a nedá se u jednotlivého souboru vypnout. Platí pro všechny nově nahrávané soubory.

### Proč zmenšení na 2048 pixelů

Největší velikost, ve které web fotku kdy zobrazí, je 2048 pixelů. Cokoliv nad to už na obrazovku nikdy nedoputuje a čtenáři se to jen stáhne do prohlížeče.

WordPress sám má strop nastavený na 2560 pixelů. Rozdíl není zanedbatelný: běžný snímek z dnešního fotoaparátu má kolem 5184 × 3456 bodů a ve 2560 pixelech zabere zhruba 1 MB, kdežto ve 2048 pixelech 585 kB. Skoro polovina objemu, kterou nikdo neuvidí.

Zmenšení se týká jen rozměrů obrazovky, ne kvality — 2048 pixelů je pořád víc, než má většina monitorů na šířku.

### Proč WebP

WebP je novější obrazový formát, kterému rozumí všechny dnešní prohlížeče. Při stejném rozlišení a prakticky stejné kvalitě vyjde znatelně menší. Naměřeno na fotce z knihovny: velikost používaná v článku měla 278 kB jako JPEG a 174 kB jako WebP — o 37 % méně při shodných rozměrech.

Převádí se **jen fotky**, tedy soubory JPEG. Obrázky ve formátu PNG zůstávají, jak jsou: na webu to bývají loga, ikony a schémata s ostrými hranami a plochými barvami, a právě na nich by komprese udělala viditelné šmouhy. Logo tedy má smysl nahrávat jako PNG.

Formát se změní i v názvu souboru — z `dscn1234.jpg` se stane `dscn1234.webp`. Odkaz na obrázek vytvořený z knihovny na to bere ohled sám, jen se nedá spoléhat na to, že si adresu souboru někdo zapamatuje dopředu.

### Proč se maže technický popis fotoaparátu

Fotoaparát ukládá do každého snímku technická data: model přístroje, čas expozice, clonu, často i souřadnice místa. Grafické programy k tomu přidávají barevné profily. Pro čtenáře webu z toho není užitečné nic, ale objem to dělá — WordPress ta data kopíruje i do všech zmenšenin, takže se jeden nahraný snímek propíše do víc než deseti souborů.

V knihovně KČT tvořila taková data dohromady 84 MB a u některých náhledů zabírala 92 % celého souboru. Extrémem bylo logo o rozměrech 300 × 53 bodů, kde 551 kB z 557 kB připadalo na tiskový barevný profil a na vlastní obrázek zbývalo 6 kB.

Mazání je **bezztrátové** — přepisuje se jen obal souboru, obrazová data se vůbec nedotknou. U souborů, kde by odstranění barevného profilu posunulo barvy, se profil ponechá.

Vedlejší efekt, který stojí za zmínku: se souřadnicemi z fotoaparátu zmizí i informace, kde přesně byl snímek pořízen. Na webu odboru je to spíš dobře.

### Otočení fotek zůstává zachované

Fotka nafocená na výšku má správnou orientaci uloženou právě v těch technických datech. Kdyby se smazala hned, fotky na výšku by se na webu objevily položené na bok. Úklid proto běží až ve chvíli, kdy si WordPress orientaci přečte a zmenšeniny podle ní natočí. Fotky na výšku tedy zůstávají na výšku.

## Starší fotky se zpětně nepřevádějí

Všechno výše platí **jen pro nově nahrávané soubory**. Co v knihovně leží z dřívějška, zůstává beze změny.

Není to opomenutí, je to vědomé rozhodnutí. Fotka uložená jako JPEG už jednou prošla ztrátovou kompresí. Její převod do WebP by znamenal kompresi podruhé, tedy další úbytek kvality — a měření ukázalo, že zisk v bajtech za tu ztrátu nestojí. U části souborů by navíc výsledek vyšel dokonce větší než předloha.

U nově nahrávané fotky je situace jiná: kóduje se z nedotčené předlohy z fotoaparátu, takže se komprimuje poprvé a WebP jasně vyhrává.

Váhu starších stránek řeší šablona jinak — posílá do stránky menší připravenou velikost místo plné fotky.

## Původní soubor se neuchovává

Když WordPress fotku zmenší, ukládá si vedle zmenšeniny i původní nezmenšený soubor, který ale nikdy nikomu neposílá. V síti KČT takhle leželo 673 nepoužívaných originálů za 1,7 GB, které jen nafukovaly zálohy. Šablona proto po nahrání plnou verzi maže.

Prakticky to znamená jedno: **knihovna médií není fotoarchiv odboru.** Originály z fotoaparátu patří do vlastního úložiště nebo na disk, web slouží k jejich zobrazení. Zpátky z webu se plné rozlišení nedostane.

## Alternativní text a popisek

Po výběru obrázku se v panelu vpravo objeví dvě textová pole, která se často nechávají prázdná zbytečně.

**Alternativní text** popisuje, co je na obrázku, pro toho, kdo ho nevidí — pro čtečku obrazovky nevidomého návštěvníka a pro prohlížeč, kterému se obrázek nenačetl. Čtou ho i vyhledávače. Píše se stručně a věcně: „Skupina turistů na rozcestí pod Ještědem“, ne „foto“, ne „DSC_0042“. U čistě dekorativního obrázku se nechává prázdný.

**Popisek** je krátký text, který se zobrazí pod obrázkem přímo na stránce a čte ho každý. Hodí se na jméno místa, datum nebo autora fotky. Není povinný.

Obojí se dá vyplnit i v knihovně médií u samotného souboru; pak se předvyplní všude, kde se fotka použije.

## Doporučení

- **Nahrávat originály z fotoaparátu, ne předem zmenšené soubory.** Zmenšování v grafickém programu a následné zpracování na webu znamená dvojí kompresi a viditelně horší výsledek. Web si zmenšení udělá sám, a to z plnohodnotné předlohy.
- Nemá smysl fotky předem ořezávat na nějaký „webový“ rozměr. Potřebné velikosti si WordPress vyrobí sám.
- Loga a schémata nahrávat jako PNG, fotky jako JPEG.
- Soubory pojmenovávat srozumitelně ještě v počítači. `vylet-jested-2025.jpg` se v knihovně po roce hledá líp než `IMG_4821.JPG`.
- V knihovně průběžně mazat, co se nikde nepoužívá.

Obrázky, které se zobrazují při sdílení odkazu na Facebooku, mají svoje vlastní pravidla — viz [Sdílecí obrázky](/funkce/sdileci-obrazky/).
