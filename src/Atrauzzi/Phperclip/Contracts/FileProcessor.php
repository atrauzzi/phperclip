<?php namespace Atrauzzi\Phperclip\Contracts;

use Symfony\Component\HttpFoundation\File\File;
use \Atrauzzi\Phperclip\Model\FileMeta as FileModel;

interface FileProcessor {

	/**
	 * Registers this Processor for use with the file mime types specified.
	 * Must return an array of string mime types.
	 *
	 * @return array $mimeTypes
	 */
	public function registeredMimes();

	/**
	 * A method for doing pre-save operations such as validation. The file records will not be persisted,
	 * and the actual file will not be permanently stored if a false-type is return from this method.
	 *
	 * @param File $file
	 * @param array $options
	 * @return mixed
	 */
	public function onBeforeSave(File $file, array $options = []);

	/**
	 * A method to hook into the file save process. Allows intervention of the save operation by returning a false-type from
	 * this method.
	 *
	 * @param File $file
	 * @param array $options
	 * @return null|bool|\Symfony\Component\HttpFoundation\File\File
	 */
	public function onSave(File $file, array $options = []);

	/**
	 * A method to hook into the file delete process. Allows intervention of the delete operation by returning a false-type from
	 * this method.
	 *
	 * @param FileModel $fileModel
	 * @param array $options
	 * @return null|bool|\Atrauzzi\Phperclip\Model\FileMeta
	 */
	public function onDelete(FileModel $fileModel, array $options = []);

	/**
	 * A method to hook into the file move process. Allows intervention of the move operation by returning a false-type from
	 * this method.
	 *
	 * @param FileModel $fileModel
	 * @param array $options
	 * @return null|bool|\Atrauzzi\Phperclip\Model\FileMeta
	 */
	public function onMove(FileModel $fileModel, array $options = []);

}