<?php

namespace Kct\Models;

use KctDeps\Wpify\Model\Attributes\Column;
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
	public string $title = '';

	#[Column( type: Column::INT )]
	public float $year = 0;

	#[Column( type: Column::TEXT )]
	public string $place = '';

	#[Column( type: Column::VARCHAR, params: 255 )]
	public string $district = '';

	#[Column( type: Column::TEXT )]
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
	public string $description = '';

	#[Column( type: Column::JSON, nullable: true )]
	public array $details = array();

	#[Column( type: Column::JSON, nullable: true )]
	public array $images = array();


}