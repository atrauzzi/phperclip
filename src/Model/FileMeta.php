<?php namespace Atrauzzi\Phperclip\Model {

	use Illuminate\Database\Eloquent\Model;
	//
	use Illuminate\Database\Eloquent\Builder;
	use Exception;

	class FileMeta extends Model {

		/** @var string */
		protected $table = 'phperclip_file_meta';

		/** @var array */
		protected $fillable = [
			'name',
			'disk',
			'mime_type',
		];

		/**
		 * @return \Illuminate\Database\Eloquent\Relations\HasMany
		 */
		public function clippings() {
			return $this->hasMany('Atrauzzi\Phperclip\Model\Clipping');
		}

		//
		//
		//

		/**
		 * Get the mimetype of the File
		 *
		 * @return string
		 */
		public function getMimeType() {
			return $this->mime_type;
		}

		/**
		 * Get the unique name of the File
		 *
		 * @return string
		 */
		public function getName() {
			return $this->name;
		}

		/**
		 * Gets the Laravel disk we know the file is stored on
		 *
		 * @return string
		 */
		public function getDisk() {
			return $this->disk;
		}

		//
		//
		//

		/**
		 * Filters FileMeta results by name
		 *
		 * @param \Illuminate\Database\Eloquent\Builder $builder
		 * @param string $name
		 * @return \Illuminate\Database\Eloquent\Builder
		 */
		public function scopeNamed(Builder $builder, $name) {
			return $builder->where('name', $name);
		}

		/**
		 * Only returns FileMeta instances that are clipped to a Clippable
		 *
		 * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $builder
		 * @param \Atrauzzi\Phperclip\Model\Clippable|string $clippableOrType
		 * @param null|int|string $clippableId
		 * @return \Illuminate\Database\Eloquent\Builder
		 * @throws \Exception
		 */
		public function scopeClippedTo(Builder $builder, $clippableOrType, $clippableId = null) {

			$builder = $builder->join('phperclip_clipping', 'phperclip_clipping.file_meta_id', '=', 'phperclip_file_meta.id');

			if($clippableOrType instanceof Clippable) {
				$clippableType = $clippableOrType->getMorphClass();
				$clippableId = $clippableOrType->getKey();
			}
			elseif($clippableOrType && $clippableId) {
				$clippableType = $clippableOrType;
			}
			elseif($clippableOrType) {
				throw new Exception(sprintf('Invalid clippable type %s/%s.', $clippableOrType, $clippableId));
			}

			if(!empty($clippableType) && !empty($clippableId)) $builder = $builder
				->where('phperclip_clipping.clippable_type', $clippableType)
				->where('phperclip_clipping.clippable_id', $clippableId)
			;

			return $builder;

		}

	}

}