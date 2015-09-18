<?php namespace Atrauzzi\Phperclip {

	use Illuminate\Filesystem\FilesystemManager;
	//
	use Atrauzzi\Phperclip\Model\FileMeta;
	use Atrauzzi\Phperclip\Model\Clipping;
	use Atrauzzi\Phperclip\Model\Clippable;
	use Atrauzzi\Phperclip\Processor\Contract as Processor;
	use Exception;


	class Service {

		/** @var \Illuminate\Contracts\Filesystem\Filesystem */
		protected $filesystem;

		/** @var string */
		protected $currentDisk;

		/** @var array */
		protected $publicPrefixes;

		/**
		 * @param \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Filesystem\FilesystemManager $filesystem
		 */
		public function __construct(FilesystemManager $filesystem) {

			$this->filesystem = $filesystem;

			// For now just rip the configs out of Laravel.
			$this->setPublicPrefixes(config('phperclip.public_prefixes', []));
			$this->currentDisk = config('filesystems.default', 'local');

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
		 * @param \Atrauzzi\Phperclip\Model\Clippable $clippable
		 * @param string $slot
		 * @param string|null $processorName
		 * @throws \Exception
		 */
		public function decorate(Clippable $clippable, $slot, $processorName = null) {

			$clipping = $clippable->files()->inSlot($slot)->first();

			if(!$clipping)
				return;

			$fileMeta = $clipping->fileMeta;

			if($processorName) {
				/** @var \Atrauzzi\Phperclip\Processor\Contract $processor */
				$processor = app($processorName);
				$options = ['processor' => $processorName];
			}
			else
				$options = [];

			if(!empty($options))
				$resource = $this->ensureDerivative($fileMeta, $clippable, $options);

			$url = $this->getPublicUri($fileMeta, $options);

			$name = sprintf('%s%s_url',
				$slot,
				(empty($processor) ?
					''
					: ('_' . $processor->getName())
				)
			);

			$clippable->decorate($name, $url);

		}

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

			$publicPrefix = array_get($this->publicPrefixes, $fileMeta->getDisk());

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
			return $disk->readStream($path);

		}

		/**
		 * Does the right thing and saves/updates a file against a clippable.
		 *
		 * @param \Atrauzzi\Phperclip\Model\FileMeta|resource|string|null $file
		 * @param null $name
		 * @param \Atrauzzi\Phperclip\Model\Clippable $clippable
		 * @param null $slot
		 * @return \Atrauzzi\PhperClip\Model\FileMeta
		 * @throws \Exception
		 */
		public function clip($file, $name = null, Clippable $clippable, $slot = null) {

			$fileMeta = null;

			// If the caller just derped out.
			if(!$file && !$name)
				throw new Exception('You must provide something to attach to the Clippable.');

			// If the file requested doesn't exist.
			if($name && !$file && !$fileMeta = $this->find($name))
				throw new Exception('Unable to find a file by that name.');

			// If the file already exists and we want to overwrite it.
			if($file && $name && $fileMeta)
				$this->delete($fileMeta);

			// Check to see if the file already exists, we can deduplicate it.
			if($file && $name && !$fileMeta)
				$fileMeta = FileMeta::named($name)->first();

			//
			//

			// By this point, if we have a FileMeta, then we don't need to take a copy of what was provided.

			if($file && !$fileMeta)
				$fileMeta = $this->save($file, $name);

			if(!$fileMeta)
				throw new Exception('Unable to save file.');

			//
			//

			if($clippable) {

				/** @var \Illuminate\Database\Eloquent\Model|\Atrauzzi\Phperclip\Model\Clippable $existingClipping */
				if($slot && $existingClipping = Clipping::select('phperclip_clipping.*')->inSlot($slot)->named($name)->forClippable($clippable)->first()) {
					$existingClipping->update([
						'slot' => null,
					]);
				}

				$this->attach($fileMeta, $clippable, $slot);

			}

			return $fileMeta;

		}

		/**
		 * Associate a FileMeta to a Clippable
		 *
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param \Atrauzzi\Phperclip\Model\Clippable $clippable
		 * @param null|string $slot
		 */
		public function attach(FileMeta $fileMeta, Clippable $clippable, $slot = null) {
			$clippable
				->files()
				->create([
					'file_meta_id' => $fileMeta->getKey(),
					'slot' => $slot,
				])
			;
		}

		//
		//
		//

		/**
		 * @param string $name
		 * @param \Atrauzzi\Phperclip\Model\Clippable|null $clippable
		 * @return \Atrauzzi\PhperClip\Model\FileMeta
		 */
		public function find($name, Clippable $clippable = null) {
			return FileMeta
				::named($name)
				->clippedTo($clippable)
				->first()
			;
		}

		/**
		 * Convenient auto-guessing save router.
		 *
		 * @param resource|string $file
		 * @param null|string $name
		 * @return \Atrauzzi\Phperclip\Model\FileMeta
		 * @throws \Exception
		 */
		public function save($file, $name = null) {
			if(filter_var($file, FILTER_VALIDATE_URL))
				return $this->saveFromUrl($file, $name);
			elseif(is_resource($file))
				return $this->saveFromResource($file, $name);
			elseif(file_exists($file))
				return $this->saveFromPath($file, $name);
			else
				throw new Exception(sprintf('Unable to save %s', $file));
		}

		/**
		 * Saves a file from a resource.  This is the "purest" variant of save.
		 *
		 * @param $resource
		 * @param string $mimeType
		 * @param null|string $name
		 * @return \Atrauzzi]Phperclip\Model\FileMeta
		 * @throws \Exception
		 */
		public function saveFromResource($resource, $mimeType, $name = null) {

			$fileMeta = FileMeta::updateOrCreate(
				[
					'name' => $name,
				],
				[
					'mime_type' => $mimeType,
					'disk' => $this->currentDisk,
				]
			);

			try {
				$this->saveResource($resource, $fileMeta);
			}
			catch(Exception $ex) {
				$fileMeta->forceDelete();
				throw $ex;
			}

			return $fileMeta;

		}

		/**
		 * Saves a file from a PHP-accessible path.
		 *
		 * @param string $path
		 * @param null|string $name
		 * @return \Atrauzzi\Phperclip\Model\FileMeta
		 * @throws \Exception
		 */
		public function saveFromPath($path, $name = null) {

			$resource = fopen($path, 'r');

			$finfoDb = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfoDb, $path);

			$fileMeta = $this->saveFromResource($resource, $mimeType, $name);

			fclose($resource);
			return $fileMeta;

		}

		/**
		 * Saves a file from a URL.
		 *
		 * @param string $uri
		 * @param null|string $name
		 * @return \Atrauzzi\Phperclip\Model\FileMeta
		 * @throws \Exception
		 */
		public function saveFromUrl($uri, $name = null) {

			stream_context_set_default(['http' => ['method' => 'HEAD']]);
			$head = get_headers($uri, true);
			$mimeType = (array)array_get($head, 'Content-Type');
			stream_context_set_default(['http' => ['method' => 'GET']]);
			$remoteResource = fopen($uri, 'r');

			$fileMeta = $this->saveFromResource($remoteResource, array_pop($mimeType), $name);

			fclose($remoteResource);
			return $fileMeta;

		}

		/**
		 * Delete a FileMeta's file (and all its derivatives if the original).
		 *
		 * @param FileMeta $fileMeta
		 * @param array $options
		 */
		public function delete(FileMeta $fileMeta, array $options = []) {

			$fileMeta->clippings()->delete();

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
		protected function saveResource($resource, FileMeta $fileMeta, array $options = []) {
			$this->getDisk()->getDriver()->putStream($this->filePath($fileMeta, $options), $resource);
		}

		/**
		 * Load an original image locally, trigger events and then save it as a derivative.
		 *
		 * @param \Atrauzzi\Phperclip\Model\FileMeta $fileMeta
		 * @param \Atrauzzi\Phperclip\Model\Clippable $clippable
		 * @param array $options
		 * @param \Atrauzzi\Phperclip\Processor\Contract|null $processor
		 * @return resource|null
		 * @throws \Exception
		 */
		protected function ensureDerivative(FileMeta $fileMeta, Clippable $clippable, array $options, $processor = null) {

			if(empty($options))
				throw new Exception('Unable to create derivative, no options provided.');

			// Take a local copy of the original.
			$originalResource = $this->getResource($fileMeta);
			$tempFile = tmpfile();
			stream_copy_to_stream($originalResource, $tempFile);
			rewind($tempFile);

			if(!$processor && $processor = array_get($options, 'processor'))
				$processor = app($processor);

			if($processor)
				$processor->process($tempFile, $fileMeta, $clippable);

			rewind($tempFile);
			$this->saveFromResource($tempFile, $fileMeta, $options);

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
			$options['id'] = intval($fileMeta->getKey());
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