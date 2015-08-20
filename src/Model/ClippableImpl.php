<?php namespace Atrauzzi\Phperclip\Model {


	trait ClippableImpl {

		/**
		 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
		 */
		public function files() {
			return $this->morphMany('Atrauzzi\Phperclip\Model\Clipping');
		}

	}

}