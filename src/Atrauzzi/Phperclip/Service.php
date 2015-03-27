<?php namespace Atrauzzi\Phperclip {

	use Atrauzzi\Phperclip\Processor\Manager;
	use Illuminate\Filesystem\FilesystemManager;
	//
	use Atrauzzi\Phperclip\Model\FileMeta;
	use Atrauzzi\Phperclip\Model\Clippable;
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

		/** @var array */
		protected $publicPrefixes;

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

			// For now just rip the configs out of Laravel.
			$this->setPublicPrefixes(config('phperclip.public_prefixes', []));

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
		 * @param string[] $prefixes
		 */
		public function setPublicPrefixes(array $prefixes) {
			$this->publicPrefixes = $prefixes;
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

			// This will ensure that the original or derivative exists.
			$this->getResource($fileMeta, $options);

			$publicPrefix = array_get($this->publicPrefixes, $this->currentDisk);

			return $publicPrefix . $this->filePath($fileMeta, $options);

		}

		/**
		 * Returns a resource identifier that points to the desired file.
		 *
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

		//
		//
		//

		/**
		 * Stream a file to storage.
		 *
		 * @param $resource
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 */
		public function saveFromResource($resource, FileMeta $fileMeta, array $options) {
			$this->getDisk()->getDriver()->putStream($this->filePath($fileMeta, $options), $resource);
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
				$fileMeta->delete();
			}
			else {
				$this->getDisk()->delete($this->filePath($fileMeta, $options));
			}

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