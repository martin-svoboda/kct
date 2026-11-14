---
title: Aplikace v Metě pro sdílení
description: Jak vzniká aplikace, která odboru vydá token k publikování na facebookovou stránku, a proč se musí zveřejnit, než začne být obsah vidět.
sidebar:
  order: 6
---

Po přečtení téhle stránky bude jasné, proč se bez vlastní aplikace v Metě na
Facebook publikovat nedá, co všechno musí být vyplněné, a hlavně proč se
aplikace musí **zveřejnit** — bez toho příspěvky odcházejí, ale nikdo kromě
hrstky lidí je neuvidí.

Tahle část je jednorázová. Udělá se při zapínání sdílení a pak se k ní správce
vrátí nanejvýš kvůli novému tokenu.

## Proč je aplikace potřeba

Facebook nikomu nedovolí publikovat na stránku „jen tak". Příspěvek za stránku
smí odeslat jedině aplikace registrovaná v Metě, a ta musí držet **token
stránky** — přístupový klíč, který plugin posílá s každým požadavkem.

Token vydává právě aplikace. Nejde si ho nikde opsat a nedá se použít cizí:
aplikace, stránka a token patří k sobě.

Předpokladem je, že odbor má na Facebooku **stránku**, ne osobní profil a ne
skupinu — na profil ani do skupiny plugin publikovat neumí — a že ji ten, kdo
aplikaci zakládá, spravuje.

## Co se v aplikaci nastavuje

Aplikace se zakládá v Meta for Developers. Její nastavení je rozdělené do
několika sekcí a pro sdílení z webu jsou podstatné tyhle:

- **Případ použití** — správa stránek. Tím se aplikaci přiřadí oprávnění
  k publikování na stránku a ke čtení jejího názvu. Žádná další oprávnění
  šablona nepoužívá.
- **Nastavení → Základní** — ikona aplikace, kategorie, doména webu, adresa
  zásad ochrany osobních údajů a adresa s postupem pro smazání údajů. Dokud
  tyhle údaje chybí, aplikaci nejde zveřejnit.
- **Platforma** — ve stejné sekci úplně dole se přidá platforma *Website*
  s adresou webu odboru.
- **Ověření** — Meta chce ověřit organizaci, která za aplikací stojí.
  Vyřizuje se doložením údajů o spolku a chvíli to trvá.

Zásady ochrany osobních údajů musí být na webu skutečně dostupné a musí
odpovídat tomu, co web dělá. Meta si je otevírá a kontroluje.

## Zveřejnění aplikace

**Tohle je krok, na kterém to nejčastěji uvázne.**

Nová aplikace je ve vývojovém režimu, v přehledu označená jako *Unpublished*.
V tom stavu funguje zdánlivě všechno: plugin příspěvek odešle, Facebook vrátí
úspěch, v editoru se objeví datum odeslání i odkaz na hotový příspěvek. Odkaz
se otevře a příspěvek na zdi stránky opravdu je.

Jenže **vidí ho jen lidé, kteří mají v té aplikaci přidělenou roli** — tedy
zpravidla jediný člověk, který ji zakládal. Pro všechny ostatní, přihlášené
i nepřihlášené, stránka vypadá, jako by na ní byly jen ručně vložené
příspěvky.

Z administrace webu se to nepozná nijak. Plugin hlásí úspěch po pravdě, protože
Facebook mu úspěch opravdu vrátil.

Náprava je jedno tlačítko: v sekci **Review → Zveřejnit** aplikaci zveřejnit.
Účinek je okamžitý a týká se i příspěvků odeslaných dřív — ty se zveřejněním
zviditelní zpětně.

## Kontrola aplikace není potřeba

Meta vedle toho nabízí **Kontrolu aplikace** (App Review) — posouzení člověkem,
k němuž se vyplňují odůvodnění oprávnění, dotazník o nakládání s daty a nahrává
se video s ukázkou funkce.

K publikování na vlastní stránku to potřeba není. Oprávnění ve výchozím,
standardním rozsahu na to stačí; kontrola slouží k rozšířenému přístupu, který
by aplikace potřebovala, kdyby publikovala na stránky cizích lidí.

Stojí za to to vědět předem — je to několik hodin práce, kterou si lze ušetřit.

## Token

Z hotové aplikace se vygeneruje **dlouhodobý token stránky** a vloží do webu.
Kam přesně a proč ne do databáze, popisuje stránka
[Bezpečnost](/spravce/bezpecnost/); co se s ním pak na webu nastavuje, stránka
[Sdílení na Facebook](/funkce/sdileni-na-facebook/).

Po vložení tokenu se v **KČT → Nastavení** na záložce *Facebook* zmáčkne
**Ověřit připojení**. Vypíše se název stránky, ke které token patří — tím je
ověřené, že token, ID stránky i aplikace patří k sobě.

## Jak ověřit, že je obsah opravdu vidět

Vlastní účet k tomu neposlouží. Kdo aplikaci zakládal, vidí její příspěvky vždy,
ať je aplikace zveřejněná, nebo ne — a právě to ten problém tak dobře schovává.

Spolehlivá kontrola je jedna: otevřít stránku odboru v **anonymním okně
prohlížeče**, tedy odhlášeně. Co je vidět tam, vidí každý. Druhá možnost je
zeptat se někoho, kdo v aplikaci roli nemá.
