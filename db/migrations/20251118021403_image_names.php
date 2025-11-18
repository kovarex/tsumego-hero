<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ImageNames extends AbstractMigration
{
    public function up(): void {
		$this->query("UPDATE `set` SET image = REPLACE(image, '.PNG', '.png')");
		$this->query("UPDATE `set` SET image = REPLACE(image, '.JPG', '.jpg')");
    }
}
