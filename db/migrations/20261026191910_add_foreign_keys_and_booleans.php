<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The foreign keys and NOT NULLs the tags, problem variants and the schedule were missing, and the
 * flags kept in int(11) made tinyint(1):
 *
 * - tag.user_id points to its user (SET NULL), tag.id and tag_connection.tag_id are unsigned like the
 *   other ids, a tag connection always has a tag,
 * - a problem has at most one variant (Play reads one), which goes with the problem,
 * - a schedule entry always has its problem and set,
 * - set.public, tag.approved, tag.hint, tag_connection.approved and schedule.published are 0 or 1.
 *   A set with public = -1 was deleted by the old code (and could be taken back by /sets/sandbox?restore=),
 *   it goes to the trash now.
 *
 * Rows the keys would refuse go first, the way the keys would treat them: a tag of a user who is gone
 * loses the user, a variant of a problem that is gone goes, and so do a second variant of a problem
 * (Play shows the first one), a tag connection without a tag and a schedule entry without its problem or set.
 * A tag connection without a decision (approved NULL, which no code writes) waits for one.
 *
 * down() brings back the old types; the rows that went and the sets moved to the trash stay as they are.
 */
final class AddForeignKeysAndBooleans extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('UPDATE tag SET approved = 1 WHERE approved NOT IN (0, 1)');
		$this->execute('UPDATE tag SET hint = 1 WHERE hint NOT IN (0, 1)');
		$this->execute('UPDATE tag LEFT JOIN user ON user.id = tag.user_id SET tag.user_id = NULL WHERE tag.user_id IS NOT NULL AND user.id IS NULL');
		$this->execute('ALTER TABLE tag_connection DROP FOREIGN KEY tag_connection_tag_id');
		$this->execute('ALTER TABLE tag
			MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT,
			MODIFY user_id int(10) unsigned DEFAULT NULL,
			MODIFY approved tinyint(1) NOT NULL DEFAULT 1,
			MODIFY hint tinyint(1) NOT NULL DEFAULT 0,
			ADD CONSTRAINT tag_user_id FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL ON UPDATE CASCADE');

		$this->execute('DELETE tag_connection FROM tag_connection LEFT JOIN tag ON tag.id = tag_connection.tag_id WHERE tag.id IS NULL');
		$this->execute('UPDATE tag_connection SET approved = 0 WHERE approved IS NULL');
		$this->execute('UPDATE tag_connection SET approved = 1 WHERE approved NOT IN (0, 1)');
		$this->execute('ALTER TABLE tag_connection
			MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT,
			MODIFY tag_id int(10) unsigned NOT NULL,
			MODIFY approved tinyint(1) NOT NULL DEFAULT 1,
			ADD CONSTRAINT tag_connection_tag_id FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE ON UPDATE CASCADE');

		$this->execute('DELETE tsumego_variant FROM tsumego_variant LEFT JOIN tsumego ON tsumego.id = tsumego_variant.tsumego_id WHERE tsumego.id IS NULL');
		$this->execute('DELETE later FROM tsumego_variant later JOIN tsumego_variant earlier ON earlier.tsumego_id = later.tsumego_id AND earlier.id < later.id');
		$this->execute('ALTER TABLE tsumego_variant
			MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT,
			MODIFY tsumego_id int(10) unsigned NOT NULL,
			ADD UNIQUE KEY tsumego_variant_tsumego_id (tsumego_id),
			ADD CONSTRAINT tsumego_variant_tsumego_id FOREIGN KEY (tsumego_id) REFERENCES tsumego (id) ON DELETE CASCADE ON UPDATE CASCADE');

		$this->execute('DELETE FROM schedule WHERE tsumego_id IS NULL OR set_id IS NULL');
		$this->execute('UPDATE schedule SET published = 1 WHERE published NOT IN (0, 1)');
		$this->execute('ALTER TABLE schedule
			MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT,
			MODIFY tsumego_id int(10) unsigned NOT NULL,
			MODIFY set_id int(10) unsigned NOT NULL,
			MODIFY published tinyint(1) NOT NULL DEFAULT 0');

		$this->execute('UPDATE `set` SET deleted = NOW() WHERE public < 0 AND deleted IS NULL');
		$this->execute('UPDATE `set` SET public = 0 WHERE public IS NULL OR public < 0');
		$this->execute('UPDATE `set` SET public = 1 WHERE public > 1');
		$this->execute('ALTER TABLE `set` MODIFY public tinyint(1) NOT NULL DEFAULT 1');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `set` MODIFY public int(11) DEFAULT 1');

		$this->execute('ALTER TABLE schedule
			MODIFY id int(11) NOT NULL AUTO_INCREMENT,
			MODIFY tsumego_id int(10) unsigned DEFAULT NULL,
			MODIFY set_id int(10) unsigned DEFAULT NULL,
			MODIFY published int(11) NOT NULL');

		$this->execute('ALTER TABLE tsumego_variant DROP FOREIGN KEY tsumego_variant_tsumego_id');
		$this->execute('ALTER TABLE tsumego_variant
			DROP KEY tsumego_variant_tsumego_id,
			MODIFY id int(11) NOT NULL AUTO_INCREMENT,
			MODIFY tsumego_id int(11) NOT NULL DEFAULT 0');

		$this->execute('ALTER TABLE tag_connection DROP FOREIGN KEY tag_connection_tag_id');
		$this->execute('ALTER TABLE tag DROP FOREIGN KEY tag_user_id');
		$this->execute('ALTER TABLE tag
			MODIFY id int(11) NOT NULL AUTO_INCREMENT,
			MODIFY user_id int(11) DEFAULT NULL,
			MODIFY approved int(11) NOT NULL DEFAULT 1,
			MODIFY hint int(11) NOT NULL DEFAULT 0');
		$this->execute('ALTER TABLE tag_connection
			MODIFY id int(11) NOT NULL AUTO_INCREMENT,
			MODIFY tag_id int(11) DEFAULT NULL,
			MODIFY approved int(11) DEFAULT 1,
			ADD CONSTRAINT tag_connection_tag_id FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE ON UPDATE CASCADE');
	}
}
