<?php

namespace App\Model\Table;

//Mistake training pool: the set of problems a user is currently reviewing.
//One row per (user, tsumego). Tracks position on the review ladder, the lapses
//that slow the growth of its waits, the next review date, and the day and result
//of the last review, which keep the list of one training day stable while it is
//being played.
class MistakeTrainingPoolTable extends AppTable {}
