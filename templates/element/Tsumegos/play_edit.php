<?php

use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoVariantTable;
use App\Utility\Auth;
use App\Utility\Rating;
use App\Utility\Trash;
use App\Utility\Util;

//What a player can propose for the problem and what an admin can edit on it, under the board behind
//one toggle (Edit / Propose a change), so a player who only solves sees none of it
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Tsumego $tsumego
 * @var \App\Model\Entity\SetConnection $setConnection
 * @var \App\Model\Entity\Sgf $sgf
 * @var \App\Model\Entity\TsumegoVariant|null $tv
 * @var bool $isSandbox
 * @var bool $swapColors
 * @var string $displayDescription
 * @var string $playerColor
 * @var bool $hasSgfProposal
 * @var bool $isAllowedToContribute
 */

$checked = fn(bool $isChecked): string => $isChecked ? 'checked="checked"' : '';
$variantType = $tv ? (int) $tv->tsumego_variant_type_id : null;
?>
<div id="editSection" class="play-edit" style="display: none;">
	<?php if ($isAllowedToContribute): ?>
		<div id="tag-adding" data-testid="tag-adding"></div>
	<?php endif; ?>
	<p>
		<?php if (Auth::isAdmin()): ?>
			<a id="openSgfLink" href="/editor/?setConnectionID=<?php echo $setConnection->id; ?>&sgfID=<?php echo $sgf->id; ?>" class="selectable-text mr-5">Open</a>
			<?php //the history is worth a look once a person changed the problem, not for an automatic version alone ?>
			<a id="showx6" href="/sgfs/view/<?php echo $tsumego->id; ?>" class="selectable-text mr-5 <?php echo $sgf->user_id !== null ? 'historyLink' : ''; ?>">History</a>
			<a id="showx8" href="/tsumegos/history/<?php echo $setConnection->id; ?>" class="selectable-text mr-5">Rating</a>
			<a id="showx99" class="selectable-text mr-5">Request Solution</a>
		<?php elseif ($hasSgfProposal): ?>
			<a id="openSgfLink" href="/editor/?setConnectionID=<?php echo $setConnection->id; ?>&sgfID=<?php echo $sgf->id; ?>&proposal=1" class="selectable-text mr-5" style="color:var(--text-softer-color);">Proposal sent</a>
		<?php else: ?>
			<a id="openSgfLink" href="/editor/?setConnectionID=<?php echo $setConnection->id; ?>&sgfID=<?php echo $sgf->id; ?>&proposal=1" class="selectable-text mr-5">Make Proposal</a>
		<?php endif; ?>
	</p>
	<?php if (Auth::isAdmin()): ?>
		<div class="play-edit__part modify-description-panel">
		<form id="tsumego-edit" method="post" action="/tsumegos/edit/<?php echo $tsumego->id; ?>">
			<input type="hidden" name="tsumego_id" value="<?php echo $tsumego->id; ?>">
			<input type="hidden" name="redirect" value="<?php echo h(Util::requestUri()); ?>">
			<input type="hidden" name="color_swapped" value="<?php echo $swapColors ? '1' : '0'; ?>">
			<table>
				<tr>
					<td><label for="description">Description:</label></td>
					<td>
						<textarea name="description" id="description" rows="3" style="width: 100%;"><?php echo h($displayDescription); ?></textarea>
						<br><small style="color: #888;">Describe the problem. <?php echo ($playerColor === 'white') ? 'White' : 'Black'; ?> is to move.</small>
					</td>
				</tr>
				<tr>
					<td><label for="hint">Hint:</label></td>
					<td><textarea name="hint" id="hint" rows="1" style="width: 100%;"><?php echo h($tsumego->hint); ?></textarea></td>
				</tr>
				<tr>
					<td><label for="rating">Rating:</label></td>
					<td><input type="text" name="rating" id="rating" value="<?php echo Rating::getReadableRankFromRatingWhenPossible($tsumego->rating); ?>"></td>
				</tr>
				<tr>
					<td><label for="minimum-rating">Minimum rating:</label></td>
					<td><input type="text" name="minimum-rating" id="minimum-rating" value="<?php echo Rating::getReadableRankFromRatingWhenPossible($tsumego->minimum_rating); ?>"></td>
				</tr>
				<tr>
					<td><label for="maximum-rating">Maximum rating:</label></td>
					<td><input type="text" name="maximum-rating" id="maximum-rating" value="<?php echo Rating::getReadableRankFromRatingWhenPossible($tsumego->maximum_rating); ?>"></td>
				</tr>
				<tr>
					<td><label for="author">Author:</label></td>
					<td><input type="text" name="author" id="author" value="<?php echo h($tsumego->author); ?>"></td>
				</tr>
				<?php if (empty($tsumego->deleted)): ?>
				<tr>
					<td><label for="delete">Delete:</label></td>
					<td>
						<input type="text" name="delete" id="delete" value="" placeholder="Type delete">
						<br><small style="color: #888;">Moves the problem to the trash, it can be restored for <?php echo Trash::$RETENTION_DAYS; ?> days.</small>
					</td>
				</tr>
				<?php endif; ?>
			</table>
			<input type="submit" value="submit" id="tsumego-edit-submit">
		</form>
		</div>
		<div class="play-edit__part" id="msg5">
			<form action="/tsumegos/settings/<?php echo $tsumego->id; ?>" method="POST">
				<table>
					<tr>
						<td>Enable passing</td>
						<td><input type="radio" id="pass-off" name="pass" value="0" <?php echo $checked($tsumego->pass != 1); ?>><label for="pass-off">no</label></td>
						<td><input type="radio" id="pass-on" name="pass" value="1" <?php echo $checked($tsumego->pass == 1); ?>><label for="pass-on">yes</label></td>
					</tr>
					<?php if ($isSandbox): ?>
						<tr>
							<td>Question</td>
							<td><input type="radio" id="variant-type-none" name="variant_type" value="" <?php echo $checked($variantType === null); ?>><label for="variant-type-none">none</label></td>
							<td><input type="radio" id="variant-type-multiple-choice" name="variant_type" value="<?php echo TsumegoVariantTable::TYPE_MULTIPLE_CHOICE; ?>" <?php echo $checked($variantType === TsumegoVariantTable::TYPE_MULTIPLE_CHOICE); ?>><label for="variant-type-multiple-choice">multiple choice</label></td>
						</tr>
					<?php endif; ?>
				</table>
				<input value="Submit" type="submit"/>
			</form>
		</div>
		<?php if ($isSandbox && $tv != null): ?>
			<div class="play-edit__part" id="showxMC">
				<form action="/tsumegos/variant/<?php echo $tsumego->id; ?>" method="post">
					<?php for ($answer = 1; $answer <= 4; $answer++): ?>
						<div class="input text"><label for="variant-answer<?php echo $answer; ?>">Answer <?php echo $answer; ?>: </label><input name="answer<?php echo $answer; ?>" value="<?php echo h($tv->get('answer' . $answer)); ?>" placeholder="Answer <?php echo $answer; ?>" type="text" id="variant-answer<?php echo $answer; ?>"></div>
					<?php endfor; ?>
					<div class="input textfield"><label for="variant-explanation">Explanation: </label><input name="explanation" value="<?php echo h($tv->explanation); ?>" placeholder="Explanation" type="text" id="variant-explanation"></div>
					<label>Correct: </label>
					<?php for ($answer = 1; $answer <= 4; $answer++): ?>
						<input type="radio" name="correct_answer" id="variant-correct-answer<?php echo $answer; ?>" value="<?php echo $answer; ?>"<?php echo $tv->correct_answer == $answer ? ' checked="checked"' : ''; ?>><label for="variant-correct-answer<?php echo $answer; ?>"><?php echo $answer; ?></label>
					<?php endfor; ?>
					<div class="submit"><input type="submit" value="Submit"></div>
				</form>
			</div>
		<?php endif; ?>
		<div class="play-edit__part" id="msg4">
			<form action="/sgf/upload/<?php echo $setConnection->id; ?>" method="POST" enctype="multipart/form-data">
				<label for="admin-upload-button">Upload SGF: </label>
				<input id="admin-upload-button" type="file" name="adminUpload" />
				<input id="admin-upload-description" type="text" name="description" maxlength="<?php echo SgfTable::DESCRIPTION_MAX_LENGTH; ?>" placeholder="What changed and why (optional)" />
				<input id="admin-upload-submit" value="Submit" type="submit"/>
			</form>
		</div>
	<?php endif; ?>
</div>
