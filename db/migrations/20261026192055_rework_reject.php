<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//What a rejection is about comes from reject_type (see the Reject::TYPE_ constants) instead of
//reject.type, which held 'proposal', 'tag name', 'tag' with the name in reject.text, or (since
//the end of 2025) the name of the tag itself. The contributions page took that last kind for a
//new tag and showed "Tag  was created" without the name and the problem.
//A rejected tag on a problem keeps the tag (tag_id) and its name, which stays when the tag is
//deleted. A rejected new tag is deleted with the rejection, so only its name is left; it has no
//problem, so tsumego_id is NULL instead of 0. The rejection goes with its problem and its player.
//Rejections of a problem or a player that is gone go first, the way the new keys would treat them,
//the contributions page did not show them anyway.
final class ReworkReject extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE reject_type (
			id int(10) unsigned NOT NULL,
			name varchar(20) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->execute("INSERT INTO reject_type (id, name) VALUES (1, 'proposal'), (2, 'tag'), (3, 'tag name')");

		$this->execute('DELETE reject FROM reject LEFT JOIN tsumego ON tsumego.id = reject.tsumego_id WHERE reject.tsumego_id <> 0 AND tsumego.id IS NULL');
		$this->execute('DELETE reject FROM reject LEFT JOIN user ON user.id = reject.user_id WHERE user.id IS NULL');
		$this->execute("ALTER TABLE reject
			ADD reject_type_id int(10) unsigned DEFAULT NULL AFTER id,
			ADD tag_id int(10) unsigned DEFAULT NULL COMMENT 'The tag of a rejected tag on a problem' AFTER tsumego_id,
			ADD tag_name varchar(50) DEFAULT NULL COMMENT 'The name of the rejected tag, kept when the tag is deleted' AFTER tag_id,
			MODIFY tsumego_id int(10) unsigned DEFAULT NULL,
			MODIFY user_id int(10) unsigned NOT NULL");
		$this->execute("UPDATE reject SET
			reject_type_id = CASE type WHEN 'proposal' THEN 1 WHEN 'tag name' THEN 3 ELSE 2 END,
			tag_name = LEFT(CASE WHEN type = 'proposal' THEN NULL WHEN type IS NULL OR type IN ('tag', 'tag name') THEN text ELSE type END, 50),
			tsumego_id = NULLIF(tsumego_id, 0)");
		$this->execute('UPDATE reject SET tag_id = (SELECT MIN(tag.id) FROM tag WHERE tag.name = reject.tag_name) WHERE reject_type_id = 2');
		$this->execute('ALTER TABLE reject
			DROP COLUMN type,
			DROP COLUMN text,
			MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT,
			MODIFY reject_type_id int(10) unsigned NOT NULL,
			ADD KEY reject_reject_type_id (reject_type_id),
			ADD KEY reject_tag_id (tag_id),
			ADD KEY reject_user_id (user_id),
			ADD CONSTRAINT reject_reject_type_id FOREIGN KEY (reject_type_id) REFERENCES reject_type (id) ON UPDATE CASCADE,
			ADD CONSTRAINT reject_tsumego_id FOREIGN KEY (tsumego_id) REFERENCES tsumego (id) ON DELETE CASCADE ON UPDATE CASCADE,
			ADD CONSTRAINT reject_tag_id FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE SET NULL ON UPDATE CASCADE,
			ADD CONSTRAINT reject_user_id FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE reject
			DROP FOREIGN KEY reject_reject_type_id,
			DROP FOREIGN KEY reject_tsumego_id,
			DROP FOREIGN KEY reject_tag_id,
			DROP FOREIGN KEY reject_user_id');
		$this->execute('ALTER TABLE reject
			DROP KEY reject_reject_type_id,
			DROP KEY reject_tag_id,
			DROP KEY reject_user_id,
			ADD type varchar(500) DEFAULT NULL AFTER id,
			ADD text varchar(500) DEFAULT NULL AFTER type');
		// the old contributions page shows a rejected tag on a problem with its name from type 'tag'
		$this->execute("UPDATE reject SET type = ELT(reject_type_id, 'proposal', 'tag', 'tag name'), text = tag_name, tsumego_id = COALESCE(tsumego_id, 0)");
		$this->execute('ALTER TABLE reject
			DROP COLUMN reject_type_id,
			DROP COLUMN tag_id,
			DROP COLUMN tag_name,
			MODIFY id int(11) NOT NULL AUTO_INCREMENT,
			MODIFY tsumego_id int(11) NOT NULL,
			MODIFY user_id int(11) NOT NULL');
		$this->execute('DROP TABLE reject_type');
	}
}
