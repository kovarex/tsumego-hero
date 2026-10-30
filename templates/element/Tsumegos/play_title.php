<?php

use App\Model\Table\ClassroomAssignmentTable;
use App\Model\Table\SetTable;
use App\Utility\Auth;
use App\Utility\MistakeTraining;

//The title above the board: where in its mode the problem is, and in a class assignment or a
//collection seen through a filter, under it which problem of which collection it really is
/**
 * @var \App\View\AppView $this
 * @var array<string, mixed>|null $classroomAssignment
 * @var \App\Utility\TsumegoButtons|null $tsumegoButtons
 * @var \App\Model\Entity\SetConnection $setConnection
 * @var \App\Model\Entity\Set $set
 * @var \App\Utility\TsumegoFilters $tsumegoFilters
 * @var \App\Utility\TimeMode $timeMode
 * @var float|int|string|null $difficulty
 * @var string $queryTitle
 * @var int $amountOfOtherCollection
 */

$order = $setConnection->num;
?>
<?php if ($classroomAssignment !== null): ?>
	<a id="playTitleA" href="/classes/<?php echo (int) $classroomAssignment['classroom_id']; ?>"><?php echo h(ClassroomAssignmentTable::title($classroomAssignment)) . ' ' . $tsumegoButtons->currentOrder . '/' . $tsumegoButtons->highestTsumegoOrder; ?></a><br>
	<font style="font-weight:400;" color="grey">
		<a style="color:grey;" class="play-title__origin" href="<?php echo SetTable::url($set->id); ?>">(<?php echo h($set->title) . ' ' . $order . '/' . $amountOfOtherCollection; ?>)</a>
	</font>
<?php elseif (Auth::isInTimeMode()): ?>
	<font size="5px"><?php echo $timeMode->currentPosition() . ' of ' . $timeMode->overallCount; ?></font>
<?php elseif (Auth::isInRatingMode()): ?>
	<div class="slidecontainer">
		<input type="range" min="1" max="7" value="<?php echo h((string) $difficulty); ?>" class="slider" id="rangeInput" name="rangeInput">
		<div id="sliderText">regular</div>
	</div>
	<a id="playTitleA" href=""></a>
<?php elseif (Auth::isInMistakeTrainingMode()): ?>
	<a id="playTitleA" href="<?php echo MistakeTraining::$OVERVIEW_LINK; ?>">Mistake Training <?php echo ($tsumegoButtons->currentOrder ?? 0) . '/' . ($tsumegoButtons->highestTsumegoOrder ?? 0); ?></a>
<?php elseif ($tsumegoFilters->query == 'difficulty' || $tsumegoFilters->query == 'tags'): ?>
	<a id="playTitleA" href="<?php echo SetTable::url($tsumegoFilters->getSetID($set), $tsumegoButtons?->getPartition() ?? 1); ?>"><?php echo h($queryTitle); ?></a><br>
	<font style="font-weight:400;" color="grey">
		<a style="color:grey;" class="play-title__origin" href="<?php echo SetTable::url($set->id); ?>">(<?php echo h($set->title) . ' ' . $order . '/' . $amountOfOtherCollection; ?>)</a>
	</font>
<?php else: ?>
	<a id="playTitleA" href="<?php echo SetTable::url($set->id, $tsumegoButtons?->getPartition() ?? 1); ?>"><?php echo h($set->title) . ' ' . $tsumegoButtons?->getPartitionTitleSuffix() . ' ' . $order . '/' . $tsumegoButtons?->highestTsumegoOrder; ?></a>
<?php endif; ?>
