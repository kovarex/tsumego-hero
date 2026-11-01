<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $title
 * @property string|null $title2
 * @property string|null $description
 * @property string|null $image
 * @property int|null $order
 * @property bool $public
 * @property string|null $color
 * @property DateTimeImmutable|null $created
 * @property bool $included_in_time_mode
 * @property bool $played_as_black
 * @property int|null $board_theme_index
 * @property DateTimeImmutable|null $deleted
 */
class Set extends AppEntity {}
