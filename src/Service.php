<?php namespace Atrauzzi\Phperclip {

	use Atrauzzi\Phperclip\Processor\Manager;
	use Illuminate\Filesystem\FilesystemManager;
	//
	use Atrauzzi\Phperclip\Model\FileMeta;
	use Atrauzzi\Phperclip\Model\Clipping;
	use Atrauzzi\Phperclip\Model\Clippable;
	use Exception;


	class Service {

		/** @var \Illuminate\Contracts\Filesystem\Filesystem */
		protected $filesystem;

		/** @var string */
		protected $currentDisk;

		/** @var \Atrauzzi\Phperclip\Processor\Manager */
		protected $processorManager;

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

		/**
		 * @param string $name
		 * @param \Atrauzzi\Phperclip\Model\Clippable|null $clippable
		 * @return int
		 */
		public function exists($name, Clippable $clippable = null) {
			return FileMeta
				::named($name)
				->clippedTo($clippable)
				->count()
			;
		}

		//
		//
		//

		/**
		 * Saves a file from a resource.  This is the "purest" variant of save.
		 *
		 * @param $resource
		 * @param string $mimeType
		 * @param null|string $slot
		 * @param \Atrauzzi\Phperclip\Model\Clippable $clippable
		 * @return \Atrauzzi\Phperclip\Model\Clipping
		 * @throws \Exception
		 */
		public function save($resource, $mimeType, $slot = null, Clippable $clippable = null) {

			$fileMeta = FileMeta::create([
				'mime_type' => $mimeType,
				'disk' => $this->currentDisk,
			]);

			try {
				$this->saveFromResource($resource, $fileMeta);
			}
			catch(Exception $ex) {
				$fileMeta->forceDelete();
				throw $ex;
			}

			/** @var \Atrauzzi\Phperclip\Model\Clipping $clipping */
			$clipping = $fileMeta->clippings()->create([
				'slot' => $slot,
			]);

			return $clipping;

		}

		/**
		 * Saves a file from a PHP-accessible path.
		 *
		 * @param string $path
		 * @param null|string $slot
		 * @param \Atrauzzi\Phperclip\Model\Clippable $clippable
		 * @return \Atrauzzi\Phperclip\Model\Clippable|\Atrauzzi\Phperclip\Model\Clipping
		 * @throws \Exception
		 */
		public function saveFromPath($path, $slot = null, Clippable $clippable) {

			$resource = fopen($path, 'r');

			$finfoDb = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfoDb, $path);

			$clippable = $this->save($resource, '?!?!', $slot, $clippable);

			fclose($resource);
			return $clippable;

		}

		/**
		 * Saves a file from a URI.
		 *
		 * @param string $uri
		 * @param Clippable $clippable
		 * @param null|string $slot
		 * @return \Atrauzzi\Phperclip\Model\Clipping|null
		 * @throws \Exception
		 */
		public function saveFromUri($uri, $slot = null, Clippable $clippable = null) {

			stream_context_set_default(['http' => ['method' => 'HEAD']]);
			$head = get_headers($uri, true);
			$mimeType = (array)array_get($head, 'Content-Type');
			stream_context_set_default(['http' => ['method' => 'GET']]);
			$remoteResource = fopen($uri, 'r');

			$clippable = $this->save($remoteResource, array_pop($mimeType), $slot, $clippable);

			fclose($remoteResource);
			return $clippable;

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
		 * Stream a file to storage.
		 *
		 * @param $resource
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 */
		protected function saveFromResource($resource, FileMeta $fileMeta, array $options = []) {
			$this->getDisk()->getDriver()->putStream($this->filePath($fileMeta, $options), $resource);
		}

		/**
		 * Load an original image locally, trigger events and then save it as a derivative.
		 *
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param array $options
		 * @return false|resource
		 */
		protected function generateDerivative(FileMeta $fileMeta, array $options) {

			$originalResource = $this->getResource($fileMeta);
			$tempFile = tmpfile();
			stream_copy_to_stream($originalResource, $tempFile);
			rewind($tempFile);

			// ToDo: Emit before-save event.

			rewind($tempFile);
			$this->saveFromResource($tempFile, $fileMeta, $options);

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