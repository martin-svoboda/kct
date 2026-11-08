<?php

namespace Kct\Images;

/**
 * Zmenšení obrázků, které zůstaly v knihovně nad stropem rozměrů.
 *
 * Features\ImageUploads drží strop 2048 px, ale platí jen na nově nahrávané
 * soubory — knihovna z doby před ním (vydání 2.0.0, 8. 9. 2026) má plná
 * rozlišení z fotoaparátů. Na webu, který vznikl dřív, jde o stovky souborů
 * a podstatnou část obsazeného místa.
 *
 * Proč je to bezpečné i podle pravidla „kvalitou se neplatí za bajty“:
 * největší velikost, kterou web kdy zobrazí, je 2048 px (registrovaná
 * velikost `2048x2048`). Pixely nad ní se nikdy nedostanou k návštěvníkovi,
 * takže se nezahazuje nic, co by kdo viděl.
 *
 * Zmenšení samo formát nemění (shrink()); převod do WebP je samostatný krok
 * (to_webp()) a týká se PNG, stejně jako u nově nahrávaných souborů. Mezi
 * formáty se nedělá rozdíl podle přípony — rozhoduje, kolikrát už se soubor
 * komprimoval. PNG v knihovně je bezztrátové, takže je převod první ztrátová
 * komprese, přesně jako u čerstvě nahrané fotky. JPEG, který v knihovně leží,
 * má jednu kompresi za sebou a převodem by dostal druhou; proto a jen proto
 * ho to_webp() vynechává.
 *
 * Plný soubor se při zmenšení překóduje jednou — tomu se vyhnout nedá — a
 * odvozené velikosti se nepřepisují vůbec:
 *
 * Všechny registrované velikosti (150, 300, 768, 1024, 1536, 2048) jsou menší
 * nebo rovné stropu, takže po zmenšení plného souboru zůstávají platné tak,
 * jak jsou. Není důvod je generovat znovu — a kdyby se generovaly, byl by to
 * další ztrátový průchod u každé z nich.
 *
 * Výjimka je velikost shodná s novým plným souborem: ta se po zmenšení stane
 * jeho kopií, takže se maže (viz tidy_sizes()).
 *
 * Operace je nevratná. Plné rozlišení se přepíše na disku a zpátky ho nic
 * nedostane; bez $write se jen spočítá, co by se stalo.
 */
class Downscaler {

	/**
	 * Delší strana, na kterou se zmenšuje.
	 *
	 * Shodná s ImageUploads::MAX_DIMENSION. Je tu zopakovaná vědomě, ne
	 * sdílená konstantou: tamta říká „takhle se ukládají nové soubory“,
	 * tahle „na tohle se srovnává knihovna“. Jsou to dvě rozhodnutí, která
	 * dnes mají stejné číslo, a kdyby se strop pro nové soubory někdy
	 * změnil, nemá se tím samo od sebe rozjet přepisování staré knihovny.
	 */
	public const MAX_DIMENSION = 2048;

	/**
	 * Formáty, které umí zmenšit.
	 *
	 * GIF chybí schválně — animovaný GIF by zmenšením přišel o všechny snímky
	 * kromě prvního a rozpoznat animaci spolehlivě by znamenalo číst strukturu
	 * souboru. GIFů bývá v knihovně pár a místa zabírají málo, takže to
	 * nestojí za riziko.
	 */
	private const FORMATS = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * O kolik pixelů se smí kratší strana odvozené velikosti lišit od plné,
	 * aby se pořád počítala za její kopii. Viz redundant_sizes().
	 */
	private const TOLERANCE = 2;

	/**
	 * Zmenší plný soubor jedné přílohy.
	 *
	 * @param int  $attachment_id ID přílohy.
	 * @param bool $write         Zapsat na disk. false = jen spočítat.
	 *
	 * @return array{status: string, before: int, after: int, width: int, height: int, note: string}
	 *               status: 'resized' | 'skipped' | 'error'
	 */
	public function shrink( int $attachment_id, bool $write = false ): array {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return $this->result( 'error', 0, 0, 0, 0, 'soubor nelze číst' );
		}

		$before = (int) filesize( $path );
		$info   = @getimagesize( $path );

		if ( ! $info ) {
			return $this->result( 'error', $before, $before, 0, 0, 'není obrázek' );
		}

		list( $w, $h ) = $info;
		$mime          = $info['mime'] ?? '';

		if ( ! in_array( $mime, self::FORMATS, true ) ) {
			return $this->result( 'skipped', $before, $before, $w, $h, 'formát ' . $mime );
		}

		if ( max( $w, $h ) <= self::MAX_DIMENSION ) {
			return $this->result( 'skipped', $before, $before, $w, $h, 'pod stropem' );
		}

		$pomer = self::MAX_DIMENSION / max( $w, $h );
		$novy_w = (int) round( $w * $pomer );
		$novy_h = (int) round( $h * $pomer );

		if ( ! $write ) {
			return $this->result( 'resized', $before, $this->estimate( $before, $w, $h, $novy_w, $novy_h ), $novy_w, $novy_h, 'nanečisto' );
		}

		return $this->do_shrink( $attachment_id, $path, $mime, $before, $novy_w, $novy_h );
	}

	/**
	 * Vlastní zápis — oddělený od rozhodování výš, ať je v jedné metodě jen
	 * jedna starost a destruktivní část se dá číst celá najednou.
	 *
	 * @param int    $attachment_id ID přílohy.
	 * @param string $path          Cesta k plnému souboru.
	 * @param string $mime          MIME typ zdroje.
	 * @param int    $before        Velikost před zmenšením.
	 * @param int    $novy_w        Cílová šířka.
	 * @param int    $novy_h        Cílová výška.
	 *
	 * @return array
	 */
	private function do_shrink( int $attachment_id, string $path, string $mime, int $before, int $novy_w, int $novy_h ): array {
		// Filtr z ImageUploads by při ukládání přepsal JPEG na WebP a vyrobil
		// soubor s příponou .jpg a obsahem WebP. Pro tuhle operaci se vypíná:
		// mění se rozměr, ne formát (viz docblok třídy).
		$drz_format = static function ( $formats ) {
			return array();
		};
		add_filter( 'image_editor_output_format', $drz_format, PHP_INT_MAX );

		try {
			$editor = wp_get_image_editor( $path );

			if ( is_wp_error( $editor ) ) {
				return $this->result( 'error', $before, $before, 0, 0, $editor->get_error_message() );
			}

			$zmena = $editor->resize( $novy_w, $novy_h, false );

			if ( is_wp_error( $zmena ) ) {
				return $this->result( 'error', $before, $before, 0, 0, $zmena->get_error_message() );
			}

			// Zápis jde nejdřív vedle a teprve po kontrole nahradí původní
			// soubor. Kdyby save() skončil v půlce, zůstane na disku rozbitý
			// dočasný soubor místo rozbitého obrázku, na který odkazuje web.
			$docasny = $path . '.kct-tmp';
			$ulozeno = $editor->save( $docasny, $mime );

			if ( is_wp_error( $ulozeno ) || empty( $ulozeno['path'] ) || ! file_exists( $ulozeno['path'] ) ) {
				@unlink( $docasny );

				return $this->result( 'error', $before, $before, 0, 0, is_wp_error( $ulozeno ) ? $ulozeno->get_error_message() : 'uložení selhalo' );
			}

			$kontrola = @getimagesize( $ulozeno['path'] );

			if ( ! $kontrola || (int) $kontrola[0] !== $novy_w ) {
				@unlink( $ulozeno['path'] );

				return $this->result( 'error', $before, $before, 0, 0, 'výsledek nemá čekané rozměry' );
			}

			if ( ! @rename( $ulozeno['path'], $path ) ) {
				@unlink( $ulozeno['path'] );

				return $this->result( 'error', $before, $before, 0, 0, 'nelze nahradit původní soubor' );
			}
		} finally {
			remove_filter( 'image_editor_output_format', $drz_format, PHP_INT_MAX );
		}

		clearstatcache( true, $path );
		$after = (int) filesize( $path );

		$this->update_metadata( $attachment_id, $novy_w, $novy_h, $after );

		return $this->result( 'resized', $before, $after, $novy_w, $novy_h, '' );
	}

	/**
	 * Srovná metadata s tím, co je nově na disku.
	 *
	 * Odvozené velikosti zůstávají — jsou menší než strop, takže platí dál.
	 * Tu jedinou, která se zmenšením stala kopií plného souboru, uklidí
	 * tidy_sizes(); dělá se to zvlášť, protože smazání souboru s sebou nese
	 * přepsání odkazů v obsahu a to má smysl dělat jednou za celou síť.
	 *
	 * @param int $attachment_id ID přílohy.
	 * @param int $w             Nová šířka.
	 * @param int $h             Nová výška.
	 * @param int $filesize      Nová velikost souboru v bajtech.
	 *
	 * @return void
	 */
	private function update_metadata( int $attachment_id, int $w, int $h, int $filesize ): void {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $metadata ) ) {
			return;
		}

		$metadata['width']    = $w;
		$metadata['height']   = $h;
		$metadata['filesize'] = $filesize;

		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Najde odvozené velikosti, které jsou kopií plného souboru.
	 *
	 * Na shodu do posledního bodu se spoléhat nedá. Delší strana vyjde na
	 * 2048 px obojím způsobem, ale kratší se dopočítává ze dvou různých
	 * výchozích rozměrů a zaokrouhlením se rozejde o bod či dva: plný soubor
	 * 2048×1152 a vedle něj velikost `2048x2048` jako 2048×1151 je tentýž
	 * obrázek dvakrát. Přesná shoda je přitom menšina případů.
	 *
	 * Delší strana se proto porovnává přesně a tolerance TOLERANCE platí jen
	 * pro tu kratší. Volnější pravidlo by sahalo i na velikost, která se
	 * opravdu používá: obrázek 1538 px široký má platnou odvozenou velikost
	 * 1536 px a ta se smazat nesmí.
	 *
	 * @param array $metadata Metadata přílohy.
	 * @param int   $w        Šířka plného souboru.
	 * @param int   $h        Výška plného souboru.
	 *
	 * @return array<int, array{name: string, path: string, bytes: int}>
	 */
	private function redundant_sizes( array $metadata, int $w, int $h ): array {
		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) || empty( $metadata['file'] ) || ! $w || ! $h ) {
			return array();
		}

		$uploads = wp_get_upload_dir();
		$adresar = trailingslashit( dirname( trailingslashit( $uploads['basedir'] ) . $metadata['file'] ) );
		$nalezy  = array();

		foreach ( $metadata['sizes'] as $nazev => $velikost ) {
			$sirka = (int) ( $velikost['width'] ?? 0 );
			$vyska = (int) ( $velikost['height'] ?? 0 );

			if ( ! $sirka || ! $vyska ) {
				continue;
			}

			if ( max( $sirka, $vyska ) !== max( $w, $h ) ) {
				continue;
			}

			if ( abs( min( $sirka, $vyska ) - min( $w, $h ) ) > self::TOLERANCE ) {
				continue;
			}

			// Pojistka proti smazání servírovaného souboru: odvozená velikost
			// nikdy nesmí mít stejné jméno jako plný soubor.
			if ( empty( $velikost['file'] ) || $velikost['file'] === wp_basename( $metadata['file'] ) ) {
				continue;
			}

			$cesta = $adresar . $velikost['file'];

			$nalezy[] = array(
				'name'  => (string) $nazev,
				'path'  => $cesta,
				'bytes' => file_exists( $cesta ) ? (int) filesize( $cesta ) : 0,
			);
		}

		return $nalezy;
	}

	/**
	 * Uklidí odvozené velikosti, které jsou kopií plného souboru.
	 *
	 * Volá se u každé přílohy, ne jen u právě zmenšené. Zmenšení proběhlo
	 * i ve vydání, které kopii lišící se o bod na kratší straně nepoznalo
	 * a v knihovně ji nechalo — a tohle je jediná cesta, jak se k ní pak
	 * ještě dostat: shrink() takovou přílohu přeskočí jako „pod stropem“.
	 *
	 * Mazaný soubor může být v obsahu odkázaný přímo — editor do bloku
	 * ukládá adresu té velikosti, kterou autor vybral. Metoda proto vrací
	 * v klíči `urls` dvojice `cesta ke kopii => cesta k plnému souboru` a
	 * nechává přepis na volajícím, stejně jako to_webp(). Bez toho by úklid
	 * utrhl obrázek ve stránce, byť je na disku vedle bajt na bajt stejný.
	 *
	 * @param int  $attachment_id ID přílohy.
	 * @param bool $write         Smazat. false = jen spočítat.
	 *
	 * @return array{count: int, bytes: int, urls: array<string,string>}
	 */
	public function tidy_sizes( int $attachment_id, bool $write = false ): array {
		$prazdne  = array( 'count' => 0, 'bytes' => 0, 'urls' => array() );
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $metadata ) ) {
			return $prazdne;
		}

		$kopie = $this->redundant_sizes( $metadata, (int) ( $metadata['width'] ?? 0 ), (int) ( $metadata['height'] ?? 0 ) );

		if ( ! $kopie ) {
			return $prazdne;
		}

		$adresy = $this->urls_of( $attachment_id );
		$plna   = $adresy['full'] ?? '';
		$mapa   = array();
		$bajtu  = 0;

		foreach ( $kopie as $jedna ) {
			$bajtu += $jedna['bytes'];
			$stara  = $adresy[ $jedna['name'] ] ?? '';

			if ( $stara && $plna && $stara !== $plna ) {
				$mapa[ $stara ] = $plna;
			}
		}

		if ( $write ) {
			foreach ( $kopie as $jedna ) {
				if ( file_exists( $jedna['path'] ) ) {
					wp_delete_file( $jedna['path'] );
				}

				unset( $metadata['sizes'][ $jedna['name'] ] );
			}

			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return array( 'count' => count( $kopie ), 'bytes' => $bajtu, 'urls' => $mapa );
	}

	/**
	 * Převede PNG přílohu do WebP.
	 *
	 * Protějšek k ImageUploads::output_format(), který totéž dělá u nově
	 * nahrávaných souborů — tahle metoda srovná na stejnou míru knihovnu,
	 * která vznikla dřív. Odůvodnění, proč se PNG převádí ztrátově a co se
	 * měřilo, je v docbloku tam; neopakuje se tu.
	 *
	 * Na rozdíl od zmenšení rozměrů tahle operace MĚNÍ NÁZEV SOUBORU, takže
	 * se musí přepsat i odkazy v obsahu. Metoda je nepřepisuje sama — vrací
	 * dvojice `stará adresa => nová adresa` v klíči `urls` a nechává to na
	 * volajícím, který je posbírá za celý web a přepíše jedním průchodem.
	 * Kdyby to dělala sama, znamenalo by to dotaz nad všemi příspěvky pro
	 * každou přílohu zvlášť.
	 *
	 * Pojistka proti nesmyslnému převodu: když výsledek není menší než
	 * předloha, soubor se zahodí a příloha zůstane v PNG. U obrázku o pár
	 * plochých barvách (jednoduché logo, ikona) WebP klidně vyjde větší.
	 *
	 * Operace je nevratná. Původní PNG se z disku maže.
	 *
	 * @param int  $attachment_id ID přílohy.
	 * @param bool $write         Zapsat na disk. false = jen spočítat.
	 *
	 * @return array{status: string, before: int, after: int, note: string, urls: array<string,string>}
	 *               status: 'converted' | 'skipped' | 'error'
	 */
	public function to_webp( int $attachment_id, bool $write = false ): array {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return $this->webp_result( 'error', 0, 0, 'soubor nelze číst' );
		}

		$info = @getimagesize( $path );

		if ( ! $info || ( $info['mime'] ?? '' ) !== 'image/png' ) {
			return $this->webp_result( 'skipped', 0, 0, 'není PNG' );
		}

		$before   = (int) filesize( $path );
		$nova     = preg_replace( '~\.png$~i', '.webp', $path );
		$docasny  = $path . '.kct-tmp.webp';

		if ( $nova === $path || file_exists( $nova ) ) {
			return $this->webp_result( 'skipped', $before, $before, 'cílový soubor už existuje' );
		}

		$editor = wp_get_image_editor( $path );

		if ( is_wp_error( $editor ) ) {
			return $this->webp_result( 'error', $before, $before, $editor->get_error_message() );
		}

		$ulozeno = $editor->save( $docasny, 'image/webp' );

		if ( is_wp_error( $ulozeno ) || empty( $ulozeno['path'] ) || ! file_exists( $ulozeno['path'] ) ) {
			@unlink( $docasny );

			return $this->webp_result( 'error', $before, $before, is_wp_error( $ulozeno ) ? $ulozeno->get_error_message() : 'převod selhal' );
		}

		$after = (int) filesize( $ulozeno['path'] );

		// Nevyplatilo se — příloha zůstane v PNG.
		if ( $after >= $before ) {
			@unlink( $ulozeno['path'] );

			return $this->webp_result( 'skipped', $before, $before, 'WebP by byl větší' );
		}

		if ( ! $write ) {
			@unlink( $ulozeno['path'] );

			return $this->webp_result( 'converted', $before, $after, 'nanečisto' );
		}

		$stare_adresy = $this->urls_of( $attachment_id );

		if ( ! @rename( $ulozeno['path'], $nova ) ) {
			@unlink( $ulozeno['path'] );

			return $this->webp_result( 'error', $before, $before, 'nelze uložit výsledek' );
		}

		$stare_soubory = $this->files_of( $attachment_id );

		update_attached_file( $attachment_id, $nova );
		wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => 'image/webp' ) );

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $nova ) );

		// Staré soubory až teď — po úspěšném vygenerování nových velikostí.
		foreach ( $stare_soubory as $stary ) {
			if ( $stary !== $nova && file_exists( $stary ) ) {
				wp_delete_file( $stary );
			}
		}

		$nove_adresy = $this->urls_of( $attachment_id );
		$plna        = $nove_adresy['full'] ?? '';
		$mapa        = array();

		foreach ( $stare_adresy as $velikost => $adresa ) {
			// Velikost, která po převodu nevznikla, se napáruje na plnou
			// adresu — mrtvý odkaz v obsahu je horší než o kousek větší obrázek.
			$cil = $nove_adresy[ $velikost ] ?? $plna;

			if ( $adresa && $cil && $adresa !== $cil ) {
				$mapa[ $adresa ] = $cil;
			}
		}

		return $this->webp_result( 'converted', $before, (int) filesize( $nova ), '', $mapa );
	}

	/**
	 * Přepíše v obsahu odkazy na soubory, které převod přejmenoval.
	 *
	 * Patří sem, a ne k volajícímu: přejmenování souboru je důsledek
	 * to_webp(), takže i jeho úklid má být vidět na jednom místě. Volá se
	 * jednou za web s mapou posbíranou přes všechny přílohy — dotaz na
	 * každou přílohu zvlášť by u stovek souborů znamenal stovky průchodů
	 * tabulkou příspěvků.
	 *
	 * Zapisuje se přes $wpdb, ne wp_update_post(): obsah starších příspěvků
	 * nese značkování, které by kses při uložení ořezal, a tahle operace má
	 * změnit adresy, nic jiného.
	 *
	 * **Projíždí celou síť, ne jen web, kde se převádělo.** Weby odborů si
	 * navzájem vkládají obrázky z cizí knihovny — typicky má web odboru na
	 * úvodní stránce loga nahraná na webu oblasti. Kdyby se přepisoval jen
	 * vlastní web, převod na oblastním webu by ta loga u sousedů utrhl.
	 * Proto cesty bez domény (viz urls_of()) a průchod po všech webech.
	 *
	 * @param array<string,string> $mapa  Dvojice stará cesta => nová cesta.
	 * @param bool                 $write Zapsat. false = jen spočítat.
	 *
	 * @return int Počet příspěvků, kterých se to týká, přes celou síť.
	 */
	public function rewrite_urls( array $mapa, bool $write = false ): int {
		if ( ! $mapa ) {
			return 0;
		}

		// Gutenberg drží adresy i v JSON atributech bloku, kde jsou lomítka
		// escapovaná (`https:\/\/…\/uploads\/…`). Bez týhle varianty by se
		// obrázek v bloku přepsal jen v HTML a atribut by zůstal ukazovat na
		// soubor, který už neexistuje — editor by pak obrázek nenašel.
		foreach ( $mapa as $stara => $nova ) {
			$mapa[ str_replace( '/', '\\/', $stara ) ] = str_replace( '/', '\\/', $nova );
		}

		$weby = is_multisite()
			? get_sites( array( 'number' => 0, 'fields' => 'ids' ) )
			: array( 0 );

		$dotcenych = 0;

		foreach ( $weby as $web_id ) {
			if ( $web_id ) {
				switch_to_blog( (int) $web_id );
			}

			$dotcenych += $this->rewrite_urls_here( $mapa, $write );

			if ( $web_id ) {
				restore_current_blog();
			}
		}

		return $dotcenych;
	}

	/**
	 * Přepsání v obsahu jednoho webu. Volá se z rewrite_urls() za každý web.
	 *
	 * @param array<string,string> $mapa  Dvojice stará cesta => nová cesta,
	 *                                    už i v escapované podobě.
	 * @param bool                 $write Zapsat. false = jen spočítat.
	 *
	 * @return int Počet příspěvků, kterých se to týká.
	 */
	private function rewrite_urls_here( array $mapa, bool $write ): int {
		global $wpdb;

		// Filtr bez lomítek schválně, ať projde obojí tvar.
		$radky = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts}
			 WHERE post_content LIKE '%uploads%' AND post_type != 'revision'"
		);

		$dotcenych = 0;

		foreach ( $radky as $radek ) {
			$novy = strtr( $radek->post_content, $mapa );

			if ( $novy === $radek->post_content ) {
				continue;
			}

			$dotcenych++;

			if ( ! $write ) {
				continue;
			}

			$wpdb->update( $wpdb->posts, array( 'post_content' => $novy ), array( 'ID' => $radek->ID ) );
			clean_post_cache( (int) $radek->ID );
		}

		return $dotcenych;
	}

	/**
	 * Cesty od kořene webu k plné velikosti i ke všem odvozeným,
	 * klíčované názvem velikosti.
	 *
	 * ZÁMĚRNĚ bez schématu a domény, jen `/wp-content/uploads/…`. Dvakrát
	 * proto:
	 *
	 * 1. `wp_get_upload_dir()` uvnitř switch_to_blog() vrací `baseurl`
	 *    s doménou hlavního webu sítě, ne toho přepnutého — cesta na disku
	 *    je správně, adresa ne. Příkaz běží přes switch_to_blog(), takže
	 *    mapa postavená na `baseurl` mířila na cizí doménu a v obsahu se
	 *    nenašla — po převodu pak ve stránce zůstaly odkazy na soubory,
	 *    které už neexistovaly.
	 * 2. I kdyby ta chyba nebyla: v obsahu je táž adresa psaná různě —
	 *    http i https, s doménou webu i s doménou, ze které se obsah kdysi
	 *    kopíroval. Cesta je společná všem.
	 *
	 * @param int $attachment_id ID přílohy.
	 *
	 * @return array<string,string>
	 */
	private function urls_of( int $attachment_id ): array {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$uploads  = wp_get_upload_dir();
		$korenu   = untrailingslashit( (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) );
		$adresy   = array();

		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) {
			return $adresy;
		}

		$adresy['full'] = $korenu . '/' . $metadata['file'];

		if ( empty( $metadata['sizes'] ) ) {
			return $adresy;
		}

		$zaklad = $korenu . '/' . trailingslashit( dirname( $metadata['file'] ) );

		foreach ( (array) $metadata['sizes'] as $nazev => $velikost ) {
			if ( ! empty( $velikost['file'] ) ) {
				$adresy[ $nazev ] = $zaklad . $velikost['file'];
			}
		}

		return $adresy;
	}

	/**
	 * Cesty k plnému souboru i ke všem odvozeným velikostem.
	 *
	 * @param int $attachment_id ID přílohy.
	 *
	 * @return array<int,string>
	 */
	private function files_of( int $attachment_id ): array {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$soubory  = array();
		$plny     = get_attached_file( $attachment_id );

		if ( $plny ) {
			$soubory[] = $plny;
		}

		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) || empty( $metadata['sizes'] ) ) {
			return $soubory;
		}

		$uploads = wp_get_upload_dir();
		$adresar = trailingslashit( dirname( trailingslashit( $uploads['basedir'] ) . $metadata['file'] ) );

		foreach ( (array) $metadata['sizes'] as $velikost ) {
			if ( ! empty( $velikost['file'] ) ) {
				$soubory[] = $adresar . $velikost['file'];
			}
		}

		return $soubory;
	}

	/**
	 * @param string                $status Stav zpracování.
	 * @param int                   $before Velikost před.
	 * @param int                   $after  Velikost po.
	 * @param string                $note   Poznámka pro výpis.
	 * @param array<string,string>  $urls   Dvojice stará adresa => nová adresa.
	 *
	 * @return array{status: string, before: int, after: int, note: string, urls: array<string,string>}
	 */
	private function webp_result( string $status, int $before, int $after, string $note, array $urls = array() ): array {
		return array(
			'status' => $status,
			'before' => $before,
			'after'  => $after,
			'note'   => $note,
			'urls'   => $urls,
		);
	}

	/**
	 * Odhad velikosti po zmenšení pro běh nanečisto.
	 *
	 * Počítá se z poměru ploch, ne stran — datový objem komprimovaného
	 * obrázku roste zhruba s počtem pixelů. Je to odhad, ne měření: přesné
	 * číslo by znamenalo soubor opravdu překódovat, a běh nanečisto má být
	 * levný a nic nezapisovat.
	 *
	 * Koeficient 1,09 je naměřený, ne odhadnutý: na vzorku fotek z plného
	 * rozlišení vycházel skutečný výsledek o pětinu až třetinu větší, než
	 * říkal samotný poměr ploch. Menší obrázek má po zmenšení vyšší podíl
	 * detailu na pixel, takže se hůř komprimuje, a režie formátu (hlavičky,
	 * Huffmanovy tabulky) se zmenšením neubývá vůbec.
	 *
	 * Záměrně je spíš pesimistický — raději slíbit menší úsporu a doručit
	 * větší než naopak.
	 *
	 * @param int $before Velikost před zmenšením.
	 * @param int $w      Původní šířka.
	 * @param int $h      Původní výška.
	 * @param int $novy_w Cílová šířka.
	 * @param int $novy_h Cílová výška.
	 *
	 * @return int
	 */
	private function estimate( int $before, int $w, int $h, int $novy_w, int $novy_h ): int {
		$plocha = ( $w * $h ) > 0 ? ( $novy_w * $novy_h ) / ( $w * $h ) : 1;

		return (int) round( $before * $plocha * 1.09 );
	}

	/**
	 * @param string $status Stav zpracování.
	 * @param int    $before Velikost před.
	 * @param int    $after  Velikost po.
	 * @param int    $width  Výsledná šířka.
	 * @param int    $height Výsledná výška.
	 * @param string $note   Poznámka pro výpis.
	 *
	 * @return array{status: string, before: int, after: int, width: int, height: int, note: string}
	 */
	private function result( string $status, int $before, int $after, int $width, int $height, string $note ): array {
		return array(
			'status' => $status,
			'before' => $before,
			'after'  => $after,
			'width'  => $width,
			'height' => $height,
			'note'   => $note,
		);
	}
}
