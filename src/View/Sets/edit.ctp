<?php

/**
 * @var View $this
 * @var bool $allArActive
 * @var bool $allArInactive
 * @var bool $allPassActive
 * @var bool $allPassInactive
 * @var bool $canEditSettings
 * @var bool $isSandbox
 * @var array $problems
 * @var array $set
 * @var int $setRating
 * @var TsumegoButtons $tsumegoButtons
 */

$setId = (int) $set['Set']['id'];
$problemCount = count($problems);
$highestOrder = $tsumegoButtons->highestTsumegoOrder ?? 0;
$presetColors = ['#74d14c', '#5b9bd5', '#e6c832', '#d63a49', '#caa8d8', '#ff8fab', '#66ccff', '#8a8a8a'];
$currentColor = $set['Set']['color'] ?? '';
if ($currentColor !== '' && !in_array(strtolower($currentColor), array_map('strtolower', $presetColors)))
	array_unshift($presetColors, $currentColor);
?>
<div class="set-edit">
	<div class="set-edit__header">
		<p class="set-edit__crumb"><a href="/sets/mine">My Sets</a> / Edit Set</p>
		<h1 class="set-edit-title"><?php echo h($set['Set']['title']); ?></h1>
		<p><a class="btn" href="/sets/view/<?php echo $setId; ?>">View Set</a></p>
	</div>

	<div class="set-edit__grid">
		<div class="card card--green set-edit__section">
			<h2 class="set-edit__heading">Details</h2>
			<?php echo $this->Form->create('Set', ['id' => 'set-edit-details', 'enctype' => 'multipart/form-data']); ?>
				<div class="form-field">
					<label class="form-field__label" for="SetTitle">Title</label>
					<input class="form-field__control" type="text" id="SetTitle" name="data[Set][title]" value="<?php echo h($set['Set']['title']); ?>" required>
				</div>
				<div class="form-field">
					<label class="form-field__label" for="SetDescription">Description</label>
					<textarea class="form-field__control" id="SetDescription" name="data[Set][description]" rows="4"><?php echo h($set['Set']['description'] ?? ''); ?></textarea>
					<p class="hint">Allowed tags: &lt;br&gt; &lt;a&gt; &lt;b&gt; &lt;i&gt; &lt;p&gt; &lt;ul&gt; &lt;ol&gt; &lt;li&gt; &lt;img&gt; &lt;font&gt; &lt;table&gt; &lt;tr&gt; &lt;td&gt; &lt;th&gt;</p>
				</div>
				<div class="form-field">
					<label class="form-field__label">Color</label>
					<div class="swatches">
						<?php foreach ($presetColors as $presetColor): ?>
							<input type="radio" class="swatch" name="data[Set][color]" value="<?php echo $presetColor; ?>"
								style="--swatch-color:<?php echo $presetColor; ?>" title="<?php echo $presetColor; ?>"
								<?php echo strcasecmp($presetColor, $currentColor) === 0 ? ' checked' : ''; ?>>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="form-field">
					<label class="form-field__label">Image</label>
					<div class="set-edit__image">
						<?php if (!empty($set['Set']['image'])): ?>
							<img class="set-edit__thumb" src="/img/<?php echo h($set['Set']['image']); ?>" alt="Set image">
						<?php endif; ?>
						<input type="file" name="image" accept=".png,.jpg,.jpeg,.webp">
					</div>
					<p class="hint">Max 2MB, png/jpg/webp.</p>
				</div>
			<?php echo $this->Form->end(['label' => 'Save', 'class' => 'btn']); ?>
		</div>

		<div class="card card--purple set-edit__section">
			<h2 class="set-edit__heading">Problems (<?php echo $problemCount; ?>)</h2>
			<div class="set-edit__problems-head">
				<p class="hint">Click the heart on any problem to add it to this set.</p>
				<label class="set-edit__preview-toggle" title="Toggle board previews"><input type="checkbox" id="preview-zoom-slider">🔍 Preview</label>
			</div>
			<?php if ($problemCount === 0): ?>
				<p class="hint">No problems yet.</p>
			<?php else: ?>
				<div class="set-edit__problems">
					<?php foreach ($tsumegoButtons as $index => $tsumegoButton): ?>
						<div class="set-edit__problem">
							<ul class="set-edit__problem-button">
								<?php $tsumegoButton->render(); ?>
							</ul>
							<div class="set-edit__problem-meta">
								<span class="set-edit__problem-id">#<?php echo $tsumegoButton->tsumegoID; ?></span>
								<span class="set-edit__rating"><?php echo h(Rating::getReadableRankFromRating($tsumegoButton->rating)); ?></span>
							</div>
							<span class="set-edit__actions">
								<?php if ($index > 0): ?>
									<form method="post" action="/sets/reorderTsumego/<?php echo $setId; ?>" style="display:inline">
										<input type="hidden" name="tsumego_id" value="<?php echo $tsumegoButton->tsumegoID; ?>">
										<input type="hidden" name="dir" value="up">
										<button type="submit" class="btn" title="Move up">▲</button>
									</form>
								<?php else: ?>
									<span class="btn btn--disabled" title="Move up">▲</span>
								<?php endif; ?>
								<?php if ($index < $problemCount - 1): ?>
									<form method="post" action="/sets/reorderTsumego/<?php echo $setId; ?>" style="display:inline">
										<input type="hidden" name="tsumego_id" value="<?php echo $tsumegoButton->tsumegoID; ?>">
										<input type="hidden" name="dir" value="down">
										<button type="submit" class="btn" title="Move down">▼</button>
									</form>
								<?php else: ?>
									<span class="btn btn--disabled" title="Move down">▼</span>
								<?php endif; ?>
								<form method="post" action="/sets/removeTsumego/<?php echo $setId; ?>" style="display:inline" onsubmit="return confirm('Remove this problem from the set?');">
									<input type="hidden" name="tsumego_id" value="<?php echo $tsumegoButton->tsumegoID; ?>">
									<button type="submit" class="btn btn--danger" title="Remove">✕</button>
								</form>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($canEditSettings): ?>
		<div class="card card--purple set-edit__section">
			<h2 class="set-edit__heading">Admin</h2>
			<?php SetEditRenderer::renderAddProblemForm($setId, $highestOrder); ?>
			<form method="post" action="/sets/edit/<?php echo $setId; ?>" id="set-edit-settings" class="set-edit__settings">
				<h3 class="set-edit__heading">Re-rate Problems</h3>
				<label for="SetSetDifficulty">Rating</label>
				<input type="text" id="SetSetDifficulty" name="data[Set][setDifficulty]" value="<?php echo h($setRating); ?>">
				<p class="hint">Sets the rating of every problem in this collection. Problems are shared, so this changes their rating everywhere.</p>

				<h3 class="set-edit__heading">Solve Modes</h3>
				<?php
				$arMessage = '';
		$passingMessage = '';
		if ($allArActive)
			$arMessage = '<p class="hint">Alternative Response activated on all problems</p>';
	elseif ($allArInactive)
		$arMessage = '<p class="hint">Alternative Response deactivated on all problems</p>';
if ($allPassActive)
	$passingMessage = '<p class="hint">Passing enabled on all problems</p>';
elseif ($allPassInactive)
	$passingMessage = '<p class="hint">Passing disabled on all problems</p>';
echo $arMessage . $passingMessage;
?>
				<table>
					<tr>
						<td>Alternative Response Mode</td>
						<td><input type="radio" id="r39" name="data[Settings][r39]" value="on"><label for="r39">on</label></td>
						<td><input type="radio" id="r39" name="data[Settings][r39]" value="off"><label for="r39">off</label></td>
					</tr>
					<tr>
						<td>Enable passing</td>
						<td><input type="radio" id="r43" name="data[Settings][r43]" value="no"><label for="r43">no</label></td>
						<td><input type="radio" id="r43" name="data[Settings][r43]" value="yes"><label for="r43">yes</label></td>
					</tr>
				</table>
				<input type="submit" class="btn" value="Apply">
			</form>
			<?php if ($isSandbox): ?>
				<p><a class="btn" href="/users/userstats3/<?php echo $setId; ?>">Activities</a></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="card set-edit__section set-edit__section--danger">
		<h2 class="set-edit__heading">Danger Zone</h2>
		<form method="post" action="/sets/delete/<?php echo $setId; ?>" onsubmit="return confirm('Delete this collection?');">
			<input type="submit" class="btn btn--danger" value="Delete Collection">
		</form>
	</div>
</div>
