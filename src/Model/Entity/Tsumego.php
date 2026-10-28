<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property string|null $description
 * @property string|null $hint
 * @property string $author
 * @property int|null $author_user_id
 * @property float $rating
 * @property float|null $minimum_rating
 * @property float|null $maximum_rating
 * @property DateTimeImmutable|null $created
 * @property int|null $minimum_liberties
 * @property int|null $maximum_liberties
 * @property int|null $variance
 * @property int|null $liberty_count
 * @property int|null $inside_liberties
 * @property int|null $semeai_type
 * @property bool $alternative_response
 * @property bool $pass
 * @property DateTimeImmutable|null $deleted
 */
class Tsumego extends AppEntity {}
