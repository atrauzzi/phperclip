<?php

return [
	// Sample Image Filter

	// Example usage:
	//
	// Phperclip::saveFromFile($file, ['filters' => Config::get('phperclip::filters.shrink')]);
	//
	'shrink' => [

		'Atrauzzi\Phperclip\Processor\Image\FixRotation',
		[
			'Atrauzzi\Phperclip\Processor\Image\Resize',
			[
				'width' => 100,
				'height' => 100,
				'preserve_ratio' => true,
			]
		]
	]
];