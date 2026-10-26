<?php

/**
 * What makes a problem a multiple choice or a score estimating one: its answers. A problem has one
 * variant at most.
 */
class TsumegoVariant extends AppModel
{
	// the rows of tsumego_variant_type
	public const TYPE_MULTIPLE_CHOICE = 1;
	public const TYPE_SCORE_ESTIMATING = 2;

	/** The names the board (besogo's multipleChoiceCustom) knows the types by. */
	public const BOARD_TYPE_NAMES = [
		self::TYPE_MULTIPLE_CHOICE => 'multiple_choice',
		self::TYPE_SCORE_ESTIMATING => 'score_estimating'];

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'tsumego_variant';
		parent::__construct($id, $table, $ds);
	}
}
