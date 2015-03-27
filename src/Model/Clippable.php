<?php namespace Atrauzzi\Phperclip\Model {


	interface Clippable {

		/**
		 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
		 */
		public function files();

	}

}