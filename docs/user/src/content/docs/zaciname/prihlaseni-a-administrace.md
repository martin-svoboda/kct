---
title: Přihlášení a administrace
description: Jak se dostat do administrace webu a co se skrývá pod jednotlivými položkami levého menu.
sidebar:
  order: 2
---

Po přečtení téhle stránky bude jasné, kudy se do administrace webu přihlásit a
kde v ní hledat to, co je potřeba upravit.

## Přihlášení

Administrace se otevírá na adrese webu doplněné o `/wp-admin/` — tedy
`https://adresa-webu-odboru/wp-admin/`. Kdo přihlášený není, přesměruje se na
přihlašovací formulář. Zadává se **uživatelské jméno nebo e-mail** a
**heslo**.

Volba **Pamatovat si mě** drží přihlášení i po zavření prohlížeče. Na sdíleném
nebo veřejném počítači se nezaškrtává.

Účty zakládá administrátor webu; je-li web součástí sítě více webů, zakládá
je správce sítě. Sám se na webu odboru nikdo zaregistrovat nemůže.

## Zapomenuté heslo

Pod přihlašovacím formulářem je odkaz **Zapomněli jste heslo?** Po zadání
uživatelského jména nebo e-mailu přijde do schránky zpráva s odkazem na
nastavení nového hesla. Odkaz má omezenou platnost — když vyprší, stačí si o
nový požádat znovu.

Pokud e-mail nedorazí ani po několika minutách, stojí za to podívat se do
složky s nevyžádanou poštou. Když ani tam není, řeší se to s administrátorem
webu — v síti více webů se správcem sítě.

## Odhlášení

Vpravo nahoře v horní liště je jméno přihlášeného uživatele. Po najetí na něj
se rozbalí nabídka s položkami **Upravit profil** a **Odhlásit se**.

## Levé menu administrace

Levý sloupec je hlavní rozcestník. Na webu odboru jsou v něm zpravidla tyto
položky:

- **Nástěnka** — úvodní obrazovka po přihlášení, přehled a upozornění.
- **Příspěvky** — aktuality webu. Ve WordPressu se tomuto typu obsahu říká
  příspěvky, na webu odboru se zobrazují jako aktuality. Podrobnosti popisuje
  stránka [Stránky a aktuality](/zaklady-wordpressu/stranky-a-aktuality/).
- **Akce** — turistické akce. Část se jich načítá z centrální databáze KČT,
  část se zadává ručně. Viz [Akce](/funkce/akce/).
- **Média** — knihovna nahraných fotek a souborů. Viz
  [Média a fotky](/zaklady-wordpressu/media-a-fotky/).
- **Stránky** — trvalý obsah, který se nemění tak často jako aktuality: o
  odboru, kontakty, historie.
- **Komentáře** — diskuse pod příspěvky, pokud jsou na webu povolené.
- **Vzhled** — podpoložky **Přizpůsobit** (styl webu, logo, barva),
  **Menu** a **Widgety**.
- **Uživatelé** — účty a jejich role. Viz
  [Role uživatelů](/zaciname/role-uzivatelu/).
- **Nástroje** — pomocné funkce WordPressu, běžně se do nich nechodí.
- **Nastavení** — obecné nastavení webu a podpoložka **KČT**.

K nim může přibýt položka **Odbory**. Seznam odborů je vedený na jednom
místě: na samostatném webu se spravuje přímo tam, a je-li web součástí sítě
více webů, jen v administraci hlavního webu sítě. Na podřízeném webu odboru se
tedy v levém menu neobjeví, i když se obsah odborů na webu zobrazuje. Co
položka obsahuje, popisuje stránka [Odbory](/funkce/odbory/).

## Horní lišta a přepínání mezi webem a administrací

Tmavý pruh úplně nahoře je vidět v administraci i na samotném webu, dokud je
uživatel přihlášený. Slouží k přeskakování mezi obojím:

- Vlevo je **název webu**. Z administrace pod ním vede odkaz **Zobrazit web**,
  z webu naopak odkaz zpět do **Nástěnky**.
- Vedle je nabídka pro rychlé přidání obsahu. V české administraci je označená
  **+ Akce** — jde o obecnou zkratku pro přidání příspěvku, stránky, média
  nebo uživatele, s turistickými akcemi nesouvisí.
- Při prohlížení konkrétní stránky nebo příspěvku na webu se v liště objeví
  odkaz **Upravit**, který otevře daný obsah rovnou v editoru. Je to nejrychlejší
  cesta k opravě překlepu.

## Nastavení → KČT

Vše, co je specifické pro šablonu KČT, je pohromadě v **Nastavení → KČT**.
Zadává se tam kód odboru nebo oblasti a nastavuje sdílení na Facebook.

Do téhle stránky se dostane jen uživatel s rolí administrátora — proč to tak
je, vysvětluje [Role uživatelů](/zaciname/role-uzivatelu/).

Jednotlivá pole popisují stránky [Akce](/funkce/akce/) a
[Sdílení na Facebook](/funkce/sdileni-na-facebook/). Co vyplnit hned na
začátku, shrnuje [První nastavení webu](/zaciname/prvni-nastaveni/).
