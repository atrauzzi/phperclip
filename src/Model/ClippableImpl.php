<?php namespace Atrauzzi\Phperclip\Model {


	trait ClippableImpl {

		/**
		 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
		 */
		public function files() {
			return $this->morphMany('Atrauzzi\Phperclip\Model\Clipping', 'clippable');
		}

		/**
		 * @param string $name
		 * @param mixed $value
		 */
		public function decorate($name, $value) {

			/** @var \Illuminate\Database\Eloquent\Model|\Atrauzzi\Phperclip\Model\Clippable $this */

			if(count($this->visible))
				$this->visible[] = $name;

			$this->setAttribute($name, $value);

		}

	}

}