<?php namespace Atrauzzi\Phperclip\Model {


	trait ClippableImpl {

		public function files() {
			return $this->morphMany('Atrauzzi\Phperclip\Model\File', 'clippable');
		}

	}

}