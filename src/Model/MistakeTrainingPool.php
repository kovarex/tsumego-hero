<?php

/**
 * Mistake training pool: the set of problems a user is currently reviewing.
 * One row per (user, tsumego). Tracks position on the review ladder, the next
 * review date, and the day and result of the last review, which keep the list of
 * one training day stable while it is being played.
 */
class MistakeTrainingPool extends AppModel
{
	public $useTable = 'mistake_training_pool';
}
