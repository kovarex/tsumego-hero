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
 * @property bool $pass
 * @property bool $played_as_black
 * @property DateTimeImmutable|null $deleted
 * @property TsumegoSemeai|null $tsumego_semeai Only when loaded with contain('TsumegoSemeai')
 */
class Tsumego extends AppEntity {}
