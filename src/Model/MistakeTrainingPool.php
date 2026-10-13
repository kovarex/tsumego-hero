<?php

/**
 * Mistake training pool: the set of problems a user is currently reviewing.
 * One row per (user, tsumego). Tracks position on the review ladder and the
 * next review date.
 */
class MistakeTrainingPool extends AppModel
{
	public $useTable = 'mistake_training_pool';
}
