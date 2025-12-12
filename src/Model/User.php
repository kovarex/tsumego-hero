<?php

class User extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'user';
		parent::__construct($id, $table, $ds);
	}

	public $validate = [
		'name' => [

			'notempty' => [
				'rule' => 'notBlank',
				'required' => true,
				'message' => 'Please Insert Name',
			],

			'minmax' => [
				'rule' => ['between', 3, 30],
				'message' => 'The length of the name should have at least 3 characters',
			],

			'checkUnique' => [
				'rule' => ['checkUnique', 'name'],
				'message' => 'Name already exists',
			],
		],

		'email' => [
			'notempty' => [
				'rule' => 'notBlank',
				'message' => 'Insert Email',
			],

			'email' => [
				'rule' => ['email', false],
				'message' => 'Enter a valid email-address',
			],

			'checkUnique' => [
				'rule' => ['checkUnique', 'email'],
				'message' => 'Email already exists',
			],
		],
	];

	public static function renderPremium($user): void
	{
		if ($user['premium'] == 2 || $user['premium'] == 1)
			echo '<img alt="Account Type" title="Account Type" src="/img/premium' . $user['premium'] . '.png" height="16px">';
	}

	public static function getHeroPowersCount($user): int
	{
		$heroPowers = 0;
		if($user['level'] >= HeroPowers::$SPRINT_MINIMUM_LEVEL)
			$heroPowers++;
		if($user['level'] >= HeroPowers::$INTUITION_MINIMUM_LEVEL)
			$heroPowers++;
		if($user['level'] >= HeroPowers::$REJUVENATION_MINIMUM_LEVEL)
			$heroPowers++;
		if($user['premium'] > 0 || $user['level'] >= HeroPowers::$REFINEMENT_MINIMUM_LEVEL)
			$heroPowers++;
		return $heroPowers;
	}

	public static function getHighestRating($user): float
	{
		$highestTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('first', [
			'conditions' => ['user_id' => $user['id']],
			'order' => 'user_rating DESC']);
		if ($highestTsumegoAttempt)
			return max($highestTsumegoAttempt['TsumegoAttempt']['user_rating'], $user['rating']);
		return $user['rating'];
	}

	public static function renderLink($id, $name = null, $externalID = null, $picture = null, $rating = null)
	{
		if (is_array($id))
			return self::renderLink($id['user_id'], $id['user_name'], $id['user_external_id'], $id['user_picture'], $id['user_rating']);

		$image = '';
		if (str_starts_with($name, 'g__') && $externalID != null)
		{
			$image = '<img class="google-profile-image" src="/img/google/' . $picture . '">';
			$name = substr($name, 3);
		}
		return '<a href="/users/view/' . $id . '">' . $image . h($name) . ' ' . Rating::getReadableRankFromRating($rating) . '</a>';
	}
}
