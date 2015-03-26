<?php namespace Atrauzzi\Phperclip {

	use Atrauzzi\Phperclip\Repository\FileMeta as FileMetaRepository;
	use Atrauzzi\Phperclip\Processor\Manager;
	use Illuminate\Filesystem\FilesystemManager;
	//
	use Atrauzzi\Phperclip\Model\FileMeta;
	use Atrauzzi\Phperclip\Model\Clippable;
	use Symfony\Component\HttpFoundation\File\File;
	use Exception;


	class Service {

		/** @var \Illuminate\Contracts\Filesystem\Filesystem */
		protected $filesystem;

		/** @var string */
		protected $currentDisk;

		/** @var \Atrauzzi\Phperclip\Processor\Manager */
		protected $processorManager;

		/** @var string */
		protected $localDiskName = 'local';

		/**
		 * @param Manager $processManager
		 * @param \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Filesystem\FilesystemManager $filesystem
		 */
		public function __construct(
			Manager $processManager,
			FilesystemManager $filesystem
		) {
			$this->processorManager = $processManager;
			$this->filesystem = $filesystem;
		}

		/**
		 * Select which drive Phperclip uses by default.
		 *
		 * @param $drive
		 */
		public function useDisk($drive) {
			$this->currentDisk = $drive;
		}

		/**
		 * Gets the current or specified drive.
		 *
		 * @param null|string $disk
		 * @return \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Filesystem\FilesystemAdapter
		 */
		protected function getDisk($disk = null) {
			return $this->filesystem->disk($disk ?: $this->currentDisk);
		}

		/**
		 * @param string $diskName
		 */
		protected function setLocalDiskName($diskName) {
			$this->localDiskName = $diskName;
		}

		/**
		 * @return \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Filesystem\FilesystemAdapter
		 */
		protected function getLocalDisk() {
			return $this->getDisk($this->localDiskName);
		}

		//
		//
		//

		/**
		 * Retrieves FileMeta by its id.
		 *
		 * @param int $id
		 * @return Model\FileMeta
		 */
		public function find($id) {
			return FileMeta::find($id);
		}

		/**
		 * Obtains FileMeta by its Clippable and slot.
		 *
		 * @param string $slot
		 * @param Clippable $clippable
		 * @return \Atrauzzi\Phperclip\Model\FileMeta
		 */
		public function findBySlot($slot, Clippable $clippable = null) {

			if($clippable)
				$query = FileMeta::forClippable(get_class($clippable), $clippable->getKey());
			else
				$query = FileMeta::unattached();

			return $query->inSlot($slot)->first();

		}

		//
		//
		//

		/**
		 * Returns an internet-facing URI that can be used to download the resource.
		 *
		 * @param FileMeta $fileMeta
		 * @param array $options
		 * @return string
		 */
		public function getPublicUri(FileMeta $fileMeta, array $options = []) {

			// This will ensure that the file exists.
			$resource = $this->getResource($fileMeta, $options);

			// ToDo: Move uri generating calls from drivers to here.
			//return $this->getDriver()->getPublicUri($fileMeta, $options);

		}

		/**
		 * Returns a resource identifier that points to the desired file.
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 * @return false|resource
		 */
		public function getResource(FileMeta $fileMeta, array $options = []) {

			$disk = $this->getDisk()->getDriver();
			$path = $this->filePath($fileMeta, $options);

			// If it isn't the original and it hasn't been generated yet.
			if(!empty($options) && !$disk->has($path))
				$resource = $this->generateDerivative($fileMeta, $options);
			else
				$resource = $disk->readStream($path);

			return $resource;

		}

		/**
		 * Returns a file URI based on the id of the original.
		 *
		 * @param $id
		 * @return string
		 */
		public function getPublicUriById($id) {
			return $this->getPublicUri($this->find($id));
		}

		/**
		 * Returns a File URI based on the slot and clippable
		 *
		 * @param $slot
		 * @param Clippable $clippable
		 * @return null|string
		 */
		public function getPublicUriBySlot($slot, Clippable $clippable = null) {

			if($file = $this->findBySlot($slot, $clippable)) {
				return $this->getPublicUri($file);
			}

			return null;

		}

		/**
		 * Get all the files by mimetype or slot, attached or unattached to a model
		 *
		 * @param null|Clippable $clippable
		 * @param null|string|array $mimeTypes
		 * @param null|string|int|array $slot
		 * @return \Illuminate\Database\Eloquent\Collection
		 */
		public function getFilesFor(Clippable $clippable = null, $mimeTypes = null, $slot = null) {

			$query = $clippable ? $clippable->files() : FileMeta::query();

			// Filter by slot(s)
			if($slot) {

				$slot = is_array($slot) ? $slot : [$slot];

				$query->whereIn('slot', $slot);

			}

			// Filter by file type(s)
			if($mimeTypes) {
				$mimeTypes = (array)$mimeTypes;
				$query->whereIn('mime_type', $mimeTypes);
			}

			return $query->get();

		}

		/**
		 * Saves a new file from the servers filesystem.
		 *
		 * @param File $file
		 * @param Clippable $clippable
		 * @param array $options
		 * @return null|FileMeta
		 */
		public function saveFromFile(File $file, Clippable $clippable = null, array $options = []) {

			// Create the original file record
			$newFile = $this->createFileRecord($file, $options);
			$this->saveOriginalFile($file, $newFile);

			// Optionally attach the file to a model
			if($clippable) {
				$clippable->clippedFiles()->save($newFile);
			}

			// Save a modified copy of the original file
			$this->saveFromResource($this->getDriver()->tempOriginal($newFile), $newFile, $options);

			return $newFile;
		}

		/**
		 * Saves a file from a URI.
		 *
		 * @param string $uri
		 * @param Clippable $clippable
		 * @param array $options
		 * @return \Atrauzzi\Phperclip\Model\FileMeta|null
		 * @throws \Exception
		 */
		public function saveFromUri($uri, Clippable $clippable = null, array $options = []) {

			stream_context_set_default(['http' => [ 'method' => 'HEAD' ]]);
			$head = get_headers($uri, true);
			stream_context_set_default([]);

			$remoteResource = fopen($uri, 'r');

			$attributes = [
				'mime_type' => array_get($head, 'Content-Type')
			];

			if($clippable)
				$fileMeta = $clippable->files()->create($attributes);
			else
				$fileMeta = FileMeta::create($attributes);

			try {
				$this->saveFromResource($remoteResource, $fileMeta, $options);
			}
			catch(Exception $ex) {
				$fileMeta->forceDelete();
				throw $ex;
			}

			fclose($remoteResource);
			return $fileMeta;

		}

		/**
		 * Delete a file (and all its derivatives if the original).
		 *
		 * @param FileMeta $fileMeta
		 * @param array $options
		 */
		public function delete(FileMeta $fileMeta, array $options = []) {

			if(empty($options)) {
				$this->getDisk()->deleteDirectory($this->fileDirectory($fileMeta, $options));
				$fileMeta->forceDelete();
			}
			else {
				$this->getDisk()->delete($this->filePath($fileMeta, $options));
			}

		}

		/**
		 * Deletes a file (and all its derivaives if the original) by id.
		 *
		 * @param $id
		 * @param array $options
		 */
		public function deleteById($id, array $options = []) {
			if($file = $this->find($id))
				$this->delete($file, $options);
		}

		/**
		 * Deletes a file (and all its derivatives if the original) by clippable and slot.
		 *
		 * @param string $slot
		 * @param \Atrauzzi\Phperclip\Model\Clippable $clippable
		 */
		public function deleteBySlot(Clippable $clippable = null, $slot, $options = []) {
			if($file = $this->findBySlot($slot, $clippable))
				$this->delete($file, $options);
		}

		//
		//
		//

		/**
		 * Load an original image locally, trigger events and then save it as a derivative.
		 *
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 * @return false|resource
		 */
		protected function generateDerivative(FileMeta $fileMeta, array $options) {

			$originalResource = $this->getResource($fileMeta, []);

			$tempFilePath = uniqid('phperclip/');

			$this->getLocalDisk()->getDriver()->putStream($tempFilePath, $originalResource);
			$localResource = $this->getLocalDisk()->getDriver()->readStream($tempFilePath);

			// ToDo: Emit before-save event.

			$this->saveFromResource($localResource, $fileMeta, $options);

			// ToDo: Emit after-save event.

			return $this->getResource($fileMeta, $options);

		}

		/**
		 * Stream a file to storage.
		 *
		 * @param $resource
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 */
		protected function saveFromResource($resource, FileMeta $fileMeta, array $options) {
			$this->getDisk()->getDriver()->putStream($this->filePath($fileMeta, $options), $resource);
		}

		/**
		 * Generates a file name to use for storage.
		 *
		 * ToDo: Cache this.
		 *
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 * @return string
		 */
		public function filePath(FileMeta $fileMeta, array $options) {
			return sprintf('%s/%s',
				$this->fileDirectory($fileMeta, $options),
				$this->fileName($fileMeta, $options)
			);
		}

		/**
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 * @return string|int
		 */
		public function fileName(FileMeta $fileMeta, array $options) {
			return $this->generateHash($fileMeta, $options);
		}

		/**
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 * @return string|int
		 */
		public function fileDirectory(FileMeta $fileMeta, array $options) {
			return $fileMeta->getKey();
		}

		/**
		 * Generates an MD5 hash of the file attributes and options.
		 *
		 * @param FileMeta $fileMeta
		 * @param array $options
		 * @return string
		 */
		protected function generateHash(FileMeta $fileMeta, array $options) {
			$options['id'] = $fileMeta->getKey();
			return md5(json_encode($this->recursiveKeySort($options)));
		}

		/**
		 * Utility method to ensure that key signatures always appear in the same order.
		 *
		 * @param array $array
		 * @return array
		 */
		protected function recursiveKeySort(array $array) {

			ksort($array);

			foreach($array as $key => $value)
				if(is_array($value))
					$array[$key] = $this->recursiveKeySort($value);

			return $array;

		}

	}

}