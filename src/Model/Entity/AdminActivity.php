<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $tsumego_id
 * @property int|null $set_id
 * @property int $type
 * @property string|null $old_value
 * @property string|null $new_value
 * @property DateTimeImmutable $created
 */
class AdminActivity extends AppEntity {}
