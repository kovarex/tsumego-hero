<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property int $classroom_id
 * @property int $user_id
 * @property int $classroom_member_status_id
 * @property DateTimeImmutable $created
 */
class ClassroomMember extends AppEntity {}
