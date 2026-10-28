<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $achievement_id
 * @property int $value
 * @property DateTimeImmutable $created
 */
class AchievementStatus extends AppEntity {}
