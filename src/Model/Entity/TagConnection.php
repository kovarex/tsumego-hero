<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $tag_id
 * @property int|null $user_id
 * @property int $tsumego_id
 * @property bool $approved
 * @property DateTimeImmutable $created
 */
class TagConnection extends AppEntity {}
