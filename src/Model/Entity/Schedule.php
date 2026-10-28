<?php

namespace App\Model\Entity;

use Cake\Chronos\ChronosDate;

/**
 * @property int $id
 * @property ChronosDate $date
 * @property int $tsumego_id
 * @property int $set_id
 * @property bool $published
 * @property int|null $created_by_user_id
 */
class Schedule extends AppEntity {}
