<?php

namespace Kct\Turinka;

/**
 * Kde Turinka běží.
 *
 * Adresa je konfigurovatelná konstantou ve wp-config.php ze dvou důvodů: kvůli
 * lokálnímu vývoji a kvůli webům, které by Turinku měly hledat jinde. Ověřování
 * TLS certifikátu se kvůli tomu nikde nevypíná.
 */
class Config {
	const DEFAULT_URL = 'https://turinka.cz';

	/**
	 * Základní adresa bez koncového lomítka.
	 */
	public function base_url(): string {
		$url = defined( 'KCT_TURINKA_URL' ) ? (string) KCT_TURINKA_URL : self::DEFAULT_URL;

		return untrailingslashit( $url );
	}

	/**
	 * Hlavička Host, kterou se přebíjí hostitel z adresy.
	 *
	 * Potřeba jen lokálně: uvnitř kontejneru se `turi.ddev.site` překládá na
	 * 127.0.0.1, tedy na vlastní Apache, takže se plugin ptá sám sebe a dostane
	 * cizí odpověď. Chodí se proto přímo na `http://ddev-turi-web` a správný
	 * virtual host se vybere touhle hlavičkou. Bez ní by Symfony navíc skládala
	 * absolutní URL s nepoužitelným hostitelem.
	 *
	 * Na produkci je prázdná.
	 */
	public function host_header(): string {
		return defined( 'KCT_TURINKA_HOST' ) ? (string) KCT_TURINKA_HOST : '';
	}

	/**
	 * Adresa Turinky pro odkazy a rámy vykreslené do stránky.
	 *
	 * Liší se od base_url() a je to důležité: base_url() je adresa pro volání
	 * ze serveru a lokálně míří přímo na kontejner (`http://ddev-turi-web`),
	 * kam se prohlížeč návštěvníka nedostane. Když je nastavená hlavička Host,
	 * je právě ona tím veřejným jménem.
	 */
	public function public_url(): string {
		$host = $this->host_header();

		return '' !== $host ? 'https://' . $host : $this->base_url();
	}

	/**
	 * Doména tohoto webu tak, jak se posílá do Turinky.
	 *
	 * Host bez `www.`, protože právě na tuhle adresu Turinka doručuje token —
	 * je to zároveň identita webu i důkaz, že ho žadatel ovládá.
	 */
	public function site_domain(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return preg_replace( '/^www\./i', '', strtolower( $host ) );
	}
}
