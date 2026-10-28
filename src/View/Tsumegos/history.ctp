<?php

/**
 * @var View $this
 * @var array $set
 * @var \App\Model\Entity\SetConnection $setConnection
 * @var array $dailyResults
 * @var array $urlParams
 */

?>
<div style="text-align: center;width: fit-content;margin: 0 auto;">
<h2>History of <a href="/<?php echo $setConnection->id; ?>"><?php echo h($set['title']) . ' - ' . $setConnection->num; ?></a></h2>
<?php
use App\Utility\ChartRenderer;
use App\Utility\TsumegoAttemptsRenderer;
echo ChartRenderer::ratingLine('Rating history', $dailyResults);
new TsumegoAttemptsRenderer($urlParams, $setConnection->tsumego_id)->render();
?>
</div>
