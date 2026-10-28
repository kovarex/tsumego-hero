<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $image
 * @property string|null $color
 * @property int|null $order
 * @property int|null $xp
 * @property string|null $additional_description
 * @property DateTimeImmutable|null $created
 */
class Achievement extends AppEntity {}
