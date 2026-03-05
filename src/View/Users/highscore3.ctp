
<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'time']); ?>

<?php
$currentCategory = intval($params1);

// Speed mode buttons — actual navigation links
$categories = [2 => 'Slow', 1 => 'Fast', 0 => 'Blitz'];
?>
<div style="margin-bottom: 16px;">
<?php foreach ($categories as $catIdx => $catName): ?>
	<?php if ($catIdx == $currentCategory): ?>
		<a class="new-button-time-inactive"><?php echo $catName; ?></a>
	<?php else: ?>
		<a class="new-button-time" href="/users/highscore3?category=<?php echo $catIdx; ?>&rank=<?php echo h($params2); ?>"><?php echo $catName; ?></a>
	<?php endif; ?>
<?php endforeach; ?>
</div>

<div style="margin-bottom: 8px;">
<?php
// Rank buttons — only for the current category
for ($j = 0; $j < count($modes[$currentCategory]); $j++):
	$hasData = ($modes[$currentCategory][$j] == '1');
	$isSelected = ($modes2[$currentCategory][$j] == $params2);
	if ($hasData && !$isSelected):
		echo '<a class="new-button-time" href="/users/highscore3?category=' . $currentCategory . '&rank=' . $modes2[$currentCategory][$j] . '">' . $modes2[$currentCategory][$j] . '</a>';
	elseif ($isSelected):
		echo '<a class="new-button-time-inactive">' . $modes2[$currentCategory][$j] . '</a>';
	else:
		echo '<a class="new-button-time-inactive2">' . $modes2[$currentCategory][$j] . '</a>';
	endif;
endfor;
?>
</div>

<?php
echo HighscoreHelper::renderTable(
	'Time Mode Highscore',
	$users,
	[
		['label' => 'Points', 'render' => fn($user) => $user['points']],
	],
	function ($user) {
		$points = $user['points'];
		if ($points > 950) return 'timeTableColor1';
		if ($points > 900) return 'timeTableColor2';
		if ($points > 850) return 'timeTableColor3';
		if ($points > 800) return 'timeTableColor4';
		if ($points > 750) return 'timeTableColor5';
		if ($points > 700) return 'timeTableColor6';
		if ($points > 650) return 'timeTableColor7';
		if ($points > 600) return 'timeTableColor8';
		if ($points > 550) return 'timeTableColor9';
		return 'timeTableColor10';
	},
	'highscoreTable timeHighscoreTable',
);
?>
</div>
<br><br><br><br><br><br>
