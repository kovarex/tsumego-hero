<?php

namespace App\Model\Table;

use App\Utility\AdminActivityLogger;
use Cake\ORM\TableRegistry;

//What makes a problem a multiple choice one: its answers. A problem has one variant at most.
class TsumegoVariantTable extends AppTable
{
	// the rows of tsumego_variant_type
	public const TYPE_MULTIPLE_CHOICE = 1;

	//The names the board (besogo's multipleChoiceCustom) knows the types by.
	public const BOARD_TYPE_NAMES = [
		self::TYPE_MULTIPLE_CHOICE => 'multiple_choice'];

	//Makes a problem a multiple choice problem, or not (null). A problem has
	//one variant at most, and keeps the answers of its variant while the type stays.
	public static function changeType(int $tsumegoID, ?int $type): void
	{
		$variantTable = TableRegistry::getTableLocator()->get('TsumegoVariant');
		$variant = $variantTable->find()->where(['tsumego_id' => $tsumegoID])->first();
		$activityTypes = [self::TYPE_MULTIPLE_CHOICE => AdminActivityTypeTable::MULTIPLE_CHOICE];
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
		$newVariant = ['tsumego_id' => $tsumegoID, 'tsumego_variant_type_id' => $type,
			'answer1' => 'Black is dead', 'answer2' => 'White is dead', 'answer3' => 'Ko', 'answer4' => 'Seki', 'correct_answer' => 1];
		$variantTable->saveOrFail($variantTable->newEntity($newVariant));
	}
}
