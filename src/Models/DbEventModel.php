<?php

namespace Kct\Models;

use KctDeps\Wpify\Model\Attributes\Column;
use KctDeps\Wpify\Model\Attributes\ReadOnlyProperty;
use KctDeps\Wpify\Model\Model;

/**
 * Event from kct-db model
 */
class DbEventModel extends Model {
	#[Column( type: Column::INT, auto_increment: true, primary_key: true )]
	public int $id = 0;

	#[Column( type: Column::INT )]
	public int $db_id = 0;

	#[Column( type: Column::VARCHAR, params: 255 )]
	public string $date = '';

	#[Column( type: Column::VARCHAR, params: 255 )]
	public string $title = '';

	#[Column( type: Column::INT )]
	public float $year = 0;

	#[Column( type: Column::VARCHAR, params: 255 )]
	public string $place = '';

	#[Column( type: Column::VARCHAR, params: 255 )]
	public string $district = '';

	#[Column( type: Column::VARCHAR, params: 255 )]
	public string $web = '';

	#[Column( type: Column::VARCHAR, params: 255 )]
	public float $region = 0;

	#[Column( type: Column::VARCHAR, params: 255 )]
	public float $department = 0;

	#[Column( type: Column::TEXT )]
	public array $organiser = array();

	#[Column( type: Column::TEXT )]
	public array $start = array();

	#[Column( type: Column::TEXT )]
	public array $finish = array();

	#[Column( type: Column::TEXT )]
	public string $content = '';

	#[Column( type: Column::TEXT )]
	public array $contact = array();

	#[Column( type: Column::JSON, nullable: true )]
	public array $details = array();

	#[Column( type: Column::JSON, nullable: true )]
	public array $image = array();

	#[Column( type: Column::JSON, nullable: true )]
	public array $proposal = array();

	#[ReadOnlyProperty]
	public string $permalink = '';

	public function get_permalink() {
		return home_url( sprintf( 'akce-db/%s', $this->db_id ) );
	}

	public function to_array( array $props = array(), array $recursive = array() ): array {
		$data = parent::to_array( $props, $recursive );

		$data['lng'] = '';
		$data['lat'] = '';
		if ( ( isset( $data['start'] ) && ! empty( $data['start']['gps_n'] ) && ! empty( $data['start']['gps_e'] ) ) ) {
			$data['lng'] = $data['start']['gps_e'];
			$data['lat'] = $data['start']['gps_n'];
		} elseif ( isset( $data['finish'] ) && ! empty( $data['finish']['gps_n'] ) && ! empty( $data['finish']['gps_e'] ) ) {
			$data['lng'] = $data['finish']['gps_e'];
			$data['lat'] = $data['finish']['gps_n'];
		}

		$event_types = get_option( 'event_types' );
		/*if ( $event_types && ! empty( $data['details'] ) && is_array( $data['details'] ) ) {
			if ( isset( $data['details']['detailid'] ) ) {
				$detail            = $data['details'];
				$data['details']   = [];
				$data['details'][] = $detail;
			}
			foreach ( $data['details'] as $key => $detail ) {
				if ( ! isset( $event_types[ $detail['detailid'] ] ) ) {
					continue;
				}

				//$data['details'][ $key ]['icon'] = $event_types[ $detail['detailid'] ]['icon'];
			}
		}*/

		return $data;
	}
}
