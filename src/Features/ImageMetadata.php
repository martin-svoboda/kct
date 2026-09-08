<?php

namespace Kct\Features;

use Kct\Images\MetadataCleaner;

/**
 * Úklid metadat u nově nahrávaných obrázků.
 *
 * Bez toho by se knihovna zaplevelila znovu — fotoaparáty ukládají do EXIF
 * desítky kilobajtů a WordPress ta data kopíruje i do všech odvozených
 * velikostí, takže jeden nahraný snímek zanese balast do dvanácti souborů.
 * V knihovně KČT tvořila metadata 84 MB, u některých náhledů 92 % objemu.
 *
 * Proč `wp_generate_attachment_metadata` a ne `wp_handle_upload`:
 * WordPress čte z originálu EXIF ve dvou krocích. Nejdřív si z něj vytáhne
 * popisek a datum (wp_read_image_metadata()), a hlavně podle značky orientace
 * otáčí generované velikosti (wp_image_maybe_exif_rotate()). Kdyby se EXIF
 * smazal hned po nahrání, fotky nafocené na výšku by se vygenerovaly položené.
 * Tenhle filtr běží až potom, kdy je obojí hotové a metadata už nikdo
 * nepotřebuje.
 *
 * Úklid je bezztrátový, viz MetadataCleaner — obrazová data se nedekódují.
 */
class ImageMetadata {

	public function __construct( private MetadataCleaner $cleaner ) {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'clean_attachment' ), 999, 2 );
	}

	/**
	 * Vyčistí originál i všechny vygenerované velikosti jedné přílohy.
	 *
	 * Priorita 999, aby doběhly pluginy, které si z metadat ještě čtou nebo do
	 * nich zapisují. Velikosti souborů v poli metadat se po úklidu přepočítají,
	 * jinak by v databázi zůstaly hodnoty z doby před zmenšením.
	 *
	 * @param array $metadata      Metadata přílohy.
	 * @param int   $attachment_id ID přílohy.
	 *
	 * @return array Metadata s aktualizovanými velikostmi souborů.
	 */
	public function clean_attachment( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$uploads = wp_get_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );
		$dir     = trailingslashit( dirname( $base . $metadata['file'] ) );

		$result = $this->cleaner->clean( $base . $metadata['file'], true );

		if ( 'cleaned' === $result['status'] && isset( $metadata['filesize'] ) ) {
			$metadata['filesize'] = $result['after'];
		}

		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return $metadata;
		}

		foreach ( $metadata['sizes'] as $name => $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}

			$result = $this->cleaner->clean( $dir . $size['file'], true );

			if ( 'cleaned' === $result['status'] && isset( $metadata['sizes'][ $name ]['filesize'] ) ) {
				$metadata['sizes'][ $name ]['filesize'] = $result['after'];
			}
		}

		return $metadata;
	}
}
