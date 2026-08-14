<?php

/**
 * @var View $this
 * @var array $setsNew
 * @var array|null $profileUser
 * @var bool $isOwn
 */

$pageTitle = $isOwn ? 'My Sets' : h($profileUser['name']) . "'s Sets";
?>
<div align="center" class="display1" style="padding-top:10px;">
	<h4><?= $pageTitle ?></h4>

	<?php if ($isOwn): ?>
		<div align="left">
			<p>Your personal problem sets. Create sets to organize your favorites.</p>
			<a class="historyLink2" href="/sets/create">Create Set</a>
		</div>
	<?php endif; ?>

	<div align="center" class="set-index display1">
	<?php if (empty($setsNew)): ?>
		<br><br>
		<p><?= $isOwn ? 'No sets yet. Click the heart on any problem to start your first set, or <a href="/sets/create">create one here</a>.' : 'This user has no public sets.' ?></p>
	<?php else: ?>
		<?php foreach ($setsNew as $i => $set): ?>
			<?php
				$problems = $set['amount'] == 1 ? 'problem' : 'problems';
				$tilde = '';
				$isZero = $set['solved'] != 0 ? '' : 'display:none;';
			?>
			<a href="/sets/view/<?= $set['id'] ?>" class="box1link">
				<div class="box1 box1topic topic-box<?= $set['id'] ?>"
					style="background-color:<?= $set['color'] ?>;background-image:linear-gradient(rgba(169,169,169,0.30),rgba(0,0,0,0.35));">
					<?php if ($set['solved'] >= 100): ?>
						<div class="collection-completed">completed</div>
					<?php endif; ?>
					<div class="collection-top"><?= h($set['name']) ?></div>
					<div class="collection-middle-left"><?= $set['amount'] ?> <?= $problems ?></div>
					<div class="collection-middle-right"><?= $tilde ?><?= $set['difficulty'] ?></div>
					<div class="collection-bottom">
						<div class="number" id="number<?= $i ?>">0</div>
						<div align="left" class="reward-bar-container">
							<div id="account-bar-wrapper2">
								<div id="account-bar2">
									<div id="xp-bar2">
										<div class="xp-bar-empty"></div>
										<div id="xp-bar-fill2<?= $i ?>" class="xp-bar-fill-c4" style="width:5%;transition:0.6s;<?= $isZero ?>">
											<div id="xp-increase-fx2<?= $i ?>">
												<div id="xp-increase-fx-flicker2<?= $i ?>"></div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</a>
		<?php endforeach; ?>
	<?php endif; ?>
	</div>
</div>

<script>
<?php foreach ($setsNew as $i => $set): ?>
animateNumber<?= $i ?>(0, <?= $set['solved'] ?>, .6);
function animateNumber<?= $i ?>(start, end, duration) {
	const element = document.getElementById("number<?= $i ?>");
	const range = end - start;
	const increment = range / (duration * 60);
	let current = start;
	const step = () => {
		current += increment;
		if ((increment > 0 && current >= end) || (increment <= 0 && current <= end)) {
			element.textContent = Math.round(end) + "%";
			document.getElementById("xp-bar-fill2<?= $i ?>").style.width = end + "%";
			return;
		}
		element.textContent = Math.round(current) + "%";
		document.getElementById("xp-bar-fill2<?= $i ?>").style.width = current + "%";
		requestAnimationFrame(step);
	};
	step();
}
<?php endforeach; ?>
</script>
