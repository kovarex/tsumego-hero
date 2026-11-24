<?php

namespace App\Cache\Engine;

use App\Utility\RequestTiming;
use Cake\Cache\Engine\FileEngine;
use DateInterval;

//The FileEngine of Cake, with the time of its reads and writes counted into the request's (RequestTiming),
//and a read that finds nothing counted as a miss
class TimedFileEngine extends FileEngine
{
	public function get(string $key, mixed $default = null): mixed
	{
		$start = hrtime(true);
		$value = parent::get($key, $default);
		RequestTiming::cache(hrtime(true) - $start, $value === $default);
		return $value;
	}

	public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
	{
		$start = hrtime(true);
		try
		{
			return parent::set($key, $value, $ttl);
		}
		finally
		{
			RequestTiming::cache(hrtime(true) - $start);
		}
	}

	public function delete(string $key): bool
	{
		$start = hrtime(true);
		try
		{
			return parent::delete($key);
		}
		finally
		{
			RequestTiming::cache(hrtime(true) - $start);
		}
	}
}
