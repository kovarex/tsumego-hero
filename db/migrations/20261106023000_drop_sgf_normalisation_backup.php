<?php

declare(strict_types=1);

use App\Model\Table\SgfTable;
use App\Utility\BoardTransformation;
use App\Utility\TsumegoButton;
use Phinx\Migration\AbstractMigration;

//bin/cake normalise_sgfs no longer keeps what it rewrites aside: the database is backed up before a run. What the
//first run on prod wrote stays, but for the only rows it wrote wrong: sgf 19415 and 19416 (problem 33023), whose setup
//after a move a bug of besogo's turn wrote as a white stone where the original empties the point (AE written as AW).
//They are put back from sgf_before_normalisation as they were, so the fixed normalise_sgfs rewrites them: text,
//preview and values, the turn taken out of the transformations from source it was put into (their own and those of
//the rows made from them), and, for a version shown, the comments of its problem. This is the only use of the backup;
//then both backup tables are dropped. A row not in the backup is left as it is and named.
final class DropSgfNormalisationBackup extends AbstractMigration
{
	private const WRITTEN_WRONG = [19415, 19416];

	public function up(): void
	{
		if ($this->hasTable('sgf_before_normalisation'))
			foreach (self::WRITTEN_WRONG as $id)
				$this->putBack($id);
		$this->execute('DROP TABLE IF EXISTS tsumego_comment_before_normalisation');
		$this->execute('DROP TABLE IF EXISTS sgf_before_normalisation');
	}

	private function putBack(int $id): void
	{
		$backup = $this->fetchRow("SELECT * FROM sgf_before_normalisation WHERE sgf_id = $id");
		if (!$backup)
		{
			$this->getOutput()->writeln("sgf $id is not in sgf_before_normalisation, left as it is");
			return;
		}
		$connection = $this->getAdapter()->getConnection();
		$row = $this->fetchRow("SELECT tsumego_id, transformation_from_source FROM sgf WHERE id = $id");
		$turn = BoardTransformation::fromJson($backup['transformation']);
		$connection->prepare('UPDATE sgf SET sgf = ?, preview = ?, first_move_color = ?, correct_moves = ? WHERE id = ?')
			->execute([$backup['sgf'], TsumegoButton::sgfToPreviewJson((string) $backup['sgf']), $backup['first_move_color'], $backup['correct_moves'], $id]);
		// the turn comes out of the transformations it was put into: its own ended in it, those from it began with it undone
		if (!empty($row['transformation_from_source']))
			$connection->prepare('UPDATE sgf SET transformation_from_source = ? WHERE id = ?')
				->execute([BoardTransformation::fromJson($row['transformation_from_source'])->then($turn->inverse())->toJson(), $id]);
		foreach ($this->fetchAll("SELECT id, transformation_from_source FROM sgf WHERE source_sgf_id = $id AND transformation_from_source IS NOT NULL") as $dependent)
			$connection->prepare('UPDATE sgf SET transformation_from_source = ? WHERE id = ?')
				->execute([$turn->then(BoardTransformation::fromJson($dependent['transformation_from_source']))->toJson(), $dependent['id']]);
		$tsumegoID = (int) $row['tsumego_id'];
		$current = $this->fetchRow('SELECT MAX(id) AS id FROM sgf WHERE tsumego_id = ' . $tsumegoID . ' AND sgf_status_id = ' . SgfTable::STATUS_ACCEPTED);
		if ((int) ($current['id'] ?? 0) === $id)
			$this->execute("UPDATE tsumego_comment comment JOIN tsumego_comment_before_normalisation backup ON backup.tsumego_comment_id = comment.id
				SET comment.message = backup.message, comment.position = backup.position WHERE comment.tsumego_id = $tsumegoID");
		$this->getOutput()->writeln("sgf $id put back as it was before normalise_sgfs, for it to rewrite it");
	}

	public function down(): void
	{
		$this->execute("CREATE TABLE sgf_before_normalisation (
				sgf_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
				sgf MEDIUMTEXT NOT NULL,
				first_move_color CHAR(1) NULL,
				correct_moves VARCHAR(128) NULL,
				transformation_from_source VARCHAR(100) NULL,
				transformation VARCHAR(100) NOT NULL,
				normalised DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT sgf_before_normalisation_sgf_id FOREIGN KEY (sgf_id) REFERENCES sgf (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->execute("CREATE TABLE tsumego_comment_before_normalisation (
				tsumego_comment_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
				message VARCHAR(2048) NOT NULL,
				position VARCHAR(300) NULL,
				CONSTRAINT tsumego_comment_before_normalisation_tsumego_comment_id FOREIGN KEY (tsumego_comment_id) REFERENCES tsumego_comment (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
}
