<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $created_by_user_id
 * @property int $minimum_time_mode_rank_id
 * @property int $maximum_time_mode_rank_id
 * @property int $seconds_per_problem
 * @property DateTimeImmutable $created
 */
class Challenge extends AppEntity {}
