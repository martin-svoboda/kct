<?php

namespace Kct\Facebook;

use DateTimeImmutable;
use Exception;
use Kct\Repositories\SettingsRepository;

/**
 * Kdy se má akce odeslat na Facebook.
 *
 * Pozvánka na pochod, který je za půl roku, na Facebooku zapadne dřív, než
 * bude aktuální — odesílá se proto s odstupem před začátkem akce.
 *
 * Čistá třída: žádné WordPress háky, žádné HTTP, žádný zápis. Dostane pole
 * akce a vrátí čas, nebo null pro „neodesílat".
 */
class ShareSchedule {

	/**
	 * Kolik dní před začátkem akce se odesílá.
	 *
	 * Dvanáctka není kulaté číslo, ale volba podle dne v týdnu. Většina akcí
	 * je o víkendu a zveřejňovat pozvánku o víkendu je špatně — lidi plánují
	 * další víkend na začátku týdne. Odstup 12 dní to trefí:
	 *
	 *     akce v sobotu  →  odeslání v pondělí
	 *     akce v neděli  →  odeslání v úterý
	 *
	 * Kdo tohle číslo mění, mění den v týdnu, na který odeslání padne.
	 */
	public const DEFAULT_LEAD_DAYS = 12;

	/** V kolik hodin se odesílá. */
	public const DEFAULT_HOUR = 9;

	public function __construct( private SettingsRepository $settings ) {
	}

	/**
	 * Čas odeslání akce, nebo null když se odeslat nemá.
	 *
	 * @param array    $event          Pole akce z Features\Events::get_event().
	 * @param int|null $override_days  Přepsání počtu dní u konkrétní akce; null = nastavení webu.
	 *
	 * @return int|null Unixový čas, nebo null pro „neodesílat".
	 */
	public function target_for_event( array $event, ?int $override_days = null ): ?int {
		$date = $this->start_date( $event );

		if ( '' === $date ) {
			// Bez data se nedá nic spočítat. Odeslat hned je lepší než
			// neodeslat vůbec — akce bez data se v datech z importu vyskytují.
			return time();
		}

		$zone = wp_timezone();

		try {
			$start = new DateTimeImmutable( $date . ' 00:00:00', $zone );
		} catch ( Exception $e ) {
			return time();
		}

		$now = new DateTimeImmutable( 'now', $zone );

		// Porovnává se den, ne okamžik: akce, která začíná dnes dopoledne, se
		// pořád ještě pošle. Až akce od včerejška dál se považuje za
		// proběhlou — pozvánka na loňský pochod je horší než žádný příspěvek.
		if ( $start->format( 'Y-m-d' ) < $now->format( 'Y-m-d' ) ) {
			return null;
		}

		$target = $start
			->setTime( $this->hour(), 0 )
			->modify( '-' . $this->lead_days( $override_days ) . ' days' );

		// Akce, která začíná dřív než za nastavený odstup, se odešle hned.
		return max( $target->getTimestamp(), time() );
	}

	/**
	 * Kolik dní předem se odesílá — po zohlednění přepsání u akce.
	 *
	 * @param int|null $override Přepsání u konkrétní akce; null = nastavení webu.
	 */
	public function lead_days( ?int $override = null ): int {
		if ( null !== $override ) {
			return max( 0, $override );
		}

		$value = $this->settings->get_option( 'fb_event_lead_days' );

		return is_numeric( $value ) ? max( 0, (int) $value ) : self::DEFAULT_LEAD_DAYS;
	}

	/** V kolik hodin se odesílá. */
	public function hour(): int {
		$value = $this->settings->get_option( 'fb_event_hour' );

		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_HOUR;
		}

		$hour = (int) $value;

		return ( $hour >= 0 && $hour <= 23 ) ? $hour : self::DEFAULT_HOUR;
	}

	/**
	 * Datum začátku akce ve tvaru Y-m-d, nebo prázdný řetězec.
	 *
	 * Přednost má `start.date`, protože to je skutečný začátek; `date` je
	 * u části importovaných akcí jediné, co je vyplněné.
	 */
	private function start_date( array $event ): string {
		foreach ( array( $event['start']['date'] ?? '', $event['date'] ?? '' ) as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}
}
