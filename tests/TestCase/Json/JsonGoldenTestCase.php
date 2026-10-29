<?php

//The JSON the endpoints answer with, the front end (React, besogo, the scripts of the pages) reads
//it. Every case of a test makes one request from a fixed ContextPreparator state and checks the
//whole answer against plain expected data in golden/<test class>.golden.php: every field, in its order,
//its type and its nesting, and its value where the value is the same on every run.
//The expected data and the requests stay as they are when the controllers move to Cake 5: the
//requests go through IntegrationTestTrait's get() and post(), which Cake 5 has too, and
//responseJson() is the only part that reads the response.
//
//The expected data is the JSON as PHP: an object is an array with string keys, a list an array
//with keys 0, 1, 2..., and '@{}' an empty object. Where a value changes from run to run, a tag
//stands for its type: '@int', '@float', '@string', '@bool', '@digits' (an integer sent as a string),
//'@datetime' (ISO 8601), a '?' in front (as '@?int') to allow null as well.
//Run with JSON_GOLDEN_RECORD=1 to write the answers into golden/<test class>.golden.php; the ids and
//dates in them become tags, the rest stays as it was answered, to be read before it is committed.
abstract class JsonGoldenTestCase extends IntegrationTestCase
{
	//The answers recorded by this run, by case, written at the end of the class
	private static array $recorded = [];

	//The last response matches the expected data of the case $name.
	protected function assertJsonGolden(string $name): void
	{
		$actual = ['status' => $this->responseStatus(), 'json' => $this->responseJson()];
		if (getenv('JSON_GOLDEN_RECORD'))
		{
			self::$recorded[static::class][$name] = self::recordable($actual);
			$this->addToAssertionCount(1);
			return;
		}

		$expected = self::golden()[$name] ?? null;
		$this->assertNotNull($expected, 'golden/' . static::class . '.golden.php has no case ' . $name);
		$this->assertSame($expected['status'], $actual['status'], $name . ': the status');
		$this->assertJsonMatches($expected['json'], $actual['json'], $name);
	}

	public static function tearDownAfterClass(): void
	{
		parent::tearDownAfterClass();
		if (!getenv('JSON_GOLDEN_RECORD') || empty(self::$recorded[static::class]))
			return;
		$cases = self::$recorded[static::class] + (is_file(self::goldenFile()) ? self::golden() : []);
		file_put_contents(self::goldenFile(), "<?php\n\nreturn " . self::export($cases, 0) . ";\n");
	}

	//The adapter: the only reads of the response.

	//The body of the last response decoded, objects as stdClass and lists as arrays.
	protected function responseJson(): mixed
	{
		$this->assertJson($this->view, 'The response is no JSON: ' . substr($this->view, 0, 300));
		return json_decode($this->view, false, 512, JSON_THROW_ON_ERROR);
	}

	protected function responseStatus(): int
	{
		return $this->_response->getStatusCode();
	}

	//The matching.

	private function assertJsonMatches(mixed $expected, mixed $actual, string $path): void
	{
		if (is_string($expected) && str_starts_with($expected, '@'))
		{
			$this->assertTrue(self::matchesTag($expected, $actual), $path . ' is ' . json_encode($actual) . ', expected ' . $expected);
			return;
		}
		if ($actual instanceof stdClass)
		{
			$actual = get_object_vars($actual);
			$this->assertTrue(is_array($expected) && ($expected === [] ? false : !array_is_list($expected)),
				$path . ' is an object, expected ' . json_encode($expected));
			$this->assertSame(array_keys($expected), array_keys($actual), $path . ' has other fields');
			foreach ($expected as $key => $value)
				$this->assertJsonMatches($value, $actual[$key], $path . '.' . $key);
			return;
		}
		if (is_array($actual))
		{
			$this->assertTrue(is_array($expected) && array_is_list($expected), $path . ' is a list, expected ' . json_encode($expected));
			$this->assertCount(count($expected), $actual, $path . ' has another length');
			foreach ($expected as $index => $value)
				$this->assertJsonMatches($value, $actual[$index], $path . '[' . $index . ']');
			return;
		}
		$this->assertSame($expected, $actual, $path);
	}

	private static function matchesTag(string $tag, mixed $actual): bool
	{
		if ($tag === '@{}')
			return $actual instanceof stdClass && get_object_vars($actual) === [];
		if (str_starts_with($tag, '@?'))
		{
			if ($actual === null)
				return true;
			$tag = '@' . substr($tag, 2);
		}
		return match ($tag)
		{
			'@int' => is_int($actual),
			'@float' => is_float($actual),
			'@string' => is_string($actual),
			'@bool' => is_bool($actual),
			'@digits' => is_string($actual) && ctype_digit($actual),
			'@datetime' => is_string($actual) && (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/', $actual),
			default => throw new InvalidArgumentException('Unknown tag ' . $tag),
		};
	}

	//The recording.

	private static function goldenFile(): string
	{
		return __DIR__ . '/golden/' . static::class . '.golden.php';
	}

	private static function golden(): array
	{
		return require self::goldenFile();
	}

	//The answer as expected data, with the ids and dates turned into their tags.
	private static function recordable(mixed $value, ?string $key = null): mixed
	{
		if ($value instanceof stdClass)
		{
			$fields = get_object_vars($value);
			if ($fields === [])
				return '@{}';
			$object = [];
			foreach ($fields as $field => $fieldValue)
				$object[$field] = self::recordable($fieldValue, (string) $field);
			return $object;
		}
		if (is_array($value))
			return array_map(fn($item) => self::recordable($item, $key), $value);
		if ($key !== null && self::isIdKey($key) && is_int($value))
			return '@int';
		if ($key !== null && self::isIdKey($key) && is_string($value) && ctype_digit($value))
			return '@digits';
		if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/', $value))
			return '@datetime';
		return $value;
	}

	//An id given out by the database, unlike the ids of the fixed rows (a status, a type)
	private static function isIdKey(string $key): bool
	{
		if (str_ends_with($key, 'status_id') || str_ends_with($key, 'type_id'))
			return false;
		return $key === 'id' || str_ends_with($key, '_id') || str_ends_with($key, 'Id');
	}

	private static function export(mixed $value, int $depth): string
	{
		if ($value === null)
			return 'null';
		if (!is_array($value))
			return var_export($value, true);
		if ($value === [])
			return '[]';
		$indent = str_repeat("\t", $depth + 1);
		$lines = [];
		foreach ($value as $key => $item)
			$lines[] = $indent . (array_is_list($value) ? '' : var_export($key, true) . ' => ') . self::export($item, $depth + 1) . ',';
		return "[\n" . implode("\n", $lines) . "\n" . str_repeat("\t", $depth) . ']';
	}
}
