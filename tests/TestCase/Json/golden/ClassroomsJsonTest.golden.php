<?php

return [
	'collections' => [
		'status' => 200,
		'json' => [
			'own' => [
				[
					'id' => '@int',
					'title' => 'Homework',
					'color' => '',
					'problemCount' => 2,
					'difficulty' => '11k',
				],
			],
			'site' => [
				[
					'id' => '@int',
					'title' => 'Site basics',
					'color' => '',
					'problemCount' => 1,
					'difficulty' => '11k',
				],
			],
		],
	],
	'collection problems' => [
		'status' => 200,
		'json' => [
			'collection' => [
				'id' => '@int',
				'title' => 'Homework',
			],
			'problems' => [
				[
					'setConnectionId' => '@int',
					'tsumegoId' => '@int',
					'number' => 1,
					'setId' => '@int',
					'setTitle' => 'Homework',
					'preview' => [
						'black' => 'cc',
						'white' => 'dd',
						'xMax' => 3,
						'yMax' => 3,
						'boardSize' => 19,
					],
				],
				[
					'setConnectionId' => '@int',
					'tsumegoId' => '@int',
					'number' => 2,
					'setId' => '@int',
					'setTitle' => 'Homework',
					'preview' => [
						'black' => 'ee',
						'white' => '',
						'xMax' => 4,
						'yMax' => 4,
						'boardSize' => 9,
					],
				],
			],
		],
	],
	'save assignment' => [
		'status' => 200,
		'json' => [
			'problemCount' => 1,
			'published' => null,
			'classUrl' => '@string',
		],
	],
	'save and publish assignment' => [
		'status' => 200,
		'json' => [
			'problemCount' => 1,
			'published' => '@datetime',
			'classUrl' => '@string',
		],
	],
	'save assignment without a due date' => [
		'status' => 422,
		'json' => [
			'error' => 'The due date is not a date.',
		],
	],
	'progress of the teacher' => [
		'status' => 200,
		'json' => [
			'problems' => [
				[
					'setConnectionId' => '@int',
					'number' => 1,
					'setId' => '@int',
					'setTitle' => 'Homework',
				],
				[
					'setConnectionId' => '@int',
					'number' => 2,
					'setId' => '@int',
					'setTitle' => 'Homework',
				],
			],
			'pupils' => [
				[
					'userId' => '@int',
					'name' => 'DN_pupil',
					'avatarUrl' => '/board/0ea1e5bf1c39',
					'rating' => 100,
					'solvedCount' => 1,
					'cells' => [
						[
							'state' => 'solved',
							'solvedAt' => '@datetime',
							'misplays' => 1,
						],
						[
							'state' => 'tried',
							'misplays' => 2,
						],
					],
				],
				[
					'userId' => '@int',
					'name' => 'DN_pupil2',
					'avatarUrl' => '/board/fcfb98f71969',
					'rating' => 100,
					'solvedCount' => 0,
					'cells' => [
						null,
						null,
					],
				],
			],
		],
	],
	'progress of a pupil' => [
		'status' => 200,
		'json' => [
			'problems' => [
				[
					'setConnectionId' => '@int',
					'number' => 1,
					'setId' => '@int',
					'setTitle' => 'Homework',
				],
				[
					'setConnectionId' => '@int',
					'number' => 2,
					'setId' => '@int',
					'setTitle' => 'Homework',
				],
			],
			'pupils' => [
				[
					'userId' => '@int',
					'name' => 'DN_pupil',
					'avatarUrl' => '/board/0ea1e5bf1c39',
					'rating' => 100,
					'solvedCount' => 1,
					'cells' => [
						[
							'state' => 'solved',
							'solvedAt' => '@datetime',
							'misplays' => 1,
						],
						[
							'state' => 'tried',
							'misplays' => 2,
						],
					],
				],
			],
		],
	],
];
