<?php

namespace Kct\Models;

use DateTime;
use Kct\Repositories\EventRepository;
use KctDeps\Wpify\Model\Attributes\Meta;
use KctDeps\Wpify\Model\Attributes\ReadOnlyProperty;
use KctDeps\Wpify\Model\Post;

/**
 * @method EventRepository model_repository()
 */
class EventModel extends Post {
	#[Meta]
	public ?int $db_id;

	#[Meta]
	public ?float $year;

	#[Meta]
	public ?string $place;

	#[Meta]
	public ?string $district;

//	#[Meta]
//	public ?string $web;
//
//	#[Meta]
//	public ?float $region;
//
//	#[Meta]
//	public ?float $department;

	#[Meta]
	public ?array $organiser;

	#[Meta]
	public ?array $start;

	#[Meta]
	public ?array $finish;

	#[Meta]
	public ?array $contact;

	#[Meta]
	public ?array $details;

	#[Meta]
	public ?array $proposal;

	#[Meta]
	public ?array $start_date;

	#[ReadOnlyProperty]
	public ?string $date;

//	#[ReadOnlyProperty]
//	public string $title;
//
//	#[ReadOnlyProperty]
//	public string $content;

	public function get_date() {
		return isset( $this->start ) && ! empty( $this->start ) && $this->start['date'] ? $this->start['date'] : '';
	}

	public function to_array( array $props = array(), array $recursive = array() ): array {
		$data = parent::to_array( $props, $recursive );
		if ( $this->featured_image ) {
			$data['image'] = array(
				'url' => get_the_post_thumbnail_url( $this->id ),
			);
		}

		$data['lng'] = '';
		$data['lat'] = '';
		if ( ( isset( $data['start'] ) && ! empty( $data['start']['gps_n'] ) && ! empty( $data['start']['gps_e'] ) ) ) {
			$data['lng'] = $data['start']['gps_e'];
			$data['lat'] = $data['start']['gps_n'];
		} elseif (  isset( $data['finish'] ) && ! empty( $data['finish']['gps_n'] ) && ! empty( $data['finish']['gps_e'] ) ) {
			$data['lng'] = $data['finish']['gps_e'];
			$data['lat'] = $data['finish']['gps_n'];
		}

		if ( $data['date'] ) {
			$data['formated_date'] = array(
				'day_name' => date_i18n( 'l', strtotime( $data['date'] ) ),
				'number'   => date_i18n( 'j. n.', strtotime( $data['date'] ) ),
				'year'     => date_i18n( 'Y', strtotime( $data['date'] ) ),
			);
		}

		return $data;
	}
}
