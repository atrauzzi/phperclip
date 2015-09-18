.<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;


class CreateClippingTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {

		Schema::create('phperclip_clipping', function (Blueprint $table) {

			$table->increments('id');

			$table->unsignedInteger('file_meta_id');

			$table->string('clippable_type')->nullable();
			$table->unsignedInteger('clippable_id')->nullable();

			$table->string('slot')->nullable();

			$table->timestamps();

			//
			//
			//

			$table->unique(
				[
					'clippable_id',
					'clippable_type',
					'slot'
				],
				'unique_clipping'
			);

		});

	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::drop('phperclip_clipping');
	}

}