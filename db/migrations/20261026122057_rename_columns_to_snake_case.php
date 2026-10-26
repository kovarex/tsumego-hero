<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The last camelCase columns outside the user table (done in 20261026102626) get snake_case names
 * that say what they hold:
 *
 * - gemCounter1, 2, 3 count the solves of the day's emerald, sapphire and ruby problems,
 * - numAnswer is the number of the right answer of a multiple choice problem,
 * - minLib, maxLib, libertyCount, insideLiberties, semeaiType set up the capturing race questions.
 *
 * Dead columns of tsumego are dropped: userWin and userLoss (an old success percentage and attempt
 * count, only reset when a problem got published, solved and failed replaced them) and eyeLiberties1,
 * eyeLiberties2 (nothing used them).
 *
 * IRREVERSIBLE in part: a rollback brings the dropped columns back with their defaults, the data is gone.
 */
final class RenameColumnsToSnakeCase extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE achievement CHANGE additionalDescription additional_description VARCHAR(500) DEFAULT NULL');
		$this->execute("
			ALTER TABLE day_record
				CHANGE gemCounter1 emerald_solves INT(11) NOT NULL DEFAULT 0 COMMENT 'Solves of the emerald problems of the day by all players.',
				CHANGE gemCounter2 sapphire_solves INT(11) NOT NULL DEFAULT 0 COMMENT 'Solves of the sapphire problems of the day by all players.',
				CHANGE gemCounter3 ruby_solves INT(11) NOT NULL DEFAULT 0 COMMENT 'Solves of the ruby problems of the day by all players.'");
		$this->execute("
			ALTER TABLE tsumego
				DROP COLUMN userWin,
				DROP COLUMN userLoss,
				DROP COLUMN eyeLiberties1,
				DROP COLUMN eyeLiberties2,
				CHANGE minLib minimum_liberties INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE maxLib maximum_liberties INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE libertyCount liberty_count INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE insideLiberties inside_liberties INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE semeaiType semeai_type INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.'");
		$this->execute("ALTER TABLE tsumego_variant CHANGE numAnswer correct_answer DOUBLE NOT NULL DEFAULT 0 COMMENT 'The number of the right answer of a multiple choice problem.'");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE tsumego_variant CHANGE correct_answer numAnswer DOUBLE NOT NULL DEFAULT 0');
		$this->execute("
			ALTER TABLE tsumego
				CHANGE minimum_liberties minLib INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE maximum_liberties maxLib INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE liberty_count libertyCount INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE inside_liberties insideLiberties INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
				CHANGE semeai_type semeaiType INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races',
				ADD COLUMN userWin FLOAT NOT NULL DEFAULT 0 COMMENT 'Success percentage. (Registered users)' AFTER maximum_rating,
				ADD COLUMN userLoss INT(11) NOT NULL DEFAULT 0 COMMENT 'How often the problem has been tried. (Registered users)' AFTER userWin,
				ADD COLUMN eyeLiberties1 INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races' AFTER insideLiberties,
				ADD COLUMN eyeLiberties2 INT(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races' AFTER eyeLiberties1");
		$this->execute('
			ALTER TABLE day_record
				CHANGE emerald_solves gemCounter1 INT(11) NOT NULL DEFAULT 0,
				CHANGE sapphire_solves gemCounter2 INT(11) NOT NULL DEFAULT 0,
				CHANGE ruby_solves gemCounter3 INT(11) NOT NULL DEFAULT 0');
		$this->execute('ALTER TABLE achievement CHANGE additional_description additionalDescription VARCHAR(500) DEFAULT NULL');
	}
}
