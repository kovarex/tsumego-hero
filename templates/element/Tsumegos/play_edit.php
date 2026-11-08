<?php

use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoVariantTable;
use App\Utility\Auth;
use App\Utility\Rating;
use App\Utility\Trash;
use App\Utility\Util;

//What a player can do with the problem besides solving it, what they can propose for it and what an
//admin can edit on it, behind the info button (⋯) of the play page, so a player who only solves sees none
//of it. The comments have a button of their own (play.php).
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
 * @var bool $hasDuplicates
 * @var array<int, array<string, mixed>> $setConnections
 * @var bool $solved
 * @var string $tagEditorProps
 */

$checked = fn(bool $isChecked): string => $isChecked ? 'checked="checked"' : '';
$variantType = $tv ? (int) $tv->tsumego_variant_type_id : null;
?>
<div id="editSection" class="play-edit" style="display: none;">
	<?php //The tags of the problem first, with adding one under them ?>
	<div class="tag-container">
		<div data-tag-editor-root data-props="<?php echo htmlspecialchars($tagEditorProps); ?>"></div>
		<?php if ($isAllowedToContribute): ?>
			<?php //The adding of a tag, right under the tags (TagEditor.tsx renders it here) ?>
			<div id="tag-adding" data-testid="tag-adding"></div>
		<?php endif; ?>
	</div>
	<?php //The board's own settings, which PlayBoard.js moves here from under the board: the corner, the rotation, the colours, the favourite and the author ?>
	<div id="boardSettings" class="play-board-settings"></div>
	<p>
		<?php //Opening the problem in the editor, or proposing a change in it, comes first ?>
		<?php if (Auth::isAdmin()): ?>
			<a id="openSgfLink" href="/editor/?setConnectionID=<?php echo $setConnection->id; ?>&sgfID=<?php echo $sgf->id; ?>" class="selectable-text mr-5">Open</a>
		<?php elseif ($isAllowedToContribute && $hasSgfProposal): ?>
			<a id="openSgfLink" href="/editor/?setConnectionID=<?php echo $setConnection->id; ?>&sgfID=<?php echo $sgf->id; ?>&proposal=1" class="selectable-text mr-5 play-edit__proposal-sent">Proposal sent</a>
		<?php elseif ($isAllowedToContribute): ?>
			<a id="openSgfLink" href="/editor/?setConnectionID=<?php echo $setConnection->id; ?>&sgfID=<?php echo $sgf->id; ?>&proposal=1" class="selectable-text mr-5">Make Proposal</a>
		<?php endif; ?>
		<a id="findSimilarProblems" class="selectable-text mr-5">Find Similar Problems</a>
		<?php if (Auth::isAdmin()): ?>
			<?php //the history is worth a look once a person changed the problem, not for an automatic version alone ?>
			<a id="showx6" href="/sgfs/view/<?php echo $tsumego->id; ?>" class="selectable-text mr-5 <?php echo $sgf->user_id !== null ? 'historyLink' : ''; ?>">History</a>
			<a id="showx8" href="/tsumegos/history/<?php echo $setConnection->id; ?>" class="selectable-text mr-5">Rating</a>
			<a id="showx99" class="selectable-text mr-5">Request Solution</a>
		<?php endif; ?>
	</p>
	<?php if ($hasDuplicates): ?>
		<div class="duplicateTable">Duplicate group:
			<?php echo implode(', ', array_map(fn($otherSetConnection) => '<a href="/' . $otherSetConnection['id'] . '">' . h($otherSetConnection['title']) . '</a>', $setConnections)); ?>
		</div>
	<?php endif; ?>
	<?php if (Auth::isAdmin()): ?>
		<?php //one form for all the problem's settings, the SGF file included: one Submit saves what changed ?>
		<form id="tsumego-edit" class="play-edit__part" method="post" action="/tsumegos/edit/<?php echo $tsumego->id; ?>" enctype="multipart/form-data">
			<input type="hidden" name="redirect" value="<?php echo h(Util::requestUri()); ?>">
			<input type="hidden" name="color_swapped" value="<?php echo $swapColors ? '1' : '0'; ?>">
			<input type="hidden" name="set_connection_id" value="<?php echo $setConnection->id; ?>">
			<table>
				<tr>
					<td><label for="description">Description:</label></td>
					<td>
						<textarea name="description" id="description" rows="3" class="play-edit__field"><?php echo h($displayDescription); ?></textarea>
						<br><small class="play-edit__note">Describe the problem. <?php echo ($playerColor === 'white') ? 'White' : 'Black'; ?> is to move.</small>
					</td>
				</tr>
				<tr>
					<td><label for="hint">Hint:</label></td>
					<td><textarea name="hint" id="hint" rows="1" class="play-edit__field"><?php echo h($tsumego->hint); ?></textarea></td>
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
				<tr>
					<td>Enable passing:</td>
					<td class="play-edit__choices">
						<input type="radio" id="pass-off" name="pass" value="0" <?php echo $checked($tsumego->pass != 1); ?>><label for="pass-off">no</label>
						<input type="radio" id="pass-on" name="pass" value="1" <?php echo $checked($tsumego->pass == 1); ?>><label for="pass-on">yes</label>
					</td>
				</tr>
				<?php if ($isSandbox): ?>
					<tr>
						<td>Question:</td>
						<td class="play-edit__choices">
							<input type="radio" id="variant-type-none" name="variant_type" value="" <?php echo $checked($variantType === null); ?>><label for="variant-type-none">none</label>
							<input type="radio" id="variant-type-multiple-choice" name="variant_type" value="<?php echo TsumegoVariantTable::TYPE_MULTIPLE_CHOICE; ?>" <?php echo $checked($variantType === TsumegoVariantTable::TYPE_MULTIPLE_CHOICE); ?>><label for="variant-type-multiple-choice">multiple choice</label>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ($isSandbox && $tv != null): ?>
					<?php for ($answer = 1; $answer <= 4; $answer++): ?>
						<tr>
							<td><label for="variant-answer<?php echo $answer; ?>">Answer <?php echo $answer; ?>:</label></td>
							<td class="play-edit__choices">
								<input name="answer<?php echo $answer; ?>" value="<?php echo h($tv->get('answer' . $answer)); ?>" placeholder="Answer <?php echo $answer; ?>" type="text" id="variant-answer<?php echo $answer; ?>">
								<input type="radio" name="correct_answer" id="variant-correct-answer<?php echo $answer; ?>" value="<?php echo $answer; ?>"<?php echo $tv->correct_answer == $answer ? ' checked="checked"' : ''; ?>><label for="variant-correct-answer<?php echo $answer; ?>">correct</label>
							</td>
						</tr>
					<?php endfor; ?>
					<tr>
						<td><label for="variant-explanation">Explanation:</label></td>
						<td><input name="explanation" value="<?php echo h($tv->explanation); ?>" placeholder="Explanation" type="text" id="variant-explanation"></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td><label for="admin-upload-button">New SGF:</label></td>
					<td class="play-edit__file">
						<input id="admin-upload-button" type="file" name="adminUpload" />
						<input id="admin-upload-description" type="text" name="sgf_description" maxlength="<?php echo SgfTable::DESCRIPTION_MAX_LENGTH; ?>" placeholder="What changed and why" />
					</td>
				</tr>
				<?php if (empty($tsumego->deleted)): ?>
					<tr>
						<td><label for="delete">Delete:</label></td>
						<td>
							<input type="text" name="delete" id="delete" value="" placeholder="Type delete">
							<br><small class="play-edit__note">Moves the problem to the trash, it can be restored for <?php echo Trash::$RETENTION_DAYS; ?> days.</small>
						</td>
					</tr>
				<?php endif; ?>
			</table>
			<input type="submit" value="Submit" id="tsumego-edit-submit">
		</form>
	<?php endif; ?>
</div>
