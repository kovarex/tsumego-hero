<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property string|null $name The name its creator gave it, see ChallengeTable::title()
 * @property int $created_by_user_id
 * @property int $minimum_time_mode_rank_id
 * @property int $maximum_time_mode_rank_id
 * @property int $seconds_per_problem
 * @property int|null $rematch_of_challenge_id The challenge this is the rematch of
 * @property DateTimeImmutable $created
 */
class Challenge extends AppEntity {}
