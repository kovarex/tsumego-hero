<?php

use App\Utility\SetImage;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;
use Symfony\Component\Console\Output\BufferedOutput;

require_once ROOT . '/db/migrations/20261124120000_set_covers_in_database.php';

//The cover of every set is read from the file set.image names, an image of the site or an upload under
//img/sets/, and stored in set_cover; a set whose file is missing gets none and is printed, a set without
//an image stays without, and running it again changes nothing
class SetCoversInDatabaseMigrationTest extends AppTestCase
{
	public function testTheCoversOfTheFilesAreStoredInTheDatabase(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'legacy cover', 'num' => 1]]],
			['sets' => [['name' => 'uploaded cover', 'num' => 1]]],
			['sets' => [['name' => 'missing cover', 'num' => 1]]],
			['sets' => [['name' => 'no cover', 'num' => 1]]]]]);
		[$legacy, $uploaded, $missing, $none] = array_map(fn($tsumego) => (int) $tsumego['sets'][0]['id'], $context->tsumegos);

		$uploadFolder = SetCoversInDatabase::imageDirectory() . 'sets' . DS . $uploaded;
		@mkdir($uploadFolder, 0775, true);
		$uploadedFile = $uploadFolder . DS . 'migration-test.webp';
		$image = imagecreatetruecolor(30, 50);
		imagewebp($image, $uploadedFile);
		imagedestroy($image);

		foreach ([$legacy => 'b1.png', $uploaded => 'sets/' . $uploaded . '/migration-test.webp', $missing => 'no-such-cover.png', $none => ''] as $setID => $name)
			Util::execute('UPDATE `set` SET image = ?, cover_hash = NULL WHERE id = ?', [$name, $setID]);
		Util::execute('DELETE FROM set_cover');

		try
		{
			$output = new BufferedOutput();
			$migration = self::migration();
			$migration->setOutput($output);
			$migration->up();
		}
		finally
		{
			@unlink($uploadedFile);
			@rmdir($uploadFolder);
		}

		$b1 = getimagesize(WWW_ROOT . 'img' . DS . 'b1.png');
		$this->assertSame(['width' => $b1[0], 'height' => $b1[1]], SetImage::size($legacy));
		$this->assertSame(['width' => 30, 'height' => 50], SetImage::size($uploaded));
		$stored = self::hashes();
		foreach ([$legacy, $uploaded] as $setID)
		{
			$picture = SetImage::picture($setID, (string) $stored[$setID]);
			$this->assertNotNull($picture, 'the cover is served under its hash');
			$this->assertSame('WEBP', substr($picture, 8, 4));
		}
		$this->assertNull($stored[$missing]);
		$this->assertNull($stored[$none]);
		$this->assertStringContainsString('Set ' . $missing . ': the cover no-such-cover.png is missing', $output->fetch());
		$this->assertSame('b1.png', Util::query('SELECT image FROM `set` WHERE id = ?', [$legacy])[0]['image'], 'set.image stays');

		$migration = self::migration();
		$migration->setOutput(new BufferedOutput());
		$migration->up();
		$this->assertSame($stored, self::hashes(), 'running it again changes nothing');
		$this->assertCount(2, Util::query('SELECT set_id FROM set_cover'));
	}

	/** @return array<int, ?string> */
	private static function hashes(): array
	{
		$hashes = [];
		foreach (Util::query('SELECT id, cover_hash FROM `set` ORDER BY id') as $row)
			$hashes[(int) $row['id']] = $row['cover_hash'];
		return $hashes;
	}

	private static function migration(): SetCoversInDatabase
	{
		$config = require ROOT . '/phinx.php';
		$migration = new SetCoversInDatabase('test', 20261124120000);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
