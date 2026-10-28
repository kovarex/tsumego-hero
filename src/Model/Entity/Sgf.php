<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property string|null $sgf
 * @property int|null $user_id
 * @property int|null $accepted_by_user_id
 * @property int $tsumego_id
 * @property int $sgf_status_id
 * @property DateTimeImmutable|null $created
 * @property DateTimeImmutable|null $accepted
 * @property string|null $first_move_color
 * @property string|null $correct_moves
 * @property string $preview
 * @property int|null $source_sgf_id
 * @property string|null $transformation_from_source
 * @property string|null $description
 */
class Sgf extends AppEntity {}
