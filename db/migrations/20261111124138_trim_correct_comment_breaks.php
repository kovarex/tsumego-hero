<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A comment marked correct whose text starts on the next line (C[+\nThe marked stones…], as some imported problems have
//it) showed its text under Correct! instead of next to it: besogo takes the + off and keeps the line break. The space
//after the + goes, in every stored version; created is kept, as the row isn't a new version.
final class TrimCorrectCommentBreaks extends AbstractMigration
{
	public const string PATTERN = '/(?<![A-Z])C\[\+\s+/';

	public function up(): void
	{
		foreach ($this->fetchAll("SELECT id, sgf FROM sgf WHERE sgf REGEXP 'C\\\\[\\\\+[[:space:]]'") as $row)
		{
			$fixed = preg_replace(self::PATTERN, 'C[+', (string) $row['sgf']);
			if ($fixed !== $row['sgf'])
				$this->execute('UPDATE sgf SET sgf = ' . $this->getAdapter()->getConnection()->quote($fixed) . ', created = created WHERE id = ' . (int) $row['id']);
		}
	}

	public function down(): void {}
}
