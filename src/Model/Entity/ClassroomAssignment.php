<?php

namespace App\Model\Entity;

use Cake\Chronos\ChronosDate;
use DateTimeImmutable;

/**
 * @property int $id
 * @property int $classroom_id
 * @property string|null $title
 * @property ChronosDate $due
 * @property DateTimeImmutable|null $published
 * @property DateTimeImmutable $created
 */
class ClassroomAssignment extends AppEntity {}
