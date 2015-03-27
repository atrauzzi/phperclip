<?php namespace Atrauzzi\Phperclip\Contracts;

use Atrauzzi\Phperclip\Model\FileMeta;

interface FileNameGenerator {

	/**
	 * Generate the file name based on the file and any options passed in.
	 *
	 * @param FileMeta $file
	 * @param array $options
	 * @return mixed
	 */
	public function fileName(FileMeta $file, array $options = []);

	/**
	 * This is the name of the array key which to create file variations from its corresponding values.
	 *
	 * @return string
	 */
	public function getFileModificationKey();

} 