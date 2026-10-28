<?php

declare(strict_types=1);

use App\Utility\TsumegoButton;
use Phinx\Migration\AbstractMigration;

//The problem buttons show a preview of the starting position on hover, which was computed by parsing
//the whole SGF of every shown button on every request. The preview depends on the SGF alone, and the
//SGF of a version never changes, so it is now computed once when the version is saved and kept in
//sgf.preview. This fills it for every version there is, in batches by id, and then makes it required.
final class AddSgfPreview extends AbstractMigration
{
	private const BATCH = 1000;

	public function up(): void
	{
		$this->execute("ALTER TABLE sgf ADD COLUMN IF NOT EXISTS preview VARCHAR(1000) NULL
			COMMENT 'JSON of the starting stones for the board preview of the problem buttons, from TsumegoButton::sgfToPreviewJson(), empty when the SGF has none. Filled on every save.'
			AFTER correct_moves");

		$connection = $this->getAdapter()->getConnection();
		$lastID = 0;
		while (true)
		{
			$rows = $this->fetchAll('SELECT id, sgf FROM sgf WHERE id > ' . $lastID . ' AND preview IS NULL ORDER BY id LIMIT ' . self::BATCH);
			if (empty($rows))
				break;
			$cases = '';
			$ids = [];
			foreach ($rows as $row)
			{
				$id = (int) $row['id'];
				$cases .= ' WHEN ' . $id . ' THEN ' . $connection->quote(TsumegoButton::sgfToPreviewJson((string) $row['sgf']));
				$ids[] = $id;
				$lastID = $id;
			}
			$this->execute('UPDATE sgf SET preview = CASE id' . $cases . ' END WHERE id IN (' . implode(',', $ids) . ')');
		}

		$this->execute("ALTER TABLE sgf MODIFY preview VARCHAR(1000) NOT NULL
			COMMENT 'JSON of the starting stones for the board preview of the problem buttons, from TsumegoButton::sgfToPreviewJson(), empty when the SGF has none. Filled on every save.'");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE sgf DROP COLUMN IF EXISTS preview');
	}
}
