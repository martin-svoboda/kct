# Prezentační miniweb šablony — návrh

**Datum:** 2026-09-02

**Souvisí s:** [`2026-09-01-uzivatelska-dokumentace-design.md`](2026-09-01-uzivatelska-dokumentace-design.md)

## Problém

Šablona KČT nemá kde být představená. Odbor, který o ní slyší, nemá kam
kliknout — neuvidí, jak vypadá, co umí, kde se stahuje ani na koho se obrátit.
Uživatelská příručka na `napoveda.sokct.cz` odpovídá na otázku „jak s tím
pracovat", ne na otázku „co to je a chci to".

Doména `sablona.sokct.cz` je dnes subsite v multisite, dosud bez využití.

## Rozsah

**Je v rozsahu:** jedna landing page na `sablona.sokct.cz` složená z bloků
šablony, se čtyřmi obsahovými částmi (co šablona umí, odkaz na nápovědu,
stažení a instalace, ukázky nasazení a kontakt).

**Není v rozsahu:** demo web s ukázkovým obsahem typu akcí a aktualit;
vícestránkový web; registrace nebo objednávkový formulář; automatické
načítání čísla verze z GitHubu.

## Proč zůstává na WordPressu

Stojí to na jedné úvaze: **prezentace šablony má sama běžet na šabloně.**
Návštěvník tím vidí hlavičku, patičku, typografii i bloky naživo, ne na
screenshotech, které za půl roku nesedí. Prezentace a ukázka jsou jedna věc,
a odpadá tím starost s udržováním obrázků.

Druhý důvod je praktický: subsite už existuje a je prázdná. Landing page je
tedy obsahová práce v editoru, ne nová infrastruktura — žádný build, žádný
deploy, žádná další doména k hlídání.

Nevýhodu to má jednu a je vědomá: obsah landing page leží v databázi, ne v
gitu. U jedné stránky, která se mění jednou za čas, je to únosné — na rozdíl
od příručky o třiceti stránkách, kde to byl důvod jít do statického webu.

## Stavba stránky

Celá je složená z bloků, které šablona nabízí. Nic se kvůli ní neprogramuje.

| Pořadí | Blok | Obsah |
| --- | --- | --- |
| 1 | Úvodní obrázek | Název, jedna věta co to je, tlačítko na nápovědu. Fotka z turistiky. |
| 2 | Předtitulek + odstavce | Pro koho šablona je: odbory a oblasti KČT |
| 3 | Info karty (3 ve sloupcích) | Akce z centrální databáze KČT · Sdílení na Facebook · Automatické zpracování fotek |
| 4 | Info karty (3 ve sloupcích) | Vlastní bloky · Mapy tras a akcí · SEO a sdílecí obrázky |
| 5 | Obrázek s obsahem vedle | Odkaz na příručku — co v ní je, tlačítko na `napoveda.sokct.cz` |
| 6 | CTA blok | Ke stažení: odkaz na poslední vydání na GitHubu, požadavek PHP 8.0+ |
| 7 | Info karty | Weby, které šablonu používají: sokct.cz, posazavskastezka.cz |
| 8 | Odstavec | Kontakt pro odbory, které by šablonu chtěly |

Pořadí sleduje otázky návštěvníka: co to je → co to umí → jak se to používá →
kde to vzít → kdo to už má → koho oslovit.

## Nastavení subsite

- Styl šablony: Obrazový (výchozí), aby prezentace ukazovala výchozí podobu
- Hlavní menu: jen odkaz na nápovědu a kotva na stažení — víc stránek web nemá
- Kód odboru zůstane **nevyplněný**: bez něj se nevypisují žádné akce, což je
  u prezentačního webu správně
- Vyhledávání v hlavičce vypnuté, tlačítko „Stát se členem" bez odkazu
  (nezobrazí se)

## Odkaz na stažení

Míří na `https://github.com/martin-svoboda/kct/releases/latest`, ne na
konkrétní verzi. Tím se odkaz nemusí měnit s každým vydáním; instalační ZIP je
u každého vydání přílohou, kterou tam zakládá `deploy.yml`.

## Otevřené body

**Kdo stránku založí.** Obsah se dá vytvořit dvěma způsoby: připravit ho jako
blokový kód k vložení do editoru (uživatel pak jen zkontroluje a publikuje),
nebo ho vložit rovnou příkazem WP-CLI na lokální instalaci. Zápis do databáze
je zásah do obsahu webu, takže o něm rozhoduje uživatel.

**Fotky.** Landing page potřebuje aspoň jednu velkou fotku do úvodního obrázku
a ilustrace do info karet. Zdroj zatím není určený.
