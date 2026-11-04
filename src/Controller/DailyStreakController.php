<?php

namespace App\Controller;

use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\DailyStreak;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;

//The daily streak (DailyStreak) of the signed in player
class DailyStreakController extends AppController
{
	//The browser's time zone, sent by webroot/js/DailyStreak.js when it differs from the stored one.
	//The streak as it is in the new zone comes back, as the day may have turned there.
	#[HttpPost]
	public function timeZone(): Response
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$timeZone = (string) $this->input('time_zone');
		if (!DailyStreak::isValidTimeZone($timeZone))
			throw new BadRequestException('Unknown time zone');
		if ($timeZone !== (Auth::getUser()['time_zone'] ?? null))
			Auth::saveUserField('time_zone', $timeZone);
		return $this->respondJson(DailyStreak::ofUser(Auth::getUser())->toArray());
	}
}
