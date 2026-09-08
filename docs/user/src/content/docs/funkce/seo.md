---
title: SEO
description: Co šablona doplňuje pro vyhledávače sama — popisky a strukturovaná data akcí a odborů, mapa webu — a jak se to snáší s pluginem Rank Math.
sidebar:
  order: 9
---

Po přečtení téhle stránky je jasné, co pro vyhledávače obstarává šablona sama,
co se od SEO pluginu ještě čeká a co v něm naopak nemá smysl přenastavovat.

## Co dělá šablona sama

Akce a odbory nejsou obyčejné stránky. Většina akcí pochází z centrální
databáze KČT a vlastní příspěvek nemá — je to stránka, kterou web sestavuje
z dat. Žádný SEO plugin o takové stránce neví, takže by jí dal titulek
a popisek výpisu aktualit. Šablona to za něj udělá správně.

### U akcí

- **Titulek stránky** složený z názvu akce, ročníku, obce a data — třeba
  „46. Krajem nezbedného bakaláře — Rakovník, 5. 9. 2026". Název webu se
  doplní za něj.
- **Popisek** poskládaný z dat akce: druh akce, kdy a kde se koná, kdo ji
  pořádá. Zkrátí se na délku, kterou vyhledávač zobrazí. Volný text má
  vyplněná jen menšina akcí, popisek proto stojí na datech a text ho jen
  doplní, když po nich zbude místo.
- **Kanonická adresa** — viz oddíl níž.
- **Strukturovaná data typu Event**: název, začátek a konec, stav akce, místo
  se souřadnicemi a adresou, pořadatel, obrázek, adresa stránky. Díky nim
  rozumí vyhledávač tomu, že jde o akci, a může ji zobrazit s termínem.
- **Sdílecí obrázek** pro sociální sítě, viz
  [Sdílecí obrázky](/funkce/sdileci-obrazky/).

Pořadatel se do strukturovaných dat dosazuje **skutečný** — tedy odbor, který
akci pořádá. Na oblastním webu, který vypisuje i akce cizích odborů, by jinak
u každé akce stálo jméno oblasti, což by nebyla pravda.

### U odborů

- **Popisek** složený z údajů odboru. Vlastní popisek nemá vyplněný ani jeden
  odbor, takže by jinak zůstal prázdný.
- **Sdílecí obrázek**.
- **Strukturovaná data typu SportsOrganization**: název, adresa, souřadnice,
  kontakt.

Tahle část se zapojí jen na webu, kde běží Rank Math. Bez něj dostane detail
odboru aspoň obecné značky pro sociální sítě, které šablona vypisuje u každé
stránky.

### Akce v mapě webu

Detaily akcí z centrální databáze se přidávají do mapy webu (sitemap), aby je
vyhledávač našel, i když na ně nevede odkaz z výpisu — třeba proto, že už
proběhly. Proběhlé akce v mapě zůstávají, ze dne na den z ní nemizí.

Mapa webu vzniká v Rank Mathu, takže tenhle bod platí jen na webech, kde je
plugin nainstalovaný.

### Která akce patří kterému webu

Na samostatném webu se tohle neřeší — web je kanonický pro každou akci, kterou
vypisuje.

Je-li web součástí sítě více webů, umí detail akce z centrální databáze zobrazit
**každý web v síti** — čtou společnou tabulku. Bez pravidla by tak stejná
stránka existovala na několika doménách zároveň a vyhledávače by je považovaly
za duplicity, což uškodí všem.

Šablona proto u každé akce určí jeden kanonický web a ostatní na něj odkážou:

1. existuje-li kdekoli v síti **příspěvek akce** spárovaný s tímhle záznamem,
   je kanonická jeho adresa;
2. jinak **web pořádajícího odboru**, pokud v síti nějaký má;
3. jinak **oblastní web**.

Do mapy webu se pak každá akce dostane jen na tom webu, kterému podle tohoto
pravidla patří. Nastavovat se to nedá a nemusí — vyplývá to z dat akce a ze
složení sítě.

## S pluginem i bez něj

Šablona zvládne obojí:

- **Bez SEO pluginu** vypisuje značky Open Graph a Twitter Card u všech
  stránek sama a na detailu akce k nim přidá titulek, popisek, kanonickou
  adresu i strukturovaná data.
- **S pluginem Rank Math** se do jeho výstupu zapojí a doplní jen to, co
  plugin z dat akce vyčíst neumí. Ostatní nechá na něm.

Dva zdroje týchž značek by si překážely, takže se šablona ve chvíli, kdy SEO
plugin najde, do vypisování obecných značek sama přestane plést.

## Co v Rank Mathu nepřenastavovat

Rank Math je doporučený plugin (viz [Doporučené
pluginy](/spravce/doporucene-pluginy/)), ale u obsahu, který řeší šablona, se
vyplatí do něj nesahat:

- **Titulek a popisek akce z centrální databáze** — skládá je šablona z dat
  akce a ruční šablony titulků v Rank Mathu se u nich neuplatní. Vyplňovat je
  tam znamená psát text, který se nikde neobjeví.
- **Strukturovaná data akcí a odborů** — šablona je doplňuje sama a přesněji,
  než co jde poskládat z nastavení pluginu. Zapínat u typu obsahu Akce vlastní
  schéma je zbytečné.
- **Výchozí obrázek pro sdílení** — u aktualit a akcí ho přebije vlastní
  sdílecí obrázek šablony, který nese titulek, termín a další údaje.
- **Sitemapu nechat zapnutou.** Detaily akcí se do ní přidají samy; ručně se
  nic přidávat nemá.

Kde má naopak smysl Rank Math používat naplno, jsou **běžné stránky
a aktuality**: tam titulek i popisek skládá plugin a redakce je může ručně
přepsat, což šablona záměrně nepřebíjí. Detail akce, která má **vlastní
příspěvek**, se chová stejně — titulek a popisek zůstávají v rukou redakce,
šablona k nim jen přidá strukturovaná data a sdílecí obrázek.

## Co SEO neobstará

Strukturovaná data a popisky vyřeší formu, ne obsah. Návštěvnost z vyhledávačů
nejvíc zvedne to, co je na webu skutečně napsané: srozumitelné názvy akcí,
vyplněné místo a pořadatel, popis trasy, fotky z minulých ročníků. Co kde
vyplnit, popisují kapitoly [Akce](/funkce/akce/) a [Odbory](/funkce/odbory/).
