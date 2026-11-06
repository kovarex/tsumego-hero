<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $tsumego_id
 * @property int $order
 * @property string|null $seconds
 * @property string|null $points
 * @property DateTimeImmutable|null $started
 * @property int $time_mode_session_id
 * @property int $time_mode_attempt_status_id
 */
class TimeModeAttempt extends AppEntity {}
