<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $tsumego_issue_status_id
 * @property int $tsumego_id
 * @property int $user_id
 * @property DateTimeImmutable $created
 * @property DateTimeImmutable|null $deleted
 */
class TsumegoIssue extends AppEntity {}
