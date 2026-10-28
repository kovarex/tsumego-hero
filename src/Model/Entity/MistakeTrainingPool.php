<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tsumego_id
 * @property int $rung
 * @property int $lapses
 * @property DateTimeImmutable|null $next_due
 * @property DateTimeImmutable|null $last_review_day
 * @property int|null $last_mistake_training_result_id
 * @property DateTimeImmutable $created
 * @property DateTimeImmutable $modified
 */
class MistakeTrainingPool extends AppEntity {}
