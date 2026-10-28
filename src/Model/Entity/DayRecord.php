<?php

namespace App\Model\Entity;

use Cake\Chronos\ChronosDate;

/**
 * @property int $id
 * @property int|null $user_id
 * @property ChronosDate|null $date
 * @property string|null $quote
 * @property string $gems
 * @property int $emerald_solves
 * @property int $sapphire_solves
 * @property int $ruby_solves
 */
class DayRecord extends AppEntity {}
