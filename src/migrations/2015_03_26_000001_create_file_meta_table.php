<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;


class CreateFileMetaTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {

		Schema::create('phperclip_file_meta', function (Blueprint $table) {

			$table->increments('id');

			$table->unsignedInteger('clippable_id')->nullable();

			$table->string('clippable_type')->nullable();

			$table->string('slot')->nullable();

			$table->string('mime_type');

			$table->timestamps();

			//
			// Indexes
			//

			$table->unique([
				'clippable_id',
				'clippable_type',
				'slot'
			]);

		});

	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::drop('phperclip_file_meta');
	}

}