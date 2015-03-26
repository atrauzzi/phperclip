<?php namespace Atrauzzi\Phperclip\Processor {

	use Atrauzzi\Phperclip\Contracts\FileProcessor;
	use Atrauzzi\Phperclip\Model\FileMeta;


	class Manager {

		/**
		 * @var \Atrauzzi\Phperclip\Processor\FileProcessorAdapter[]
		 */
		protected $processors;

		/**
		 * @param \Atrauzzi\Phperclip\Processor\FileProcessorAdapter[] $processors
		 */
		public function __construct(array $processors = []) {
			$this->processors = $processors;
		}

		/**
		 * @param resource $fileResource
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param string $action
		 * @param array $options
		 * @return bool
		 */
		public function dispatch($fileResource, FileMeta $fileMeta, $action, array $options = []) {

			$mimeType = $fileMeta->getMimeType();

			foreach($this->getProcessorsFor($mimeType) as $processor)
				if(method_exists($processor, $action))
					$processor->$action($fileResource, $fileMeta, $options);

		}

		//
		//
		//

		/**
		 * Retrieve all processors which are registered to act on the mimetype.
		 *
		 * @param $mimeType
		 * @return \Atrauzzi\Phperclip\Processor\FileProcessorAdapter[]
		 */
		protected function getProcessorsFor($mimeType) {

			return array_filter($this->processors, function (FileProcessor $processor) use ($mimeType) {
				return in_array($mimeType, $processor->registeredMimes());
			});

		}

	}

}