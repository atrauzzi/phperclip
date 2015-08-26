<?php namespace Atrauzzi\Phperclip\Model {


	interface Clippable {

		/**
		 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
		 */
		public function files();

		/**
		 * Attaches an arbitrary key and value to any serialized representation.
		 *
		 * @param string $name
		 * @param mixed $value
		 */
		public function decorate($name, $value);

	}

}