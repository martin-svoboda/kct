---
title: Ochrana kontaktů
description: Šablona sama zakrývá e-maily a telefony na webu tak, aby je roboti nesbírali, ale lidé je viděli a mohli použít.
sidebar:
  order: 11
---

Po přečtení téhle stránky je jasné, co se na webu děje s e-maily a telefony,
proč vypadají ve zdroji stránky jinak, než jak se zobrazují, a co z toho plyne
pro psaní obsahu.

## Co šablona dělá

Zveřejněný e-mail na webu si dřív nebo později najde program, který prochází
stránky a sbírá adresy pro rozesílání spamu. Šablona proto **žádný kontakt
nevypisuje do stránky čitelně**. Místo adresy nebo čísla se do stránky uloží
zakódovaná podoba a skutečný odkaz z ní složí prohlížeč teprve ve chvíli, kdy
stránku zobrazuje člověk.

Pro čtenáře se tím nemění nic:

- e-mail se zobrazí celý a klepnutím se otevře poštovní program,
- telefon se zobrazí celý a na mobilu se klepnutím vytočí,
- adresu i číslo lze označit a zkopírovat,
- odečítač obrazovky přečte celý údaj.

Kdo má v prohlížeči vypnutý JavaScript, uvidí kontakt také — jen jako text,
bez odkazu.

**Nastavení k tomu žádné není.** Je to bezpečnostní opatření, které běží vždy;
není co zapínat a není co pokazit.

## Kde to platí

Ochrana se použije všude, kde se kontakt na webu objeví:

- v **kontaktních údajích odboru** (telefony a e-maily na stránce odboru, viz
  [Odbory](/funkce/odbory/)),
- v **kontaktu na pořadatele akce** (viz [Akce](/funkce/akce/)),
- v **textu aktualit a stránek** — kontakt napsaný do obsahu je chráněný sám,
  bez jakéhokoli zvláštního zápisu,
- ve **widgetech**, tedy i v patičce, kde kontakt bývá na každé stránce webu.

Platí to i pro e-mail napsaný jako odkaz: šablona z něj udělá chráněný odkaz
na tutéž adresu.

## Co to znamená pro psaní obsahu

**Kontakt se píše normálně.** `jmeno@priklad.cz` nebo `736 754 120`
v odstavci stačí; o zakrytí se stará šablona. Jediné, na co je dobré myslet:

- **Kontakt ve zdrojovém kódu stránky.** Když se část textu v editoru skryje
  do komentáře, zůstane uložená ve stránce a ochrana se jí nedotkne — do
  komentáře nelze chráněný odkaz vložit. Nepoužívaný kontakt je proto lepší
  z obsahu smazat, ne zakomentovat.
- **Popisek pro vyhledávače.** Popisek je čistý text bez odkazů, takže se do
  něj chráněný zápis vložit nedá. U akcí z centrální databáze si s tím
  šablona poradí sama: je-li v textu akce kontakt, ukázku z textu do popisku
  vůbec nevezme. U aktualit a stránek skládá popisek Rank Math z prvních vět
  textu, a tam o ochraně neví — stojí-li adresa nebo číslo v úvodu textu, je
  vhodné popisek vyplnit ručně (viz [SEO](/funkce/seo/)).
- **Obrázek není text.** Adresa vypsaná v obrázku nebo v PDF je mimo dosah
  ochrany i mimo dosah čtenáře, který si ji chce zkopírovat. Patří do textu.

## Čeho ochrana nedosáhne

Zastaví program, který si stránku jen stáhne a prohledá — a to je většina
sběračů adres. **Nezastaví program, který stránku zobrazí jako prohlížeč**;
ten dostane složený odkaz stejně jako člověk. Ochrana sběr zdražuje,
neznemožňuje ho.

Adresy, které už na webu čitelně stály dřív, jsou nejspíš posbírané a ochrana
je zpětně nevrátí. Má smysl u adres nových a změněných — a u webu, který
takhle chráněný je od začátku.

Když některá adresa dostává spamu tolik, že je neúnosná, pomůže jedině ji
vyměnit za novou.
