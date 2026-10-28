<?php

namespace App\Model\Table;

use AdminActivityType;
use App\Model\Entity\TsumegoVariant;
use App\Utility\AdminActivityLogger;
use Cake\ORM\TableRegistry;

//What makes a problem a multiple choice or a score estimating one: its answers. A problem has one variant at most.
class TsumegoVariantTable extends AppTable
{
	// the rows of tsumego_variant_type
	public const TYPE_MULTIPLE_CHOICE = 1;
	public const TYPE_SCORE_ESTIMATING = 2;

	//The names the board (besogo's multipleChoiceCustom) knows the types by.
	public const BOARD_TYPE_NAMES = [
		self::TYPE_MULTIPLE_CHOICE => 'multiple_choice',
		self::TYPE_SCORE_ESTIMATING => 'score_estimating'];

	//Makes a problem a multiple choice or a score estimating problem, or neither (null). A problem has
	//one variant at most, and keeps the answers of its variant while the type stays.
	public static function changeType(int $tsumegoID, ?int $type): void
	{
		$variantTable = TableRegistry::getTableLocator()->get('TsumegoVariant');
		/** @var TsumegoVariant|null $variant */
		$variant = $variantTable->find()->where(['tsumego_id' => $tsumegoID])->first();
		$activityTypes = [
			self::TYPE_MULTIPLE_CHOICE => AdminActivityType::MULTIPLE_CHOICE,
			self::TYPE_SCORE_ESTIMATING => AdminActivityType::SCORE_ESTIMATING];
		$currentType = $variant ? $variant->tsumego_variant_type_id : null;
		if ($currentType === $type)
			return;
		if ($variant)
		{
			AdminActivityLogger::log($activityTypes[$currentType], $tsumegoID, null, null, '0');
			$variantTable->deleteOrFail($variant);
		}
		if ($type === null)
			return;

		AdminActivityLogger::log($activityTypes[$type], $tsumegoID, null, null, '1');
		$newVariant = ['tsumego_id' => $tsumegoID, 'tsumego_variant_type_id' => $type];
		if ($type === self::TYPE_MULTIPLE_CHOICE)
			$newVariant += ['answer1' => 'Black is dead', 'answer2' => 'White is dead', 'answer3' => 'Ko', 'answer4' => 'Seki', 'correct_answer' => 1];
		else //the komi, the captures of black and of white, and the score
			$newVariant += ['answer1' => '0', 'answer2' => '0', 'answer3' => '0', 'winner' => '0'];
		$variantTable->saveOrFail($variantTable->newEntity($newVariant));
	}
}
