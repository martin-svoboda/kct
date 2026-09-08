<?php

namespace Kct\Og;

/**
 * Úložiště vygenerovaných sdílecích obrázků.
 *
 * Cesta se bere z wp_get_upload_dir(), takže v multisite dostane každý web
 * vlastní adresář pod uploads/sites/{N}/ bez jediného řádku navíc.
 *
 * Na disku leží vždy nejvýš jeden obrázek na objekt: po zápisu nové verze se
 * starší soubory se stejnou předponou smažou.
 */
class OgImageStore {

	private const DIR = 'kct-og';

	public function dir(): string {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	public function base_url(): string {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['baseurl'] ) . self::DIR;
	}

	public function filename( string $prefix, string $hash, string $ext = 'png' ): string {
		return $prefix . '-' . $hash . '.' . $ext;
	}

	/** URL hotového obrázku, nebo null když soubor neexistuje. */
	public function url( string $prefix, string $hash, string $ext = 'png' ): ?string {
		$name = $this->filename( $prefix, $hash, $ext );

		return file_exists( $this->dir() . '/' . $name ) ? $this->base_url() . '/' . $name : null;
	}

	/**
	 * Uloží obrázek a vrátí jeho URL, nebo null když se zapsat nepovedlo.
	 *
	 * Zapisuje se do dočasného souboru a přejmenovává až po úspěchu. Přímý
	 * zápis by při přerušení nechal na disku useknutý soubor, který by se
	 * tvářil jako hotový — a protože se existence souboru bere jako „hotovo",
	 * už by se nikdy nepřegeneroval.
	 */
	public function save( string $blob, string $prefix, string $hash, string $ext = 'png' ): ?string {
		$dir = $this->dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$name  = $this->filename( $prefix, $hash, $ext );
		$final = $dir . '/' . $name;
		$tmp   = $final . '.tmp';

		if ( false === file_put_contents( $tmp, $blob ) ) {
			return null;
		}

		if ( ! rename( $tmp, $final ) ) {
			wp_delete_file( $tmp );

			return null;
		}

		$this->prune( $prefix, $name, $ext );

		return $this->base_url() . '/' . $name;
	}

	/**
	 * Smaže starší verze téhož objektu v témž formátu.
	 *
	 * Předpony jsou `post-12`, `akce-12`, `akce-db-345` a u plakátů
	 * `social-post-12`; maska za nimi má pomlčku, takže `post-1-*` nechytne
	 * `post-12-*` a `akce-12-*` nechytne `akce-db-12-*`. Přípona v masce navíc
	 * zajistí, že úklid plakátu nesmaže kartu na šířku a naopak.
	 */
	private function prune( string $prefix, string $keep, string $ext ): void {
		foreach ( (array) glob( $this->dir() . '/' . $prefix . '-*.' . $ext ) as $file ) {
			if ( basename( $file ) !== $keep ) {
				wp_delete_file( $file );
			}
		}
	}
}
