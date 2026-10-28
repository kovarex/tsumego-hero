<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $sender_user_id
 * @property int $recipient_user_id
 * @property string $text
 * @property DateTimeImmutable $created
 * @property DateTimeImmutable|null $read_at
 */
class Message extends AppEntity {}
