<?php

use Phinx\Migration\AbstractMigration;

//The board editor normalises, mirrors and may rotate the problem before it is
//saved as a new SGF version, so the new version can be oriented differently
//from the one it was edited from. The editor now sends that transformation
//along; when the version becomes the current one, the comments of the problem
//(stored in SGF coordinates) are transformed with it.
class AddSgfSourceTransformation extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `sgf`
			ADD `source_sgf_id` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT 'The SGF version this one was edited from in the board editor',
			ADD `transformation_from_source` VARCHAR(100) NULL DEFAULT NULL COMMENT 'JSON of App\\\\Utility\\\\BoardTransformation from the coordinates of source_sgf_id to the coordinates of this version',
			ADD CONSTRAINT `sgf_source_sgf_id` FOREIGN KEY (`source_sgf_id`) REFERENCES `sgf` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `sgf` DROP FOREIGN KEY `sgf_source_sgf_id`');
		$this->execute('ALTER TABLE `sgf` DROP `source_sgf_id`, DROP `transformation_from_source`');
	}
}
