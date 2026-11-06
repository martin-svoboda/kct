<?php
/**
 * Konfigurace endpointu aktualizací.
 *
 * Žádné tajemství tady není a být nemůže: repozitář pluginu je veřejný,
 * GitHub API se volá bez tokenu a basic auth na přehled je v .htaccess,
 * s heslem v souboru mimo repozitář. Soubor se proto nasazuje jako každý jiný.
 */

declare( strict_types=1 );

return array(
	'slug'            => 'kct',
	'name'            => 'KČT — šablona pro odbory a oblasti',
	'description'     => 'Šablona a funkce pro weby odborů a oblastí Klubu českých turistů.',
	'homepage'        => 'https://napoveda.sokct.cz/',
	'author'          => 'Martin Svoboda',
	'author_homepage' => 'https://martin-svoboda.cz',
	'repo'            => 'martin-svoboda/kct',

	// Instalační ZIP se v příloze vydání pozná podle názvu; deploy.yml ho
	// pojmenovává kct-<verze>.zip.
	'asset_pattern'   => '/^kct-.+\.zip$/',

	// 15 minut. Při čtyřech obnovách za hodinu zbývá u limitu GitHub API
	// (60/h na IP bez tokenu) dost rezervy, takže token není potřeba.
	'cache_ttl'       => 900,

	'retention_days'  => 90,

	// Po kolika dnech bez dotazu se doména v přehledu zvýrazní.
	'stale_days'      => 14,

	// Uvnitř docrootu, a je to vědomé. Subdoména je na tomhle hostingu
	// obyčejná složka vedle `www`, takže sourozenec docrootu by se sám stal
	// další subdoménou (`data.sokct.cz`). Přístup zvenčí zakazuje .htaccess.
	'data_dir'        => __DIR__ . '/data',
);
