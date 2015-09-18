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

			$table->string('name')->unique()->nullable();
			$table->string('disk');
			$table->string('mime_type');

			$table->timestamps();

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