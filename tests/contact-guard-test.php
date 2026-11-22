<?php
/**
 * Testy hledání kontaktů v textu a jejich kódování. Spouští se
 * `php tests/contact-guard-test.php`, při chybě končí návratovým kódem 1.
 *
 * Záměrně se nepoužívá assert(): bez zend.assertions=1 se vyhodnocení vypne
 * a test by tiše prošel.
 *
 * Řetězce v testech jsou takové, jaké v obsahu opravdu stojí — adresa odboru
 * s PSČ a IČO v jednom odstavci, telefon zapsaný obojím způsobem, kontakt
 * organizátora. Vymyšlený vstup by u heuristiky nedokázal nic.
 */

declare( strict_types=1 );

require_once __DIR__ . '/../src/Features/ContactScanner.php';

use Kct\Features\ContactScanner;

$failed = 0;

function check( string $name, mixed $actual, mixed $expected ): void {
	global $failed;

	if ( $actual === $expected ) {
		echo "OK    $name\n";

		return;
	}

	$failed ++;
	echo "CHYBA $name\n";
	echo '      čekáno:  ' . var_export( $expected, true ) . "\n";
	echo '      dostal:  ' . var_export( $actual, true ) . "\n";
}

/**
 * Průchod se značkami místo HTML, aby se v testu dalo přečíst, co se našlo
 * jako e-mail a co jako telefon.
 */
function scan( string $html ): string {
	return ContactScanner::protect(
		$html,
		static fn( string $email ): string => '[E:' . $email . ']',
		static fn( string $phone ): string => '[P:' . $phone . ']'
	);
}

/**
 * Průchod se zápisem, jaký vypisuje ContactGuard — pro testy, kde záleží na
 * tom, že výstup sám chráněný je.
 */
function scan_as_output( string $html ): string {
	$render = static function ( string $attribute, string $value ): string {
		return sprintf(
			'<span class="kct-contact" %s="%s"><noscript>%s</noscript></span>',
			$attribute,
			ContactScanner::encode( $value ),
			'skryto'
		);
	};

	return ContactScanner::protect(
		$html,
		static fn( string $email ): string => $render( 'data-kct-email', $email ),
		static fn( string $phone ): string => $render( 'data-kct-tel', $phone )
	);
}

// --- e-mail ---------------------------------------------------------------
check( 'adresa v textu', scan( 'Kontakt: turistlabe@email.cz' ), 'Kontakt: [E:turistlabe@email.cz]' );
check( 'adresa s pomlčkou', scan( 'kct-sedlec-prcice@centrum.cz' ), '[E:kct-sedlec-prcice@centrum.cz]' );
check( 'adresa s podtržítkem', scan( 'slesingerova_marie@jawa.eu' ), '[E:slesingerova_marie@jawa.eu]' );
check( 'adresa na konci věty', scan( 'Napište na sipkova@bri.cz.' ), 'Napište na [E:sipkova@bri.cz].' );
check( 'dvě adresy', scan( 'a@b.cz, c@d.eu' ), '[E:a@b.cz], [E:c@d.eu]' );
check( 'zavináč bez domény se nebere', scan( 'cena 100 Kč @ stánku' ), 'cena 100 Kč @ stánku' );

// --- telefon --------------------------------------------------------------
check( 'číslo po trojicích', scan( 'Tel. 736 754 120' ), 'Tel. [P:736 754 120]' );
check( 'číslo v jednom kuse', scan( 'Tel. 728742102' ), 'Tel. [P:728742102]' );
check( 'číslo s předvolbou', scan( '+420 604 958 059' ), '[P:+420 604 958 059]' );
check( 'číslo s předvolbou 00420', scan( '00420737712807' ), '[P:00420737712807]' );
check( 'nedělitelná mezera jako oddělovač', scan( "737\u{00A0}712\u{00A0}807" ), "[P:737\u{00A0}712\u{00A0}807]" );

// --- čím se telefon není -------------------------------------------------
check( 'osmimístné IČO', scan( 'IČO: 86595148' ), 'IČO: 86595148' );
check( 'PSČ a město', scan( '110 00 Praha 1' ), '110 00 Praha 1' );
check( 'PSČ bez mezery', scan( 'Sedlec-Prčice 25791' ), 'Sedlec-Prčice 25791' );
check( 'rok', scan( 'ročník 2026' ), 'ročník 2026' );
check( 'souřadnice', scan( 'GPS 50.1685, 14.91905' ), 'GPS 50.1685, 14.91905' );
check( 'číslo účtu', scan( 'účet 123456789/0800' ), 'účet 123456789/0800' );
check( 'devět číslic označených jako IČO', scan( 'IČO 123456789' ), 'IČO 123456789' );
check( 'variabilní symbol', scan( 'variabilní symbol 123456789' ), 'variabilní symbol 123456789' );
check( 'delší číslo se nekrájí', scan( 'kód 1234567890123' ), 'kód 1234567890123' );
check( 'číslo popisné', scan( 'Revoluční 1056/8a' ), 'Revoluční 1056/8a' );

// --- celý odstavec z patičky ---------------------------------------------
check(
	'adresa, e-mail, mobil a IČO v jednom odstavci',
	scan( 'Revoluční 1056/8a,110 00 Praha 1<br>e-mail: sekretar@priklad.cz<br>mobil: 736 754 120<br>IČO: 86595148' ),
	'Revoluční 1056/8a,110 00 Praha 1<br>e-mail: [E:sekretar@priklad.cz]<br>mobil: [P:736 754 120]<br>IČO: 86595148'
);

// --- do značek a atributů se nesahá --------------------------------------
check(
	'adresa v atributu zůstane',
	scan( '<img src="/soubor/736754120.jpg" alt="a@b.cz">' ),
	'<img src="/soubor/736754120.jpg" alt="a@b.cz">'
);
check(
	'odkaz na web se nerozbije',
	scan( '<a href="https://priklad.cz/736754120">Propozice</a>' ),
	'<a href="https://priklad.cz/736754120">Propozice</a>'
);

// --- odkazy mailto a tel se mění celé ------------------------------------
check(
	'mailto odkaz s textem adresy',
	scan( '<a href="mailto:a@b.cz">a@b.cz</a>' ),
	'[E:a@b.cz]'
);
check(
	'mailto odkaz s jiným textem vede na adresu z href',
	scan( '<a href="mailto:a@b.cz">Napište nám</a>' ),
	'[E:a@b.cz]'
);
check(
	'mailto odkaz s předmětem',
	scan( '<a href="mailto:a@b.cz?subject=Dotaz">Dotaz</a>' ),
	'[E:a@b.cz]'
);
check(
	'tel odkaz',
	scan( '<a href="tel:604958059">604 958 059</a>' ),
	'[P:604958059]'
);

// --- přeskakované prvky ---------------------------------------------------
check( 'obsah code', scan( '<code>a@b.cz</code>' ), '<code>a@b.cz</code>' );
check( 'obsah pre', scan( '<pre>736 754 120</pre>' ), '<pre>736 754 120</pre>' );
check(
	'obsah skriptu',
	scan( '<script>var m = "a@b.cz";</script>' ),
	'<script>var m = "a@b.cz";</script>'
);
check(
	'text za přeskakovaným prvkem se zpracuje',
	scan( '<code>a@b.cz</code> nebo c@d.cz' ),
	'<code>a@b.cz</code> nebo [E:c@d.cz]'
);

// --- komentáře ------------------------------------------------------------
//
// Zakomentovaný kontakt se nechává být (vidět není a vložit do komentáře
// chráněný prvek nejde), ale nesmí si vzít text, který za komentářem
// následuje — ten se vypisuje a chráněný být má.
check(
	'kontakt v komentáři zůstane',
	scan( '<!-- mobil: 736 754 120 -->' ),
	'<!-- mobil: 736 754 120 -->'
);
check(
	'text za komentářem se zpracuje',
	scan( '<!-- mobil: 736 754 120 --> napište na a@b.cz' ),
	'<!-- mobil: 736 754 120 --> napište na [E:a@b.cz]'
);
check(
	'komentář bez konce si text nevezme',
	scan( '<!-- pozn. --> tel. 736 754 120<br />' ),
	'<!-- pozn. --> tel. [P:736 754 120]<br />'
);

// --- už chráněný prvek se nechá být --------------------------------------
check(
	'chráněný prvek se podruhé nekóduje',
	scan( '<span class="kct-contact" data-kct-email="1f2e"><noscript>a&#64;b.cz</noscript></span>' ),
	'<span class="kct-contact" data-kct-email="1f2e"><noscript>a&#64;b.cz</noscript></span>'
);

// --- kódování -------------------------------------------------------------
check( 'kódování a rozebrání', ContactScanner::decode( ContactScanner::encode( 'a@b.cz' ) ), 'a@b.cz' );
check(
	'kódování s diakritikou',
	ContactScanner::decode( ContactScanner::encode( 'žluťoučký@kůň.cz' ) ),
	'žluťoučký@kůň.cz'
);
check( 'kódování telefonu s mezerami', ContactScanner::decode( ContactScanner::encode( '736 754 120' ) ), '736 754 120' );
check( 'daný klíč dá daný výstup', ContactScanner::encode( 'ab', 0x2a ), '2a4b48' );
check( 've výstupu není zavináč', str_contains( ContactScanner::encode( 'a@b.cz' ), '@' ), false );
check( 'poškozený vstup nic nevrátí', ContactScanner::decode( 'xyz' ), '' );
check( 'prázdný vstup nic nevrátí', ContactScanner::decode( '' ), '' );

// --- části pro čitelnou zálohu -------------------------------------------
check( 'rozdělení e-mailu', ContactScanner::split_email( 'a@b.cz' ), array( 'a', '@', 'b.cz' ) );
check(
	'rozdělení e-mailu bere poslední zavináč',
	ContactScanner::split_email( 'a@b@c.cz' ),
	array( 'a@b', '@', 'c.cz' )
);
check( 'rozdělení telefonu po trojicích', ContactScanner::split_phone( '736754120' ), array( '736', ' ', '754', ' ', '120' ) );
check(
	'rozdělení telefonu s předvolbou',
	ContactScanner::split_phone( '+420736754120' ),
	array( '+420', ' ', '736', ' ', '754', ' ', '120' )
);
check(
	'rozdělení telefonu, který už mezery má',
	ContactScanner::split_phone( '736 754 120' ),
	array( '736', ' ', '754', ' ', '120' )
);

// --- dvojí průchod --------------------------------------------------------
//
// Obsah projde filtrem dvakrát: jednou jako vykreslený blok, podruhé jako celý
// obsah příspěvku složený z bloků. Druhý průchod tedy vidí výstup prvního a
// nesmí ho zpracovat znovu — pozná ho podle `data-kct-*` na prvku.
//
// Testuje se proto zápisem, jaký se opravdu vypisuje. Značka `[E:…]` by tu
// neřekla nic: je to čitelná adresa, kterou by druhý průchod po právu našel.
$kct_once  = scan_as_output( 'Kontakt: a@b.cz, 736 754 120' );
$kct_twice = scan_as_output( $kct_once );
check( 'druhý průchod nic nepřidá', $kct_twice, $kct_once );
check(
	'první průchod kontakt opravdu zakryl',
	str_contains( $kct_once, 'a@b.cz' ) || str_contains( $kct_once, '736 754 120' ),
	false
);

// --- je v textu kontakt? --------------------------------------------------
//
// Otázka pro výstupy, do kterých chráněný prvek vložit nejde — popisek akce
// pro vyhledávače se skládá i z textu akce a ten se při nálezu vynechá.
check( 'kontakt: adresa', ContactScanner::has_contact( 'Podrobnosti: hoke@priklad.cz' ), true );
check( 'kontakt: telefon', ContactScanner::has_contact( 'Info na 736 754 120' ), true );
check( 'kontakt: nic', ContactScanner::has_contact( 'Sraz je u nádraží v 8 hodin.' ), false );
check( 'kontakt: IČO není telefon', ContactScanner::has_contact( 'IČO 123456789' ), false );
check( 'kontakt: PSČ není telefon', ContactScanner::has_contact( 'Dolní Lhota 110 00' ), false );

// --- text bez kontaktu projde beze změny ---------------------------------
$kct_plain = '<p>Sraz je u nádraží, trasa vede přes Mydlovar.</p>';
check( 'text bez kontaktu', scan( $kct_plain ), $kct_plain );

echo "\n";

if ( $failed > 0 ) {
	echo "Neprošlo: $failed\n";
	exit( 1 );
}

echo "Všechno prošlo.\n";
exit( 0 );
