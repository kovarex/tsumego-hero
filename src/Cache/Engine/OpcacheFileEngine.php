<?php

namespace App\Cache\Engine;

use Cake\Cache\CacheEngine;
use DateInterval;
use RuntimeException;

//A cache whose entries are PHP files returning the value, so reading one is an include the opcache answers
//from memory. The FileEngine reads each entry from the disk, and on the hosting's network disk every read
//took about 0.7 ms; a page reads the schema of each table it uses this way. Meant for values written
//rarely and read on every request, like the table schemas. The opcache looks at a rewritten or deleted
//file again after opcache.revalidate_freq seconds.
class OpcacheFileEngine extends CacheEngine
{
	protected array $_defaultConfig = [
		'duration' => 3600,
		'groups' => [],
		'path' => CACHE,
		'prefix' => 'cake_',
		'warnOnWriteFailures' => true,
	];

	public function init(array $config = []): bool
	{
		parent::init($config);
		$this->_config['path'] = rtrim($this->_config['path'], DS) . DS;
		if (!is_dir($this->_config['path']) && !@mkdir($this->_config['path'], 0777, true) && !is_dir($this->_config['path']))
			throw new RuntimeException('Cannot create the cache directory ' . $this->_config['path']);
		return true;
	}

	public function get(string $key, mixed $default = null): mixed
	{
		$file = $this->file($key);
		$entry = @include $file;
		if (!is_array($entry))
			return $default;
		[$expires, $serialized] = $entry;
		if ($expires < time())
		{
			$this->remove($file);
			return $default;
		}
		return unserialize($serialized);
	}

	public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
	{
		$file = $this->file($key);
		$code = '<?php return [' . (time() + $this->duration($ttl)) . ', ' . var_export(serialize($value), true) . '];' . "\n";
		//written aside and renamed, so a request never includes half a file
		$temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
		if (@file_put_contents($temporary, $code) === false || !@rename($temporary, $file))
		{
			@unlink($temporary);
			if ($this->_config['warnOnWriteFailures'])
				trigger_error('Could not write the cache file ' . $file, E_USER_WARNING);
			return false;
		}
		if (function_exists('opcache_invalidate'))
			opcache_invalidate($file, true);
		return true;
	}

	public function increment(string $key, int $offset = 1): int|false
	{
		$value = (int) $this->get($key, 0) + $offset;
		return $this->set($key, $value) ? $value : false;
	}

	public function decrement(string $key, int $offset = 1): int|false
	{
		return $this->increment($key, -$offset);
	}

	public function delete(string $key): bool
	{
		return $this->remove($this->file($key));
	}

	public function clear(): bool
	{
		foreach (glob($this->_config['path'] . $this->_config['prefix'] . '*.php') ?: [] as $file)
			$this->remove($file);
		return true;
	}

	public function clearGroup(string $group): bool
	{
		return $this->clear();
	}

	private function file(string $key): string
	{
		return $this->_config['path'] . $this->_key($key) . '.php';
	}

	private function remove(string $file): bool
	{
		if (function_exists('opcache_invalidate'))
			opcache_invalidate($file, true);
		return @unlink($file) || !is_file($file);
	}
}
