<?php namespace Atrauzzi\Phperclip;

use Atrauzzi\Phperclip\Model\FileMeta;

class FileNameGenerator implements Contracts\FileNameGenerator {

	protected $mimeResolver;

	public function __construct(MimeResolver $mimeResolver) {
		$this->mimeResolver = $mimeResolver;
	}



}