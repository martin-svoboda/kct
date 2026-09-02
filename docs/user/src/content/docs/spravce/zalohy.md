---
title: Zálohy
description: Co všechno se ze webu zálohuje, kdo zálohy pořizuje a kdy si stáhnout vlastní.
sidebar:
  order: 4
---

Po přečtení téhle stránky bude jasné, co je ze zálohy možné obnovit, kdo ji
pořizuje a v jaké situaci se vyplatí stáhnout si vlastní kopii obsahu.

## Z čeho se web skládá

Úplná záloha webu má dvě části a jedna bez druhé je k ničemu:

- **databáze** — texty stránek a aktualit, vlastní akce, nastavení, menu,
  uživatelské účty a jejich role,
- **soubory** — nahraná média (knihovna médií), plugin, šablona a konfigurace.

Kdo zálohy pořizuje, záleží na tom, kde web běží. **Na samostatném webu** je to
věc správce: většina hostingů zálohuje sama a stojí za to zjistit, jak často a
jak dlouho se zálohy drží; jinak zbývá zálohovací plugin nebo ruční kopie
databáze a nahraných souborů. **Je-li web součástí spravované sítě**, zálohy
celého serveru pořizuje provozovatel sítě a odbor je neřeší.

Obnova ze zálohy je vždycky zásah do celého webu, ne do jedné stránky — vrací se
stav k určitému okamžiku a novější změny se ztratí.

## Co je nenahraditelné

Plugin i šablonu lze kdykoli nainstalovat znovu, akce z centrální databáze KČT
se doimportují samy. Skutečně nenahraditelná je **knihovna médií** a texty:
fotky z akcí nikde jinde neexistují a nikdo je znovu nevyfotí. Tomu odpovídá i
opatrnost při zacházení s nimi — hromadné mazání v knihovně médií je nevratné,
protože smazaný soubor koš nepoužívá.

## Vlastní záloha před velkým zásahem

Před zásahem, který se těžko vrací zpátky — hromadným mazáním médií,
přejmenováním kategorií, přestavbou struktury stránek, změnou domény — je vhodné
stáhnout si vlastní zálohu obsahu:

1. **Nástroje → Exportovat** vytvoří soubor XML se všemi příspěvky, stránkami a
   akcemi. Média v něm ale nejsou, jsou v něm jen odkazy na ně.
2. Nahrané soubory se stahují zvlášť: na samostatném webu složku s nahranými
   soubory přes FTP nebo souborového správce hostingu, na spravované síti je
   potřeba o ni požádat provozovatele. Jednotlivé fotky se dají stáhnout i
   ručně z knihovny médií.

Zálohu je vhodné stáhnout před zásahem, ne až po něm, a nechat si ji do doby,
než se ověří, že je web v pořádku.
