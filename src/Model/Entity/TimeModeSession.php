<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $points
 * @property DateTimeImmutable $created
 * @property int $time_mode_session_status_id
 * @property int|null $time_mode_category_id
 * @property int|null $time_mode_rank_id
 * @property int|null $challenge_id
 */
class TimeModeSession extends AppEntity {}
