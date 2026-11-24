<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $title
 * @property string|null $title2
 * @property string|null $description
 * @property string|null $image The file of webroot/img the cover was read from before set_cover; no longer read
 * @property string|null $cover_hash Start of the sha256 of the cover in set_cover (SetImage::url())
 * @property int|null $order
 * @property bool $public
 * @property string|null $color
 * @property DateTimeImmutable|null $created
 * @property bool $included_in_time_mode
 * @property int|null $board_theme_index
 * @property int|null $completion_achievement_id
 * @property DateTimeImmutable|null $deleted
 */
class Set extends AppEntity {}
