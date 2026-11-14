=== Šablona KČT ===
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: KCT_VERSION
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Web na klíč pro odbory a oblasti Klubu českých turistů: kalendář akcí, odbory, sdílení na Facebook, SEO a zpracování obrázků.

== Description ==

Plugin se šablonou pro weby odborů a oblastí Klubu českých turistů. Umí:

* kalendář akcí z centrální Databáze turistických akcí KČT i z vlastních příspěvků,
* evidenci odborů s mapou a vyhledáváním,
* propojení s aplikací Turinka — akce, trasy a místa,
* sdílení akcí na facebookovou stránku odboru,
* SEO a sdílecí obrázky pro akce i odbory,
* zpracování obrázků: zmenšení na rozumný rozměr a převod do WebP,
* vlastní vzhled webu ve třech stylech.

Nápověda pro správce webu je na https://napoveda.sokct.cz.

== Changelog ==

Čísla verzí začínají u 2.0.0, kterou se šablona začala vydávat veřejně.
Podrobněji, s obrázky a odkazy, totéž popisuje
https://napoveda.sokct.cz/spravce/zmeny-v-sablone/.

= 2.2.0 =

* Přibylo menu KČT v administraci — nastavení se přestěhovalo z Nastavení → KČT do vlastního menu nad Vzhledem.
* Přibyla Nástěnka KČT: akce a jejich zdroje, stav importu, propojení s Turinkou a sdílení na Facebook na jedné stránce.
* Přibyl Přehled akcí pod Akcemi — jedna akce jako jeden řádek napříč zdroji, včetně nespárovaných dvojic.
* Přibyly trasy a místa z Turinky: přehledy v menu KČT a nový blok, kterým se vypíšou do stránky.
* Přibyly trasy u akce a předpověď počasí na den konání.
* Přibylo hledání ve výpisu odborů, od osmi odborů výš.
* Přibyla volba barvy proužku u bloku Info karta — primární barva webu nebo jedna ze čtyř barev značení.
* Výpis akcí se nestránkuje: další akce se dotahují pod už načtené, takže seznam neuskočí.
* Filtr akcí je sbalitelný, nabízí rychlou volbu rozsahu a nad výpisem je počet nalezených akcí.
* Prázdný výsledek filtru nabídne nejbližší akci za koncem zvoleného rozmezí.
* Propozice mají tlačítko k otevření v PDF a náhled, který se na telefonu stahuje až po rozbalení.
* Výsledky hledání jsou seznam se štítkem typu obsahu místo řady článků.
* Výpisy a stránky akcí jsou přístupnější a lehčí: úrovně nadpisů, popisky orientačních bodů, větší cíle dotyku a obrázek akce ve velikosti, v jaké se zobrazí.
* Opraveno: příspěvek sdílený na Facebook s fotkou končil v albu stránky místo ve výpisu příspěvků.

= 2.1.2 =

* Opraveno: úklid knihovny mohl rozbít obrázek vložený z knihovny jiného webu sítě — odkazy se nově dohledají na všech webech.
* Opraveno: po zmenšení knihovny zůstávala ležet druhá kopie v plné velikosti.

= 2.1.1 =

* Pod obsahem akce přibyla navigace na předchozí a následující akci, sekce Mohlo by vás zajímat a přehled dalších ročníků.
* Ikony druhů turistiky se doplní z číselníku i u akcí, u kterých je centrální databáze neposílá.
* Import odstraní i akci, kterou centrální databáze přestala nabízet bez ohlášení; proběhlé akce v archivu zůstávají.
* Import si poradí s vadným znakem v exportu, který ho dřív tiše zastavil.
* Administrace mluví česky: Kalendář akcí, Odbory a jejich hlášky.
* Do WebP se převádějí i soubory PNG, průhlednost zůstává zachovaná.
* Starší knihovnu jde jednorázově zmenšit a převést; spouští se z příkazové řádky.

= 2.1.0 =

* Šablona si novou verzi hlásí sama a aktualizuje se jako kterýkoli jiný plugin. Platí až od příští aktualizace.
* Údaje odboru jdou vyplnit celé v administraci, i u odborů, které vznikly importem.

= 2.0.0 =

* První veřejné vydání: šablonu si může nainstalovat kterýkoli odbor.
* Adresa exportu z Databáze akcí KČT se zadává v KČT → Nastavení. Dokud je pole prázdné, akce se nenačítají.
* Číslování verzí začíná u 2.0.0.
