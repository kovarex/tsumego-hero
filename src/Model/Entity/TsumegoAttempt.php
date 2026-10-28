<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tsumego_id
 * @property int $gain
 * @property bool $solved
 * @property int $seconds
 * @property int $misplays
 * @property int|null $user_rating
 * @property int $tsumego_rating
 * @property DateTimeImmutable $created
 */
class TsumegoAttempt extends AppEntity {}
