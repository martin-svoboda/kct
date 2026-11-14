<?php

namespace Kct\Turinka;

use Kct\Repositories\SettingsRepository;

/**
 * Předpověď počasí k akci — vkládaný widget Turinky v postranním sloupci.
 *
 * Samostatný widget, ne modul widgetu akce: Turinka ho má na vlastní adrese
 * `/vlozit/pocasi` se souřadnicemi a rozmezím dní, aby šla předpověď vložit
 * i tam, kde žádná akce není. Vkládá se stejně jako widget akce — rámem, ne
 * skriptem, a výšku rámu dopočítá tentýž hostitelský skript.
 *
 * Token není potřeba, předpověď je veřejná.
 */
class Weather {
	/** Pole v nastavení, kterým se předpověď vypíná. */
	const OPTION = 'turinka_weather';

	/**
	 * Kolik dní dopředu předpověď dosahuje, dnešek v to počítaje.
	 *
	 * Dál Turinka nedohlédne a widget to sám ohlásí hláškou. Hláška místo
	 * předpovědi je ale u akce na podzim pro akci na jaře k ničemu, tak se
	 * rám radši nevkládá.
	 */
	const HORIZON_DAYS = 16;

	/**
	 * Obdélník, pro který Turinka předpověď dává — ostatní místa odmítne.
	 *
	 * Vlastní kontrola je tu kvůli tomu, aby na stránce akce nestála
	 * chybová stránka v rámu; rozhodnutí samo patří Turince, která hranici
	 * drží kvůli limitu svého zdroje dat. Rezerva je štědrá úmyslně —
	 * přesná hranice se tady hlídat nemá.
	 */
	const LAT_MIN = 48.5;
	const LAT_MAX = 51.1;
	const LNG_MIN = 12.0;
	const LNG_MAX = 18.9;

	public function __construct(
		private Config $config,
		private SettingsRepository $settings
	) {
	}

	/**
	 * Má se předpověď na tomhle webu vkládat?
	 *
	 * Výchozí je zapnuto a propojení k tomu potřeba není. Rozlišuje se
	 * „neuloženo“ od „uloženo vypnuté“ — get_option() vrací false pro obojí,
	 * takže by web, který volbu nikdy neviděl, předpověď nezobrazoval.
	 */
	public function is_enabled(): bool {
		$options = $this->settings->get_options();

		return ! array_key_exists( self::OPTION, $options ) || (bool) $options[ self::OPTION ];
	}

	/**
	 * Hotové HTML předpovědi, nebo prázdný řetězec.
	 *
	 * Prázdný znamená „není co předpovídat“ — akce už byla, nemá souřadnice,
	 * je dál než dosah předpovědi, nebo není v Česku.
	 *
	 * @param array $event Akce z Events::get_event().
	 */
	public function snippet( array $event ): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		$place = $this->place( $event );

		if ( ! $place ) {
			return '';
		}

		$days = $this->days( $event );

		if ( ! $days ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'lat' => $place['lat'],
				'lng' => $place['lng'],
				'od'  => $days['from'],
				'do'  => $days['to'],
			),
			$this->config->public_url() . '/vlozit/pocasi'
		);

		return sprintf(
			'<div class="kct-turinka-weather">'
			. '<h2 class="event-section__title">%1$s</h2>'
			. '<iframe src="%2$s" data-turi-embed style="width:100%%;border:0;height:320px" loading="lazy" title="%1$s"></iframe>'
			. '</div>',
			esc_html__( 'Předpověď počasí', 'kct' ),
			esc_url( $url )
		);
	}

	/**
	 * Souřadnice startu akce, pokud se pro ně předpověď dá získat.
	 *
	 * @return array{lat: float, lng: float}|null
	 */
	private function place( array $event ): ?array {
		$lat = $event['lat'] ?? '';
		$lng = $event['lng'] ?? '';

		// Prázdný prvek feedu se uloží jako prázdné pole, ne jako prázdný
		// řetězec — is_numeric() na poli vrátí false a tím je to vyřešené.
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}

		$lat = (float) $lat;
		$lng = (float) $lng;

		if ( $lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX ) {
			return null;
		}

		return array( 'lat' => $lat, 'lng' => $lng );
	}

	/**
	 * Rozmezí dní, na které se má předpověď ukázat.
	 *
	 * U vícedenní akce celé její trvání, u jednodenní ten jeden den. Rozmezí
	 * se zařízne na dosah předpovědi z obou stran: den, který už byl, se
	 * nepředpovídá a den za obzorem se nepředpoví.
	 *
	 * @return array{from: string, to: string}|null
	 */
	private function days( array $event ): ?array {
		$start  = $this->day( $event['start']['date'] ?? '' ) ?: $this->day( $event['date'] ?? '' );
		$finish = $this->day( $event['finish']['date'] ?? '' ) ?: $start;

		if ( null === $start ) {
			return null;
		}

		// Podle dne, ne podle okamžiku: akce, která je dnes, má předpověď mít
		// i odpoledne.
		$today = (int) strtotime( 'today' );
		$last  = $today + ( self::HORIZON_DAYS - 1 ) * DAY_IN_SECONDS;

		if ( $finish < $start ) {
			$finish = $start;
		}

		if ( $finish < $today || $start > $last ) {
			return null;
		}

		return array(
			'from' => gmdate( 'Y-m-d', max( $start, $today ) ),
			'to'   => gmdate( 'Y-m-d', min( $finish, $last ) ),
		);
	}

	/**
	 * Začátek dne z data v akci, nebo null.
	 *
	 * Formát se liší podle toho, odkud akce přišla, takže se datum nečte
	 * vzorem, ale strtotime() — stejně jako zbytek stránky akce. 'today'
	 * zahodí případný čas, aby se dny daly porovnávat.
	 */
	private function day( $value ): ?int {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}

		$time = strtotime( $value );

		return false !== $time ? (int) strtotime( 'today', $time ) : null;
	}
}
