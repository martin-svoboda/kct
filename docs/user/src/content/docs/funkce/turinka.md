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

1. V **Nastavení → KČT** na záložce *Obecné* vyplňte *Kód oblasti / odboru*,
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
v **Nastavení → KČT** na záložce *Turinka*.

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

## Widget na stránce akce

Na stránce akce se pod obsahem zobrazí rámeček z Turinky s údaji o akci
a tlačítky Plánovat, Oblíbit a Sdílet. Pod ním je odkaz na akci v Turince — ten
tam musí zůstat, je to protihodnota za vložený widget.

**Widget funguje i bez propojení** a je ve výchozím stavu zapnutý. Zobrazování
akcí je v Turince veřejné, takže na ně přístup potřeba není — na rozdíl od
dodávání obsahu. Vypnout ho jde v **Nastavení → KČT** volbou *Widget Turinky
u akcí*; tím zmizí i odchozí odkaz.

Když Turinka akci nezná nebo zrovna neodpovídá, **nezobrazí se nic** a stránka
akce funguje beze změny.

## Když akce v Turince chybí

Občas se stane, že akci vlastní stránkou zakládáte ručně, ale Turinka ji už zná
z centrální databáze. V takovém případě u akce uvidíte upozornění s jejím
**číslem v centrální databázi**. Doplňte ho do pole *ID akce z kct-db* — obsah
pak půjde k té existující akci a nevznikne duplicita.
