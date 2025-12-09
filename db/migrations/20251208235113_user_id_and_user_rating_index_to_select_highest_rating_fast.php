<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserIdAndUserRatingIndexToSelectHighestRatingFast extends AbstractMigration
{
    public function change(): void
    {
		$this->execute("ALTER TABLE tsumego_attempt ADD INDEX `user_id_and_user_rating` (`user_id`, `user_rating`)");
    }
}
