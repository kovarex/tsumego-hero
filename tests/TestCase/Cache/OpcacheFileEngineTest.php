<?php

use App\Cache\Engine\OpcacheFileEngine;
use Cake\Database\Schema\TableSchema;

class OpcacheFileEngineTest extends AppTestCase
{
	private string $path;

	private function engine(array $config = []): OpcacheFileEngine
	{
		$this->path = TMP . 'tests' . DS . 'opcache_file_engine_' . bin2hex(random_bytes(4)) . DS;
		$engine = new OpcacheFileEngine();
		$engine->init($config + ['path' => $this->path, 'prefix' => 'test_', 'duration' => 3600]);
		return $engine;
	}

	protected function tearDown(): void
	{
		foreach (glob($this->path . '*') ?: [] as $file)
			unlink($file);
		if (is_dir($this->path))
			rmdir($this->path);
		parent::tearDown();
	}

	public function testAValueReadsBackAsItWasWritten(): void
	{
		$engine = $this->engine();
		$schema = new TableSchema('user', ['id' => ['type' => 'integer'], 'name' => ['type' => 'string', 'length' => 50]]);
		$values = ['schema' => $schema, 'text' => "it's \"quoted\"\0 with a null byte", 'list' => [1, 2.5, true, null], 'number' => 7];
		foreach ($values as $key => $value)
			$this->assertTrue($engine->set($key, $value));
		foreach ($values as $key => $value)
			$this->assertEquals($value, $engine->get($key));
		$this->assertSame(['id', 'name'], $engine->get('schema')->columns());
	}

	public function testAMissingEntryGivesTheDefault(): void
	{
		$this->assertSame('default', $this->engine()->get('missing', 'default'));
	}

	public function testAnExpiredEntryGivesTheDefaultAndIsRemoved(): void
	{
		$engine = $this->engine();
		$engine->set('old', 'value', -1);
		$this->assertNull($engine->get('old'));
		$this->assertFileDoesNotExist($this->path . 'test_old.php');
	}

	public function testAnEntryCanBeDeletedAndAllCleared(): void
	{
		$engine = $this->engine();
		$engine->set('first', 1);
		$engine->set('second', 2);
		$this->assertTrue($engine->delete('first'));
		$this->assertNull($engine->get('first'));
		$this->assertSame(2, $engine->get('second'));
		$this->assertTrue($engine->clear());
		$this->assertNull($engine->get('second'));
		$this->assertSame([], glob($this->path . '*'));
	}

	public function testCountersAddToTheStoredNumber(): void
	{
		$engine = $this->engine();
		$this->assertSame(1, $engine->increment('counter'));
		$this->assertSame(4, $engine->increment('counter', 3));
		$this->assertSame(2, $engine->decrement('counter', 2));
	}

	public function testTheEntryIsAPhpFileTheOpcacheCanKeep(): void
	{
		$engine = $this->engine();
		$engine->set('key', ['a' => 1]);
		$this->assertStringStartsWith('<?php return [', file_get_contents($this->path . 'test_key.php'));
	}
}
