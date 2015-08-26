<?php namespace Atrauzzi\Phperclip\Processor {

	use Atrauzzi\Phperclip\Model\FileMeta;
	use Atrauzzi\Phperclip\Model\Clippable;


	interface Contract {

		/**
		 * @param $resource
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param \Atrauzzi\Phperclip\Model\Clippable|null $clippable
		 */
		public function process($resource, FileMeta $fileMeta, Clippable $clippable = null);

		/**
		 * @return string
		 */
		public function getName();

	}

}