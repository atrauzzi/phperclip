<?php namespace Atrauzzi\Phperclip\Model {

	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Builder;


	class FileMeta extends Model {

		protected $table = 'phperclip_file_meta';

		protected $fillable = [
			'driver',
			'mime_type',
		];

		/**
		 * @return \Illuminate\Database\Eloquent\Relations\HasMany
		 */
		public function clippings() {
			return $this->hasMany('Atrauzzi\Phperclip\Model\Clipping');
		}

		/**
		 * Get the mimetype of the File
		 *
		 * @return mixed
		 */
		public function getMimeType() {
			return $this->mime_type;
		}

	}

}