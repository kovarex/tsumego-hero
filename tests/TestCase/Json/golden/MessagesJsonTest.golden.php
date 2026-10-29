<?php

return [
	'conversations' => [
		'status' => 200,
		'json' => [
			'conversations' => [
				[
					'partner' => [
						'id' => '@int',
						'displayName' => 'Bob',
						'avatarUrl' => '/board/cd9fb1e148cc',
						'rating' => 100,
					],
					'lastMessage' => [
						'id' => '@int',
						'senderUserId' => '@int',
						'text' => 'A question about problem 5',
						'created' => '@datetime',
						'readAt' => null,
					],
					'unreadCount' => 1,
				],
				[
					'partner' => [
						'id' => '@int',
						'displayName' => 'Anna Seigen',
						'avatarUrl' => '/board/f60997103371',
						'rating' => 100,
					],
					'lastMessage' => [
						'id' => '@int',
						'senderUserId' => '@int',
						'text' => 'Hi, how are you?',
						'created' => '@datetime',
						'readAt' => null,
					],
					'unreadCount' => 1,
				],
			],
		],
	],
	'conversation' => [
		'status' => 200,
		'json' => [
			'partner' => [
				'id' => '@int',
				'displayName' => 'Anna Seigen',
				'avatarUrl' => '/board/f60997103371',
				'rating' => 100,
			],
			'messages' => [
				[
					'id' => '@int',
					'senderUserId' => '@int',
					'text' => 'Hello Anna',
					'created' => '@datetime',
					'readAt' => null,
				],
				[
					'id' => '@int',
					'senderUserId' => '@int',
					'text' => 'Hi, how are you?',
					'created' => '@datetime',
					'readAt' => null,
				],
			],
			'hasOlder' => false,
		],
	],
	'conversation before' => [
		'status' => 200,
		'json' => [
			'partner' => [
				'id' => '@int',
				'displayName' => 'Anna Seigen',
				'avatarUrl' => '/board/f60997103371',
				'rating' => 100,
			],
			'messages' => [
				[
					'id' => '@int',
					'senderUserId' => '@int',
					'text' => 'Hello Anna',
					'created' => '@datetime',
					'readAt' => null,
				],
			],
			'hasOlder' => false,
		],
	],
	'send' => [
		'status' => 200,
		'json' => [
			'message' => [
				'id' => '@int',
				'senderUserId' => '@int',
				'text' => 'It is solved by the hane',
				'created' => '@datetime',
				'readAt' => null,
			],
		],
	],
	'read' => [
		'status' => 200,
		'json' => [
			'unreadCount' => 1,
		],
	],
	'recipients' => [
		'status' => 200,
		'json' => [
			'players' => [
				[
					'id' => '@int',
					'displayName' => 'Anna Seigen',
					'avatarUrl' => '/board/f60997103371',
					'rating' => 100,
				],
			],
		],
	],
];
