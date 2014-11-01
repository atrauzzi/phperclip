<?php namespace TippingCanoe\Phperclip\Processes;

use Symfony\Component\HttpFoundation\File\File;
use TippingCanoe\Phperclip\Model\File as FileModel;

class ProcessManager {

	/**
	 * @var \TippingCanoe\Phperclip\Processes\FileProcessor[]
	 */
	protected $processors;

	public function __construct(array $processors = null) {

		$this->processors = $processors;
	}

	/**
	 * @param $file
	 * @param $action
	 * @return bool
	 */
	public function dispatch($file, $action) {

		// Do not process if the file is not an expected file type object.
		if (!($this->validFileObject($file))) {
			return null;
		}

		$mimeType = $file->getMimeType();

		if ($processors = $this->getProcessorsFor($mimeType)) {
			foreach ($processors as $processor) {

				// Call the processor method
				if (method_exists($processor, $action)) {
					$file = $processor->$action($file);
				}

				// If we return anything but the file here, stop the processing.
				if (!($this->validFileObject($file))) {
					return null;
				}
			}
		}

		return $file;
	}

	/**
	 * Retrieve all processors which are registered to act on the mimetype.
	 *
	 * @param $mimeType
	 * @return null|array|\TippingCanoe\Phperclip\Processes\FileProcessor[]
	 */
	protected function getProcessorsFor($mimeType) {
		if(empty($this->processors)) return null;

		return array_filter($this->processors, function($processor) use($mimeType){
			return in_array($mimeType, $processor->registeredMimes());
		});
	}

	/**
	 * @param $file
	 * @return bool
	 */
	private function validFileObject($file) {

		return $file instanceof File || $file instanceof FileModel;
	}
}