---
title: Propojení s Turinkou
description: Jak web dodává obsah svých akcí do aplikace Turinka, co za to získává zpátky a jak se propojení zapíná.
sidebar:
  order: 10
---

Po přečtení téhle stránky je jasné, co Turinka s akcemi vašeho webu dělá, proč
se jí obsah vyplatí dodávat, jak se přístup zařizuje a co která volba u akce
znamená.

## Proč to existuje

Turinka i váš web čerpají akce ze **stejné centrální databáze KČT**. Bez domluvy
by tedy obě stránky o téže akci soutěžily ve vyhledávání a Google by si jednu
z nich vybral sám — obvykle ne tu vaši.

Domluva zní takto: **když web dodá vlastní text akce a odkaz na svou stránku,
Turinka se u té akce vyhledávačům schová** (dá si `noindex`), odkáže na váš web
a připíše vám autorství. Návštěvnost z hledání tak jde k vám. Když nic nedodáte,
zůstává ve výsledcích Turinka.

Zjednodušeně: **pozice ve vyhledávání je odměna za vlastní práci, ne za to, že
web existuje.**

Zpátky získáte vkládací widget — kus Turinky přímo na stránce vaší akce
s tlačítky Plánovat, Oblíbit a Sdílet, které váš web sám nemá. Ten funguje
i samostatně, bez přístupu.

## Jak se přístup zařídí

1. V **KČT → Nastavení** na záložce *Obecné* vyplňte *Kód oblasti / odboru*,
   pokud tam ještě není. Bez něj nemá Turinka žádost k čemu přiřadit.
2. Na záložce *Turinka* klikněte na **Požádat o přístup**. Odešle se doména
   webu a váš kód.
3. Žádost **schvaluje člověk** na straně Turinky, takže to není hned.
4. Po schválení dorazí přístup sám a stav v nastavení se změní na *Propojeno*.

Když se přístup nepodaří doručit automaticky (typicky u webu, na který se
z internetu nedá dostat), správce Turinky vám pošle **token**. Vložte ho na téže
záložce do pole *Token vložený ručně* a uložte — po ověření se uloží bezpečně
a z toho pole zmizí.

Tlačítkem **Ověřit připojení** si kdykoli zkontrolujete, komu přístup patří
a co smí.

:::caution
Web potřebuje **obě oprávnění** — dodávat obsah k akcím i zakládat vlastní akce.
Když v nastavení vidíte jen jedno, řekněte si správci Turinky o druhé; jinak bude
polovina propojení tiše nefunkční.
:::

## Volba u jednotlivé akce

Jakmile je web propojený, přibude u každé akce v panelu *Data akce* volba
**Akce v Turince**:

| Volba | Co znamená |
|---|---|
| **Neposílat** | Akce zůstane jen na vašem webu. |
| **Veřejně** | Akce je v Turince běžně dohledatelná. |
| **Jen na odkaz (mimo výpisy)** | Akce v Turince je a dá se na ni odkázat, ale nechodí do jejích výpisů. |

Výchozí stav pro akce, u kterých volbu nikdo nenastavil, se nastavuje jednou
v **KČT → Nastavení** na záložce *Turinka*.

:::note
U akcí převzatých z **centrální databáze KČT** se rozdíl mezi *Veřejně* a *Jen na
odkaz* neprojeví. Takové akce jsou celostátně vyhlášené a o jejich viditelnosti
rozhoduje centrální databáze, ne jednotlivý web. Volba u nich funguje jako
„posílat / neposílat".
:::

## Co se odesílá

Odesílá se **text akce z editoru** a **odkaz na její stránku na vašem webu**.
Datum, místo a další údaje akcí z centrální databáze se neposílají — ty drží
centrální databáze a web je přepsat nemůže.

Odeslání proběhne krátce po uložení akce, ne okamžitě. Několik uložení za sebou
se tak slije do jednoho.

**Aby Turinka text přijala, musí mít alespoň 200 znaků** a nesmí být shodný
s tím, co už u akce má. Kratší text se neuloží a u akce se objeví upozornění —
je to cena za to, že se Turinka u vaší akce vzdá místa ve vyhledávání.

Akce **bez vlastního textu se neodesílá vůbec**, protože není co dodat.

## Když akci odpublikujete

- **Akce z centrální databáze** — Turince se vezme zpátky odkaz na váš web.
  Akce se tím vrátí do výsledků vyhledávání Turinky, protože stránka na vašem
  webu už neexistuje. Text v Turince zůstává.
- **Vlastní akce** — označí se v Turince jako **zrušená**. Smazat ji nelze:
  lidé už si ji mohli přidat do plánu a Turinka jim zrušení ohlásí. Když akci
  zase publikujete, zrušení se odvolá.

## Přehledy v administraci

Po propojení přibude v levém menu položka **KČT** se dvěma stránkami:

- **Trasy v Turince** — trasy, které aplikace vede pod vaší organizací: název, stav, délka, převýšení, obtížnost, jestli k nim web odkázal článek a kdy se naposled změnily;
- **Místa v Turince** — totéž pro místa, místo délky a převýšení kategorie a obec. Nové místo vzniká ke schválení, takže chvíli po založení bývá ve stavu *Čeká na schválení*.

Názvy stavů, obtížností a kategorií píše sama Turinka, takže v administraci stojí totéž, co je vidět v aplikaci.

Pod názvem je napsáno, co s položkou smíte dělat — *vlastní*, *spravujete za odbor* (obsah k ní smíte dodávat jen do chvíle, než ho dodá web odboru) nebo *spárovaná*. Název je odkaz do Turinky, kde se položka upravuje; web je odsud jen ukazuje.

Nad tabulkou se dá hledat a filtrovat, tlačítkem **Načíst znovu** si vynutíte čerstvá data (jinak se drží deset minut). Když aplikace neodpoví, zůstane poslední známý stav a nahoře hláška.

### Založení a úprava přímo z přehledu

Tlačítkem **Založit trasu v Turince** (u míst *Založit místo v Turince*) se nad tabulkou otevře formulář aplikace. Vyplní se v něm všechno, co k trase patří — u nové trasy hlavně soubor GPX, ze kterého se délka a převýšení spočítají samy — a po uložení se tabulka načte znovu i s novou položkou. Z webu nikam neodcházíte.

U položky, kterou web založil, je v řádku odkaz **Upravit v Turince**; otevře tentýž formulář s vyplněnými daty. Položky, které web nezaložil (třeba akce odboru nebo místo od někoho jiného), takový odkaz nemají — ty se upravují přímo v aplikaci, odkaz **Otevřít v Turince** vede na ně.

Místo vzniká ke schválení, takže se po uložení objeví ve stavu *Čeká na schválení* a zveřejní ho až správce Turinky.

Obrázek mapy se stopou si Turinka u nové trasy vykresluje až po uložení, takže na kartě ve výpisu chvíli chybí — výškový profil a čísla tam jsou hned.

### Odebrání z Turinky

U položky, kterou web založil, je v řádku i červené **Odebrat z Turinky**. Zeptá se na potvrzení a pak podle toho, co položka je:

- **vlastní položku Turinka schová** — lidé ji přestanou vidět, ale nesmaže se; když ji přes web založíte znovu se stejným názvem, vrátí se i s daty;
- **u připojené cizí položky** se web jen odpojí a položka v aplikaci zůstane.

Co se stalo, napíše hláška nad tabulkou. Položky, které web nezaložil, odebrat nejdou — ty patří někomu jinému.

Přehled akcí je jinde — u akcí, protože ty web drží i jako příspěvky; viz [Akce](/funkce/akce/).

## Trasy u akce

Na stránce akce je v postranním panelu rámeček **Trasy v Turince**. Je vidět,
po kterých trasách akce vede, a dají se odsud vazby přidávat i rušit — v
Turince se pak trasa u akce ukáže a akce u trasy.

Vyhledávací pole hledá **ve všech trasách, které Turinka ukazuje**, ne jen ve
vašich: k vlastní akci smíte přivázat i trasu, kterou založil někdo jiný.
Kliknutím na nalezenou trasu vazba vznikne, křížkem u navázané trasy zase
zmizí — bez potvrzování, protože ani jedno nic nemaže; mizí jen vazba.

Rozhoduje, **čí je akce**, ne čí je trasa. U akce, která vašemu webu nepatří,
je rámeček jen ke čtení s vysvětlením — vazby k ní spravuje její pořadatel. A
dokud Turinka akci nezná, je v rámečku jen věta, že se k ní trasy přivázat
nedají; jakmile ji zná, pole se objeví.

Tlačítkem **Založit trasu k této akci** se otevře formulář nové trasy a akce
je v něm předem v oddílu *Akce na trase*. Trasa tedy vznikne rovnou navázaná a
není potřeba se k akci vracet.

Rámeček se zobrazuje jen propojenému webu. Bez propojení na Turinku na stránce
akce není.

### Akce bez příspěvku

Stejný panel je i v **Akce → Přehled akcí**: u každé akce, kterou web v Turince
spravuje, je v řádku odkaz *Trasy v Turince*, který panel rozbalí pod řádkem.
Je to jediná cesta, jak trasy přivázat k akci, která na webu vlastní příspěvek
nemá — a většina akcí z databáze KČT ho nemá.

Obsah se načte až při rozbalení, takže se přehled kvůli trasám nezdržuje. U akcí,
které Turinka nezná nebo patří někomu jinému, odkaz není.

### Jak se trasy ukážou na webu

Přivázané trasy se na stránce akce vypíšou samy — **hned pod obsahem a nad
propozicemi**, stejnými kartami jako blok *Trasy z Turinky*: mapa se stopou,
druhy aktivity, délka, převýšení, odhad času, obtížnost a výškový profil.
Odkaz z karty vede do aplikace, protože trasa na webu vlastní stránku nemá.

Nic se nenastavuje: akce, která trasu nemá, výpis nemá taky, a stránka tím
nezmění ani řádek. Výpis má jen propojený web — čtení tras je za přístupem,
na rozdíl od vkládaného widgetu akce.

Změna vazby se na webu projeví hned; jinak se výpis drží čtvrt hodiny
a poslední známý den, aby výpadek aplikace neudělal ze stránky prázdné místo.

## Widget na stránce akce

Na stránce akce se pod obsahem zobrazí rámeček z Turinky s údaji o akci
a tlačítky Plánovat, Oblíbit a Sdílet. Pod ním je odkaz na akci v Turince — ten
tam musí zůstat, je to protihodnota za vložený widget.

**Widget funguje i bez propojení** a je ve výchozím stavu zapnutý. Zobrazování
akcí je v Turince veřejné, takže na ně přístup potřeba není — na rozdíl od
dodávání obsahu. Vypnout ho jde v **KČT → Nastavení** volbou *Widget Turinky
u akcí*; tím zmizí i odchozí odkaz.

Když Turinka akci nezná nebo zrovna neodpovídá, **nezobrazí se nic** a stránka
akce funguje beze změny.

## Předpověď počasí u akce

V postranním sloupci akce se pod jejími údaji ukáže **předpověď počasí** na den
konání — tatáž karta, jakou má akce v Turince. U vícedenní akce je předpověď na
celé její trvání.

Zobrazí se jen tehdy, když akce **teprve bude**, má **souřadnice startu**
a vejde se do **dosahu předpovědi**, tedy nejvýš šestnáct dní dopředu. U starší
akce, u akce bez souřadnic, u akce mimo Česko a u akce, na kterou je ještě brzy,
se nezobrazí nic — místo aby na stránce stála hláška, že předpověď není.

Předpověď **funguje i bez propojení** a je ve výchozím stavu zapnutá. Vypnout ji
jde v **KČT → Nastavení** volbou *Předpověď počasí u akcí*.

## Když akce v Turince chybí

Občas se stane, že akci vlastní stránkou zakládáte ručně, ale Turinka ji už zná
z centrální databáze. V takovém případě u akce uvidíte upozornění s jejím
**číslem v centrální databázi**. Doplňte ho do pole *ID akce z kct-db* — obsah
pak půjde k té existující akci a nevznikne duplicita.
