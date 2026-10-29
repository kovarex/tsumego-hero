<?php

return [
	'solved' => [
		'status' => 200,
		'json' => [
			'xp_gained' => 45,
			'new_rating' => 1509.4086883822947,
			'new_xp' => 35,
			'new_level' => 3,
			'new_damage' => 0,
			'status' => 'S',
			'potion_triggered' => false,
		],
	],
	'failed' => [
		'status' => 200,
		'json' => [
			'xp_gained' => 0,
			'new_rating' => 1492.5913206974851,
			'new_xp' => 50,
			'new_level' => 2,
			'new_damage' => 1,
			'status' => 'V',
			'potion_triggered' => false,
		],
	],
	'solved after a mistake' => [
		'status' => 200,
		'json' => [
			'xp_gained' => 45,
			'new_rating' => 1509.4086883822947,
			'new_xp' => 35,
			'new_level' => 3,
			'new_damage' => 0,
			'status' => 'F',
			'potion_triggered' => false,
		],
	],
	'solved unlocking an achievement' => [
		'status' => 200,
		'json' => [
			'xp_gained' => 45,
			'new_rating' => 1509.4086883822947,
			'new_xp' => 45,
			'new_level' => 12,
			'new_damage' => 0,
			'status' => 'S',
			'potion_triggered' => false,
			'achievement_updates' => [
				[
					'id' => 1,
					'name' => '1000 Problems',
					'description' => 'Solve 1000 problems.',
					'xp' => 1000,
					'image' => 'ac001',
					'color' => 'achievementColorBlack',
				],
			],
		],
	],
];
