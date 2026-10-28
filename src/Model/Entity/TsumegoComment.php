<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $tsumego_id
 * @property int|null $tsumego_issue_id
 * @property string $message
 * @property DateTimeImmutable $created
 * @property int $user_id
 * @property string|null $position
 * @property DateTimeImmutable|null $deleted
 */
class TsumegoComment extends AppEntity {}
