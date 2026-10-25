<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TheRulesOfCapturingRaces165Fix extends AbstractMigration
{
    public function change(): void
    {
      //gets rid of wrong count in one case
		  $this->execute("UPDATE tsumego SET variance = 0 WHERE id = 28567;");
    }
}
