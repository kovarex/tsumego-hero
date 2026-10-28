<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $set_id
 * @property int|null $set_partition
 * @property DateTimeImmutable $created
 */
class ProgressDeletion extends AppEntity {}
