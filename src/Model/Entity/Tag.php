<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string|null $link
 * @property int|null $user_id
 * @property bool $approved
 * @property int $color
 * @property bool $hint
 * @property DateTimeImmutable $created
 */
class Tag extends AppEntity {}
