<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;

//The base of the Cake 5 entities. A field the row doesn't have throws instead of reading as null, so
//code still reading the Cake 2 shape, $row['Model']['field'], fails loudly rather than quietly.
class AppEntity extends Entity
{
	protected bool $requireFieldPresence = true;

	protected array $_accessible = ['*' => true, 'id' => false];
}
