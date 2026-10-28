<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $tsumego_id
 * @property int $tsumego_variant_type_id
 * @property int $correct_answer
 * @property string $answer1
 * @property string $answer2
 * @property string $answer3
 * @property string $answer4
 * @property string|null $winner
 * @property string|null $explanation
 * @property DateTimeImmutable $created
 */
class TsumegoVariant extends AppEntity {}
