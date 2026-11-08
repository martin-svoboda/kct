<?php

namespace Kct\Features;

/**
 * Jak se ukládají nahrané obrázky: strop rozměrů a výstupní formát.
 *
 * Plugin je aktivovaný pro celou síť, takže obojí platí automaticky na všech
 * webech a nemusí se nikde nastavovat. Obojí zároveň řeší jádro WordPressu
 * samo — tady se jen mění jeho výchozí hodnoty, což je spolehlivější než
 * servírovací vrstva s přepisy v .htaccess nebo náhradou značek <img>:
 * `srcset` funguje beze změny a v souborech není nic, co by šlo rozbít
 * změnou konfigurace webserveru.
 *
 * Týká se to jen nově nahrávaných souborů. Starší knihovna se ZÁMĚRNĚ nepřevádí:
 * převod už jednou zkomprimovaného JPEGu do WebP znamená druhou ztrátovou
 * kompresi, a to se na měření neobhájilo. Váhu stránek u staršího obsahu řeší
 * něco jiného — posílat správnou velikost místo plné (viz kct_post_thumbnail()
 * v themes/kct/inc/template-tags.php).
 */
class ImageUploads {

	/**
	 * Delší strana nahrávaného obrázku v pixelech.
	 *
	 * Jádro má ve výchozím stavu 2560 px. Snímek z běžného fotoaparátu je
	 * mnohonásobně větší a mezi 2560 a 2048 px je skoro poloviční rozdíl
	 * v objemu souboru. Pro web, kde je největší zobrazovaná velikost
	 * 2048 px, je vyšší rozlišení jen zátěž navíc.
	 */
	private const MAX_DIMENSION = 2048;

	/**
	 * Kvalita komprese WebP.
	 *
	 * Ponechána na hodnotě jádra. Nižší čísla se nabízela kvůli převodu už
	 * existující knihovny — tam se komprimuje podruhé a při 82 vzniká u části
	 * souborů větší výsledek než předloha. Jenže hromadný převod staré knihovny
	 * se nekoná: měřeno objektivně (PSNR) je opakovaná komprese znatelná ztráta
	 * a nestojí za ni. Tenhle filtr se tak uplatní jen na nově nahrávané
	 * soubory, kde se kóduje z nedotčené předlohy a WebP vyhrává i při 82 —
	 * u velikosti „large“ vyjde přibližně o třetinu menší než JPEG.
	 */
	private const WEBP_QUALITY = 82;

	public function __construct() {
		add_filter( 'big_image_size_threshold', array( $this, 'max_dimension' ), 10, 4 );
		add_filter( 'image_editor_output_format', array( $this, 'output_format' ), 10, 3 );
		add_filter( 'wp_editor_set_quality', array( $this, 'webp_quality' ), 10, 2 );

		// Priorita 1000: až za úklidem metadat (ImageMetadata, 999), aby se
		// pořadí nedalo splést. Kolidovat by nemohly — úklid sahá na zmenšený
		// soubor a odvozené velikosti, tenhle hook na ponechaný originál —
		// ale na destruktivní operaci je jasné pořadí lepší než úvaha.
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'drop_original' ), 1000, 2 );
	}

	/**
	 * Strop delší strany, nad kterým WordPress obrázek zmenší.
	 *
	 * @param int    $threshold     Výchozí hodnota jádra.
	 * @param array  $imagesize     Rozměry nahraného souboru.
	 * @param string $file          Cesta k souboru.
	 * @param int    $attachment_id ID přílohy.
	 *
	 * @return int
	 */
	public function max_dimension( $threshold, $imagesize = array(), $file = '', $attachment_id = 0 ) {
		return self::MAX_DIMENSION;
	}

	/**
	 * Odvozené velikosti generovat ve WebP.
	 *
	 * Naměřeno na fotce z fotoaparátu: velikost „large“ vyjde ve WebP
	 * přibližně o třetinu menší než v JPEGu při stejném rozlišení.
	 *
	 * PNG se dřív vynechávala s odůvodněním, že na webu bývají loga, ikony
	 * a schémata s ostrými hranami, na kterých ztrátová komprese dělá
	 * viditelné šmouhy. Zní to rozumně, ale na skutečné knihovně to neplatí:
	 * změřeno na všech větších PNG vyšla průměrná odchylka barvy proti
	 * předloze hluboko pod dvěma procenty na kanál, přičemž grafika a snímky
	 * administrace na tom nebyly hůř než fotky. Nejhorší kus sady je mapa
	 * s tenkými čarami, a i u ní je rozdíl při běžném zobrazení
	 * nerozpoznatelný. Objem přitom spadl na zlomek původního. PNG proto
	 * do WebP jdou taky.
	 *
	 * Průhlednost se neztrácí — WebP má alfa kanál a jádro ho při převodu
	 * zachová.
	 *
	 * Co se tím NEŘEŠÍ: u obrázku o pár plochých barvách (jednoduché logo,
	 * ikona) může WebP vyjít větší než PNG. Jádro na to háček nemá, takže
	 * se takový soubor uloží jako WebP, i když se nevyplatí. Zpětný převod
	 * knihovny tu pojistku má (Images\Downscaler), při nahrávání jde o
	 * jednotky kilobajtů a nestojí za vlastní obrazovou vrstvu.
	 *
	 * Proč ne AVIF: na testovaném snímku vyšel větší než WebP a o dvě třetiny
	 * pomalejší. Kodér v Imagicku by se musel ladit
	 * a tenhle kód má být bezúdržbový.
	 *
	 * @param array  $formats   Mapa vstupní MIME typ => výstupní MIME typ.
	 * @param string $filename  Cesta k souboru.
	 * @param string $mime_type MIME typ zdroje.
	 *
	 * @return array
	 */
	public function output_format( $formats, $filename = '', $mime_type = '' ) {
		if ( ! is_array( $formats ) ) {
			$formats = array();
		}

		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';

		return $formats;
	}

	/**
	 * Kvalita komprese pro WebP.
	 *
	 * Ostatní formáty se nechávají na hodnotě jádra — filtr běží nad všemi.
	 * Drží se tu vlastní konstanta, aby bylo číslo na jednom místě a šlo ho
	 * změnit bez hledání v jádře; hodnota je dnes shodná s výchozí.
	 *
	 * @param int    $quality   Kvalita 1–100.
	 * @param string $mime_type MIME typ výstupu.
	 *
	 * @return int
	 */
	public function webp_quality( $quality, $mime_type = '' ) {
		return 'image/webp' === $mime_type ? self::WEBP_QUALITY : $quality;
	}

	/**
	 * Zahodí plnou verzi obrázku, kterou si jádro nechává vedle zmenšené.
	 *
	 * Když je nahraný snímek delší než strop výše, WordPress vyrobí zmenšenou
	 * kopii `…-scaled.…`, servíruje ji — a původní soubor si nechá ležet na
	 * disku napořád. Strop rozměrů tedy sám o sobě neušetří ani bajt úložiště
	 * a na webu s plnou knihovnou fotek jde o stovky souborů, které nikdo
	 * nečte — jen zabírají místo a nafukují zálohy.
	 *
	 * Klíč `original_image` se musí odstranit spolu se souborem. Jádro podle
	 * něj skládá cestu ve wp_get_original_image_path(); kdyby zůstal viset nad
	 * neexistujícím souborem, odkaz na originál v knihovně médií by vracel
	 * chybu. Bez klíče se jádro vrátí ke zmenšenému souboru, což je správně —
	 * ten je od téhle chvíle originál.
	 *
	 * Operace je nevratná: plná verze se maže z disku a zpátky ji nic nedostane.
	 *
	 * @param array $metadata      Metadata přílohy.
	 * @param int   $attachment_id ID přílohy.
	 *
	 * @return array Metadata bez odkazu na plnou verzi.
	 */
	public function drop_original( $metadata, $attachment_id = 0 ) {
		if ( ! is_array( $metadata ) || empty( $metadata['original_image'] ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$uploads = wp_get_upload_dir();
		$path    = trailingslashit( dirname( trailingslashit( $uploads['basedir'] ) . $metadata['file'] ) ) . $metadata['original_image'];

		// Pojistka proti smazání souboru, který je zároveň tím servírovaným —
		// stát by se to nemělo, ale rozdíl mezi „uklidil jsem zbytek“
		// a „smazal jsem jediný obrázek“ je příliš velký na důvěru.
		if ( wp_basename( $metadata['file'] ) === $metadata['original_image'] ) {
			return $metadata;
		}

		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}

		unset( $metadata['original_image'] );

		return $metadata;
	}
}
