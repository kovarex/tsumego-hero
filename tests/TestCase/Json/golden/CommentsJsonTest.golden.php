<?php

return [
	'index' => [
		'status' => 200,
		'json' => [
			'issues' => [
				[
					'id' => '@int',
					'tsumego_issue_status_id' => 2,
					'created' => '@datetime',
					'user_id' => '@int',
					'user_display_name' => 'DN_kovarex',
					'user_avatar_url' => '/board/b04ad88c2cfa',
					'user_rating' => 100,
					'isAdmin' => true,
					'comments' => [
						[
							'id' => '@int',
							'text' => 'missing variation',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'user_rating' => 100,
							'isAdmin' => true,
							'created' => '@datetime',
							'position' => null,
						],
					],
				],
				[
					'id' => '@int',
					'tsumego_issue_status_id' => 1,
					'created' => '@datetime',
					'user_id' => '@int',
					'user_display_name' => 'DN_kovarex',
					'user_avatar_url' => '/board/b04ad88c2cfa',
					'user_rating' => 100,
					'isAdmin' => true,
					'comments' => [
						[
							'id' => '@int',
							'text' => 'wrong answer',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'user_rating' => 100,
							'isAdmin' => true,
							'created' => '@datetime',
							'position' => null,
						],
						[
							'id' => '@int',
							'text' => 'agreed, b2 works too',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'user_rating' => 100,
							'isAdmin' => true,
							'created' => '@datetime',
							'position' => '{"x":1,"y":1}',
						],
					],
				],
			],
			'standalone' => [
				[
					'id' => '@int',
					'text' => 'nice problem',
					'user_id' => '@int',
					'user_display_name' => 'DN_kovarex',
					'user_avatar_url' => '/board/b04ad88c2cfa',
					'user_rating' => 100,
					'isAdmin' => true,
					'created' => '@datetime',
					'position' => null,
				],
			],
			'counts' => [
				'total' => 3,
				'comments' => 1,
				'issues' => 2,
				'openIssues' => 1,
			],
		],
	],
	'add' => [
		'status' => 200,
		'json' => [
			'id' => '@int',
			'text' => 'a new comment',
			'user_id' => '@int',
			'user_display_name' => 'DN_kovarex',
			'user_avatar_url' => '/board/b04ad88c2cfa',
			'user_rating' => 100,
			'isAdmin' => true,
			'created' => '@datetime',
			'position' => '{"x":3,"y":4}',
		],
	],
	'delete' => [
		'status' => 200,
		'json' => [
			'success' => true,
		],
	],
	'issue create' => [
		'status' => 200,
		'json' => [
			'success' => true,
			'issue' => [
				'id' => '@int',
				'tsumego_issue_status_id' => 1,
				'created' => '@datetime',
				'user_id' => '@int',
				'user_display_name' => 'DN_kovarex',
				'user_avatar_url' => '/board/b04ad88c2cfa',
				'user_rating' => 100,
				'isAdmin' => true,
				'comments' => [
					[
						'id' => '@int',
						'text' => 'the ko is wrong',
						'user_id' => '@int',
						'user_display_name' => 'DN_kovarex',
						'user_avatar_url' => '/board/b04ad88c2cfa',
						'user_rating' => 100,
						'isAdmin' => true,
						'created' => '@datetime',
						'position' => '{"x":2,"y":2}',
					],
				],
			],
		],
	],
	'issues api' => [
		'status' => 200,
		'json' => [
			'issues' => [
				[
					'issue' => [
						'id' => '@int',
						'tsumego_issue_status_id' => 1,
						'tsumego_id' => '@int',
						'user_id' => '@int',
						'user_display_name' => 'DN_kovarex',
						'user_rating' => 100,
						'user_avatar_url' => '/board/b04ad88c2cfa',
						'created' => '@datetime',
					],
					'comments' => [
						[
							'id' => '@int',
							'text' => 'wrong answer',
							'created' => '@datetime',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_rating' => 100,
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'isAdmin' => true,
						],
						[
							'id' => '@int',
							'text' => 'agreed, b2 works too',
							'created' => '@datetime',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_rating' => 100,
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'isAdmin' => true,
						],
					],
					'author' => [
						'name' => 'DN_kovarex',
					],
					'tsumegoId' => '@int',
					'Set' => [
						'id' => '@int',
						'title' => 'Life and death',
					],
					'TsumegoNum' => 7,
					'issueNumber' => 1,
				],
			],
			'counts' => [
				'open' => 1,
				'closed' => 1,
			],
			'totalPages' => 1,
			'currentPage' => 1,
		],
	],
	'issues api of all statuses' => [
		'status' => 200,
		'json' => [
			'issues' => [
				[
					'issue' => [
						'id' => '@int',
						'tsumego_issue_status_id' => 2,
						'tsumego_id' => '@int',
						'user_id' => '@int',
						'user_display_name' => 'DN_kovarex',
						'user_rating' => 100,
						'user_avatar_url' => '/board/b04ad88c2cfa',
						'created' => '@datetime',
					],
					'comments' => [
						[
							'id' => '@int',
							'text' => 'missing variation',
							'created' => '@datetime',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_rating' => 100,
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'isAdmin' => true,
						],
					],
					'author' => [
						'name' => 'DN_kovarex',
					],
					'tsumegoId' => '@int',
					'Set' => [
						'id' => '@int',
						'title' => 'Life and death',
					],
					'TsumegoNum' => 7,
					'issueNumber' => 1,
				],
				[
					'issue' => [
						'id' => '@int',
						'tsumego_issue_status_id' => 1,
						'tsumego_id' => '@int',
						'user_id' => '@int',
						'user_display_name' => 'DN_kovarex',
						'user_rating' => 100,
						'user_avatar_url' => '/board/b04ad88c2cfa',
						'created' => '@datetime',
					],
					'comments' => [
						[
							'id' => '@int',
							'text' => 'wrong answer',
							'created' => '@datetime',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_rating' => 100,
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'isAdmin' => true,
						],
						[
							'id' => '@int',
							'text' => 'agreed, b2 works too',
							'created' => '@datetime',
							'user_id' => '@int',
							'user_display_name' => 'DN_kovarex',
							'user_rating' => 100,
							'user_avatar_url' => '/board/b04ad88c2cfa',
							'isAdmin' => true,
						],
					],
					'author' => [
						'name' => 'DN_kovarex',
					],
					'tsumegoId' => '@int',
					'Set' => [
						'id' => '@int',
						'title' => 'Life and death',
					],
					'TsumegoNum' => 7,
					'issueNumber' => 2,
				],
			],
			'counts' => [
				'open' => 1,
				'closed' => 1,
			],
			'totalPages' => 1,
			'currentPage' => 1,
		],
	],
	'issue close' => [
		'status' => 200,
		'json' => [
			'success' => true,
		],
	],
	'issue reopen' => [
		'status' => 200,
		'json' => [
			'success' => true,
		],
	],
	'move comment to its own' => [
		'status' => 200,
		'json' => [
			'success' => true,
		],
	],
	'move comment to a new issue' => [
		'status' => 200,
		'json' => [
			'success' => true,
			'issue' => [
				'id' => '@int',
				'status' => 'open',
				'created' => '@datetime',
				'user_id' => '@int',
				'user_display_name' => 'DN_kovarex',
				'user_avatar_url' => '/board/b04ad88c2cfa',
				'user_rating' => 100,
				'isAdmin' => true,
				'comments' => [
					[
						'id' => '@digits',
						'text' => 'nice problem',
						'user_id' => '@int',
						'user_display_name' => 'DN_kovarex',
						'user_avatar_url' => '/board/b04ad88c2cfa',
						'user_rating' => 100,
						'isAdmin' => true,
						'created' => '@datetime',
						'position' => null,
					],
				],
			],
			'comment_id' => '@digits',
		],
	],
	'move comment to an issue' => [
		'status' => 200,
		'json' => [
			'success' => true,
		],
	],
];
