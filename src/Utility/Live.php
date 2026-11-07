<?php

namespace App\Utility;

use Cake\Log\Log;

//Change stamps: what a page watches to learn that something it shows changed, without asking PHP.
//A stamp is a tiny static file, webroot/live/<channel>, which bump() rewrites after every change of
//what the channel stands for. Pages ask for it every second or two with a conditional request,
//which Apache answers from the file system with a 304 while it is the same (webroot/live/.htaccess:
//no PHP, no database), and only fetch the content itself once it changed (webroot/js/Live.js).
//The stamp holds no content, only a version and when it was written, so it can be public. A push
//hub (a daemon reading the same files and telling the pages that watch one) can replace the polling
//of the pages later without any change here or in the callers.
//Channels:
//  challenge-<id>  the table of the players of the challenge (ChallengesController::standings())
class Live
{
	//The directory of the stamps, created writable by deploy.sh
	public const string DIRECTORY = WWW_ROOT . 'live';

	//The channels are file names, served from webroot/live/
	private const string CHANNEL_PATTERN = '/^[a-z0-9-]{1,100}$/';

	public static function challengeChannel(int $challengeId): string
	{
		return 'challenge-' . $challengeId;
	}

	//Tells the pages watching the channel that it changed: rewrites its stamp at once, through a file
	//renamed over it, so a page never reads half of one. A stamp that can't be written is logged and
	//left alone: the change is in the database, and the pages see it on their next full refresh.
	public static function bump(string $channel): void
	{
		if (!preg_match(self::CHANNEL_PATTERN, $channel))
			throw new \InvalidArgumentException("'$channel' is not a channel.");
		$path = self::path($channel);
		$stamp = self::read($channel);
		$content = json_encode(['version' => ($stamp['version'] ?? 0) + 1, 'time' => microtime(true)]);
		if (!is_dir(self::DIRECTORY))
			@mkdir(self::DIRECTORY, 0777, true);
		$temporary = @tempnam(self::DIRECTORY, '.' . $channel . '-');
		if ($temporary === false || @file_put_contents($temporary, $content) === false
			|| !@chmod($temporary, 0644) || !@rename($temporary, $path))
		{
			if ($temporary !== false)
				@unlink($temporary);
			Log::warning('The live stamp ' . $path . ' could not be written.');
		}
	}

	//What the stamp of the channel says, null when it was never bumped
	/** @return array{version: int, time: float}|null */
	public static function read(string $channel): ?array
	{
		$content = @file_get_contents(self::path($channel));
		if ($content === false)
			return null;
		$stamp = json_decode($content, true);
		return is_array($stamp) && isset($stamp['version'], $stamp['time'])
			? ['version' => (int) $stamp['version'], 'time' => (float) $stamp['time']]
			: null;
	}

	//Days after its last bump that a stamp goes, see pruneStale()
	public const int STALE_AFTER_DAYS = 7;

	//Deletes the stamps no bump touched for STALE_AFTER_DAYS (the daily cron), so the directory doesn't keep one
	//file for every challenge there ever was. A page that still watches one gets the 204 of a stamp never
	//bumped, the hub an empty stamp, and the next bump writes it again. Only files named as channels go: the
	//.htaccess and anything else in the directory stay.
	//Returns how many stamps went
	public static function pruneStale(?int $now = null): int
	{
		$limit = ($now ?? time()) - self::STALE_AFTER_DAYS * 24 * 3600;
		$pruned = 0;
		foreach (@scandir(self::DIRECTORY) ?: [] as $name)
		{
			$path = self::DIRECTORY . DS . $name;
			if (!preg_match(self::CHANNEL_PATTERN, $name) || !is_file($path))
				continue;
			$modified = @filemtime($path);
			if ($modified !== false && $modified < $limit && @unlink($path))
				$pruned++;
		}
		return $pruned;
	}

	public static function path(string $channel): string
	{
		return self::DIRECTORY . DS . $channel;
	}
}
