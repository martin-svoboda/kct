---
title: Trasy a místa z Turinky
description: Dva bloky, které vypíšou trasy nebo místa přímo z aplikace Turinka.
sidebar:
  order: 8
---

Na konci téhle stránky je jasné, jak na stránku dostat výpis tras nebo míst
z Turinky a co jde nastavit.

## K čemu bloky jsou

**Trasy z Turinky** a **Místa z Turinky** vypíšou karty přímo z aplikace. Web
si je nikam neukládá — data patří Turince a web je jen ukazuje, takže jsou
vždycky aktuální a nemusí se nic importovat.

Karta ukazuje všechno, co o položce výpis nese:

- **trasa** — mapa se stopou, druhy aktivity s ikonou, délka, nastoupané
  převýšení (u trasy z místa na místo i klesání), odhad času, obtížnost, malý
  výškový profil, krátký popis, vhodnost (třeba *Pro rodiny s dětmi*),
  sezónnost a kolik lidí si ji uložilo mezi oblíbené;
- **místo** — fotka s uvedením autora, kategorie, přístupnost, obec a okres,
  krátký popis.

Doporučená trasa nebo tip na místo má v rohu obrázku odznak **Tip**.

**Odkazy z karet vedou do Turinky**, protože trasy a místa nemají na webu
vlastní stránku. Je to na kartě vidět: ikonka u názvu a dole v kartě logo
aplikace s větou *Otevře se v Turince*. Odkaz se otevírá v novém panelu.

Patička s logem sedí vždy u spodní hrany karty, takže i při různě dlouhých
popisech jsou karty v řádku zarovnané. **Kliknout jde kamkoli do karty**, ne
jen na název.

Bloky se v editoru nabízejí **jen na webu propojeném s Turinkou**, viz
[Propojení s Turinkou](/funkce/turinka/).

## Nastavení

V postranním panelu bloku:

- **Co vypsat** — jen obsah vašeho odboru nebo oblasti, celou oblast, nebo
  vše veřejné v Turince;
- **Počet** — kolik karet se vypíše, nejvýš 24;
- **Obtížnost** (jen u tras) — snadná, střední, náročná, nebo všechny;
- **Nadpis nad výpisem** — nepovinný; prázdný znamená bez nadpisu.

## Kolik karet je vedle sebe

Výpis se řídí **místem, které na stránce dostal**, ne šířkou okna. Na plné
šířce jsou tři karty vedle sebe, na stránce s postranním panelem dvě a na
mobilu jedna. Nastavovat to nejde a není proč — karta užší než zhruba 300 bodů
už láme název přes tři řádky.

Širší výpis dostanete tak, že u stránky vypnete postranní panel (v nastavení
stránky přepínač *Bez bočního panelu*).

## Co blok dělá, když není co vypsat

Nevypíše nic — ani prázdný rámeček, ani hlášku. Blok vložený do stránky
dopředu (než odbor založí první trasu) tak stránku nijak nekazí.

Když Turinka zrovna neodpovídá, ukáže web poslední známá data; drží si je den.
Na stránce se tedy nic nerozpadne ani při výpadku aplikace.

## Proč karty odkazují do Turinky

Trasy a místa nemají na webu vlastní stránku, takže není kam jinam odkázat.
Pod výpisem je proto uvedený zdroj dat — je to protihodnota za to, že aplikace
data poskytuje, stejně jako u rámečku na stránce akce.

Když chcete mít k trase vlastní text, který bude vyhledávačům patřit webu,
napište běžný příspěvek nebo stránku a blok do ní vložte — text je pak váš
a data se do něj doplní z Turinky.
