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
 * @property bool $alternative_response
 * @property bool $pass
 * @property DateTimeImmutable|null $deleted
 * @property TsumegoCapturingRace|null $tsumego_capturing_race Only when loaded with contain('TsumegoCapturingRace')
 */
class Tsumego extends AppEntity {}
