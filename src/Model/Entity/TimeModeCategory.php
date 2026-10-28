<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $name
 * @property int $seconds
 */
class TimeModeCategory extends Entity
{
	protected array $_accessible = ['*' => true, 'id' => false];
}
