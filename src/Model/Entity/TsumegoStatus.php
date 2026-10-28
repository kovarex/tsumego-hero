<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tsumego_id
 * @property string $status
 * @property DateTimeImmutable $updated
 */
class TsumegoStatus extends AppEntity {}
