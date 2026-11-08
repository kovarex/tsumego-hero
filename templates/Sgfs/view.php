  <?php

use App\Utility\SgfDescription;
use App\Utility\Util;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var int $id
 * @var string $name
 * @var array $versions
 * @var string $type
 * @var string $ux
 */

?>

	<div class="legacy-center">
	<p class="title">
		<br>
		<?php if($type == 'tsumego')
		{ ?>
			Upload History of <?php echo h($name); ?>
		<?php }
else
{ ?>
			Upload History of <?php echo h($ux); ?>
		<?php } ?>
		<br><br>
	</p>
	<div class="legacy-left">
		<?php if($type == 'tsumego')
		{ ?>
			<a href="/tsumegos/play/<?php echo $id; ?>">back</a>
		<?php }
			else
			{ ?>
			<a href="/sgfs/view/<?php echo $id; ?>">back</a>
		<?php } ?>
	</div>
	<table class="data-table phone-scroll-x">
	<tbody>
	<tr>
		<th>Date</th>
		<th class="legacy-left">&nbsp;Author</th>
		<th class="legacy-left">&nbsp;Version</th>
		<th class="legacy-left">&nbsp;Description</th>
		<th></th>
	</tr>
	<?php
		for($i = 0; $i < count($versions); $i++)
		{
			echo '<tr id="' . $versions[$i]['id'] . '">
			<td class="timeTableLeft versionColor legacy-center">
				<time datetime="' . Util::toIso8601($versions[$i]['created']) . '" data-format="datetime">' . $versions[$i]['created'] . '</time>
			</td>
			<td class="timeTableMiddle versionColor legacy-left">';
			if($versions[$i]['user_id'] === null)
				echo 'automatically generated';
			else
				echo '<a href="/sgfs/view/' . $versions[$i]['tsumego_id'] . '?user=' . $versions[$i]['user_id'] . '">' . h($versions[$i]['user']) . '</a>';
			echo '</td>';
			echo '<td class="timeTableMiddle versionColor legacy-left">';
			if($type == 'tsumego')
				echo '<a href="/tsumegos/play/' . ($versions[$i]['tsumego_id']) . '"> ' . h($versions[$i]['title']) . '</a> ';
			else
				echo '<a href="/sgfs/view/' . $versions[$i]['tsumego_id'] . '"> ' . h($versions[$i]['title']) . '</a> ';
			echo '<a href="/sgfs/view/' . $versions[$i]['tsumego_id'] . '" id="dl1-' . $versions[$i]['id'] . '" data-download="' . h($versions[$i]['title'] . ' v' . $versions[$i]['version'] . '.sgf') . '">v ' . $versions[$i]['version'] . '</a>';
			echo '</td>';
			echo '<td class="timeTableMiddle versionColor proposal-description legacy-left" id="description-' . $versions[$i]['id'] . '">' . SgfDescription::render($versions[$i]['description'] ?? null) . '</td>';
			echo '<td class="timeTableRight versionColor legacy-left">
			<a id="open-' . $versions[$i]['id'] . '" class="openHistoryPointLink" href="/editor?sgfID=' . (int) $versions[$i]['id'] . '">open</a>&nbsp;&nbsp;&nbsp;';
			if ($versions[$i]['diff'])
				echo '<a id="compare-' . $versions[$i]['id'] . '" class="openDiff" href="/editor?sgfID=' . $versions[$i]['id'] . '&diffID=' . $versions[$i]['diff'] . '" data-compared-with="' . (int) $versions[$i]['diff'] . '">diff</a>&nbsp;&nbsp;&nbsp;';
			echo '<a href="#" id="dl2-' . $versions[$i]['id'] . '" data-download="' . h($versions[$i]['num'] . '.sgf') . '">download</a>';
			if($versions[$i]['delete'])
				echo '&nbsp;&nbsp;&nbsp;<a data-delete="' . (int) $versions[$i]['id'] . '" href="#">delete</a>';
			echo '</td>
			</tr>';
		}
	?>
	</tbody>
	</table>
</div>
<br>
<?php
//All webroot/js/UploadHistoryPage.js needs to know about this request: the SGF of each version, once, for its downloads
$uploadHistoryPage = [
	'problemID' => (int) $id,
	'sgfs' => array_column($versions, 'sgf', 'id'),
];
?>
<script>
	const uploadHistoryPage = <?php echo json_encode($uploadHistoryPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT); ?>;
</script>
<?php echo ViteManifest::legacyScript('problem-admin'); ?>
