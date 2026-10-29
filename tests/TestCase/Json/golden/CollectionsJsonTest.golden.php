<?php

return [
	'tag problems' => [
		'status' => 200,
		'json' => [
			[
				'url' => '@string',
				'name' => 'test set 1',
				'approved' => true,
			],
			[
				'url' => '@string',
				'name' => 'test set 2',
				'approved' => false,
			],
		],
	],
	'tag add' => [
		'status' => 200,
		'json' => [
			'success' => true,
		],
	],
	'tag remove' => [
		'status' => 200,
		'json' => [
			'success' => true,
		],
	],
	'sets add tsumego' => [
		'status' => 200,
		'json' => [
			'contains' => true,
			'set_id' => '@digits',
			'title' => 'My Set',
		],
	],
	'sets add tsumego to favorites' => [
		'status' => 200,
		'json' => [
			'contains' => true,
			'set_id' => '@int',
			'title' => 'Favorites',
		],
	],
	'sets remove tsumego' => [
		'status' => 200,
		'json' => [
			'contains' => false,
		],
	],
	'sgf info' => [
		'status' => 200,
		'json' => [
			'description' => 'Added D16
because',
			'authorLink' => '@string',
		],
	],
	'schedule preview' => [
		'status' => 200,
		'json' => [
			[
				'tsumego_id' => '@int',
				'sc_id' => '@int',
				'num' => 5,
				'preview' => [
					'black' => 'cc',
					'white' => 'dd',
					'xMax' => 3,
					'yMax' => 3,
					'boardSize' => 19,
				],
				'num_collision' => true,
				'date' => '2030-01-01',
			],
			[
				'tsumego_id' => '@int',
				'sc_id' => '@int',
				'num' => 6,
				'preview' => [
					'black' => 'ee',
					'white' => '',
					'xMax' => 4,
					'yMax' => 4,
					'boardSize' => 9,
				],
				'num_collision' => false,
				'date' => '2030-01-02',
			],
		],
	],
	'admin review of an sgf change' => [
		'status' => 200,
		'json' => [
			'reviewed' => true,
		],
	],
	'admin review taken back' => [
		'status' => 200,
		'json' => [
			'reviewed' => false,
		],
	],
];
