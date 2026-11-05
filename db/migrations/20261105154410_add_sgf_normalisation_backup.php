<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//bin/cake normalise_sgfs rewrites every stored SGF in place to how the site keeps them (turned to the top-left,
//the correct variations first, App\Command\NormaliseSgfsCommand). What it changes is kept here first: the row as it
//was with the turn applied to it, and the comments it moved, so a run can be checked and undone.
final class AddSgfNormalisationBackup extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE sgf_before_normalisation (
				sgf_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
				sgf MEDIUMTEXT NOT NULL,
				first_move_color CHAR(1) NULL,
				correct_moves VARCHAR(128) NULL,
				transformation_from_source VARCHAR(100) NULL,
				transformation VARCHAR(100) NOT NULL COMMENT 'JSON of App\\\\Utility\\\\BoardTransformation from the coordinates of sgf to those of the rewritten row',
				normalised DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT sgf_before_normalisation_sgf_id FOREIGN KEY (sgf_id) REFERENCES sgf (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT='Rows of sgf as they were before bin/cake normalise_sgfs rewrote them'");
		$this->execute("CREATE TABLE tsumego_comment_before_normalisation (
				tsumego_comment_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
				message VARCHAR(2048) NOT NULL,
				position VARCHAR(300) NULL,
				CONSTRAINT tsumego_comment_before_normalisation_tsumego_comment_id FOREIGN KEY (tsumego_comment_id) REFERENCES tsumego_comment (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT='Comments as they were before bin/cake normalise_sgfs moved them with their problem'");
	}

	public function down(): void
	{
		$this->execute('DROP TABLE tsumego_comment_before_normalisation');
		$this->execute('DROP TABLE sgf_before_normalisation');
	}
}
