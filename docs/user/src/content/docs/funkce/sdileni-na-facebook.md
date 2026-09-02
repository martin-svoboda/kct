---
title: Sdílení na Facebook
description: Jak šablona sama odesílá aktuality a pozvánky na akce na facebookovou stránku odboru a co se u toho dá nastavit.
sidebar:
  order: 7
---

Po přečtení téhle stránky je jasné, jak web sám odesílá aktuality a pozvánky na
akce na facebookovou stránku odboru, kde se to nastavuje a jak se sdílení dá
u jednotlivého příspěvku vypnout nebo poslat ručně.

## Co funkce dělá

Šablona umí publikovat na facebookovou **stránku** odboru — ne na osobní profil
a ne do skupiny. Odesílá dvě věci:

- **aktuality** krátce po jejich publikování na webu,
- **pozvánky na akce** s předstihem před termínem konání, ne hned po vložení
  akce do kalendáře.

Příspěvek se posílá jako fotka se sdílecím obrázkem, který si šablona sama
vykreslí (viz [Sdílecí obrázky](/funkce/sdileci-obrazky/)). Odkaz na web je
u fotopříspěvku součástí textu, protože u fotky Facebook klikací náhledovou
kartu nezobrazuje. Když fotku z nějakého důvodu odmítne, odešle se místo ní
obyčejný odkaz, aby sdílení proběhlo aspoň takhle.

Nic se neodešle u příspěvku chráněného heslem — jeho obsah je záměrně neveřejný.

## Nastavení

Všechno je v administraci pod **Nastavení → KČT**, v části **Sdílení na
Facebook**.

### ID Facebook stránky

Číselné ID stránky, na kterou se budou příspěvky odesílat.

### Page access token

Dlouhodobý token stránky z aplikace v Metě. Bez ID stránky i tokenu se
neodešle nic a v editoru se nezobrazí ani ovládání sdílení u jednotlivých
příspěvků — nemá smysl slibovat něco, co se stejně nestane.

Kde token vzít a proč ho radši nedržet v databázi, řeší
[Bezpečnost](/spravce/bezpecnost/) a poslední oddíl téhle stránky.

### Sdílet automaticky — aktuality

Přepínač, který určuje, jestli mají **nové aktuality** ve výchozím stavu
zapnuté sdílení. U už existující aktuality nic nemění — jen předvyplňuje
přepínač u nově vytvořené.

### Sdílet automaticky — akce

Totéž pro akce. Aktuality a akce jsou dva druhy obsahu a redakce u nich může
chtít jiné chování, proto jsou přepínače dva.

Tenhle přepínač má navíc jeden účinek navíc: řídí i odesílání akcí z centrální
databáze, které vlastní příspěvek nemají. Když je vypnutý, neodejde z nich
žádná — podrobněji v oddílu Akce z centrální databáze níž.

### Kolik dní před akcí odeslat

S jakým předstihem před začátkem akce má pozvánka odejít. Výchozí hodnota je
**12 dní** a není to kulaté číslo náhodou — je to volba podle dne v týdnu:

- akce v **sobotu** → pozvánka odejde v **pondělí**,
- akce v **neděli** → pozvánka odejde v **úterý**.

Většina akcí je o víkendu a zveřejňovat pozvánku o víkendu je špatně: lidé
plánují další víkend na začátku toho týdne. Dvanáctka to trefí. Kdo tohle číslo
mění, mění tím zároveň den v týdnu, na který odeslání padne — vyplatí se
zůstat u násobku sedmi plus nebo minus pár dní, ne u „hezčí" desítky.

Nula znamená odeslat v den akce. Povolený rozsah je 0 až 365 dní.

Akce, která začíná dřív, než je nastavený odstup, se odešle hned. Akce, která
už proběhla, se neodešle vůbec — pozvánka na loňský pochod je horší než žádný
příspěvek.

### V kolik hodin odeslat

Hodina, ve které se pozvánka na akci odesílá; výchozí je **9**. Bez ní by
pozvánka odešla v tu denní dobu, kdy byla akce náhodou publikovaná — tedy klidně
v jednu ráno. Zadává se celé číslo 0 až 23.

Hodina se týká jen akcí. Aktuality odcházejí krátce po publikování, ne v pevný
čas.

### Ověřit připojení

Tlačítko, které zkusí spojení s Facebookem a vypíše název stránky, ke které
token patří. Je to nejrychlejší způsob, jak zjistit, že se ID a token opravdu
párují a že token ještě platí. Vyplatí se ho zmáčknout po každé výměně tokenu.

## Ovládání u jednotlivého příspěvku

Když je sdílení nastavené, přibudou v editoru aktuality i akce v pravém
sloupci dva boxy.

### Box Facebook

- **Sdílet na Facebook** — přepínač. Předvyplňuje se podle nastavení webu
  (Sdílet automaticky), ale u konkrétního příspěvku se dá kdykoli přepnout.
  Vypnutý přepínač znamená, že se příspěvek neodešle.
- **Text příspěvku** — vlastní text pro Facebook. Když zůstane prázdný, složí
  se text automaticky.
- **Kolik dní předem** — jen u akcí. Přepíše počet dní z nastavení webu pro
  tuhle jednu akci. Prázdné pole znamená nastavení webu.

### Box Facebook — stav odeslání

Ukazuje, co se s příspěvkem stalo nebo teprve stane:

- **kdy se odešle** — u akce, která na odeslání čeká („Odešle se 5. 9. 2026
  v 9:00.", „Odešle se během několika minut.", nebo „Akce už proběhla, na
  Facebook se neodešle.");
- **datum odeslání a odkaz** na hotový příspěvek, když už odešel;
- **chybu**, když se odeslání nepovedlo, spolu s tlačítkem **Zkusit znovu**.

U aktuality se box objeví, až když je co ukázat — tedy po odeslání nebo po
neúspěšném pokusu. U akce se ukáže hned, protože i „kdy se odešle" je
informace, kvůli které vznikl.

Box se po uložení sám nepřekresluje a odesílání běží mimo právě otevřenou
stránku — aktuální stav ukáže až obnovení editoru.

## Jak vypadá odeslaný text

Pokud redaktor nevyplnil vlastní text, složí se automaticky.

U **aktuality** je to titulek a pod ním perex, zkrácený na zhruba tři sta
znaků na hranici slova. Krátká aktualita, která nemá vlastní detailní stránku,
se neseká vůbec a odchází bez odkazu — nebylo by kam odkazovat.

U **akce** má text pevný tvar:

```
Název akce
Kdy: sobota 5. 9. 2026, 8:00
Kde: Rakovník

Perex nebo popis akce…
```

Datum se skloňuje podle českého zvyku (den v týdnu malým písmenem). Když je
datum v datech z importu nesmyslné, vypíše se tak, jak je — ať je vidět, že je
potřeba ho opravit, místo aby beze stopy zmizelo.

## Akce z centrální databáze

Většina akcí, které web vypisuje, pochází z centrální databáze KČT a **vlastní
příspěvek nemá** — na sokct.cz je to řádově dvanáct akcí z tří set. Kdyby
sdílení uměl jen editor příspěvku, drtivá většina pozvánek by nikdy neodešla.

Tyhle akce proto odesílá samostatná denní úloha. Spouští se v hodinu nastavenou
v poli **V kolik hodin odeslat**, projde akce, kterým právě nastal den odeslání,
a pošle je. Kdyby se úloha některý den nespustila (běh plánovaných úloh závisí
na návštěvnosti webu, na hodně tichém webu se může o něco opozdit), dožene
i akce, kterým den odeslání nastal včera nebo předevčírem. Starší se samy
neodešlou — díky tomu se při prvním zapnutí funkce nevysype na facebookovou
stránku celá historie.

Ovládání takové akce není v administraci, ale **přímo na její stránce na webu**
— přihlášenému redaktorovi se pod detailem akce ukáže řádek se stavem a odkazy:

- kdy se akce odešle („Na Facebook se odešle 24. 8. 2026."), případně „Termín
  odeslání už uplynul — pošlete ručně.";
- **Nesdílet** / **Sdílet** — vypne nebo znovu zapne odeslání téhle jedné akce;
- **Odeslat hned** — pošle ji okamžitě, bez čekání na denní úlohu.

Když už akce odešla, je na řádku místo toho potvrzení a odkaz na příspěvek na
Facebooku.

Akce, která má vlastní příspěvek, se touhle cestou neodesílá — postará se o ni
běžné sdílení z editoru, jinak by odešla dvakrát.

## Když se odeslání nepovede

- **Facebook vrátí chybu** — pokus se sám zopakuje třikrát, po pěti minutách,
  po půl hodině a po dvou hodinách. Chyba zůstane vypsaná v boxu stavu.
- **Spojení se nedokončí** (výpadek sítě, vypršel časový limit) — pokus se sám
  **neopakuje**. Požadavek totiž mohl na straně Facebooku uspět a opakování by
  přidalo na zeď druhý stejný příspěvek. Box na to upozorní: než se použije
  tlačítko „Zkusit znovu", je potřeba se podívat na stránku na Facebooku,
  jestli tam příspěvek náhodou není.
- **Token je neplatný nebo vypršel** — sdílení nefunguje na celém webu, ne
  jen u jednoho příspěvku, takže se místo opakování objeví upozornění
  v administraci s odkazem na nastavení. Zmizí, jakmile se s novým tokenem
  povede odeslat první příspěvek.

Další rady jsou v kapitole [Řešení problémů](/spravce/reseni-problemu/).

## Kam patří token

Token vyplněný v **Nastavení → KČT** leží v databázi webu. To znamená, že se
načítá při každém požadavku na web a že je v každé záloze databáze — kdokoli se
k záloze dostane, dostane se i k oprávnění publikovat na facebookovou stránku
odboru.

Bezpečnější je držet token mimo databázi, v konstantě `KCT_FB_PAGE_TOKEN`
v souboru `wp-config.php`. Když je konstanta nastavená, má přednost, pole
v nastavení se přepne na needitovatelnou informaci a dřív uložená hodnota se
z databáze rovnou smaže.

Do `wp-config.php` se z administrace nedostane nikdo — je to práce pro toho, kdo
má přístup k souborům webu na serveru, ne pro redaktora. Postup je
v kapitole [Bezpečnost](/spravce/bezpecnost/).
