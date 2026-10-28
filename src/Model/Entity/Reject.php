<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $reject_type_id
 * @property int|null $tsumego_id
 * @property int|null $tag_id
 * @property string|null $tag_name
 * @property int|null $sgf_id
 * @property int $user_id
 * @property int|null $rejected_by_user_id
 * @property string|null $comment
 * @property DateTimeImmutable $created
 */
class Reject extends AppEntity {}
