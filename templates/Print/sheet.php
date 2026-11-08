<?php

use App\Utility\PrintSheet;

/**
 * The problems on paper, a page of its own without the site's header and menu (PrintController::sheet()).
 * On the screen the pages are sheets with the Print button above them; the printer gets the pages
 * alone. A page is sized to fit both A4 and Letter within the margins of @page.
 *
 * @var \App\View\AppView $this
 * @var PrintSheet $sheet
 */

[$columns, $rows] = PrintSheet::grid($sheet->problemsPerPage);
[$answerColumns, $answerRows] = PrintSheet::grid(PrintSheet::ANSWERS_PER_PAGE);
$problemCount = count($sheet->problems);
$pageCount = count($sheet->problemPages()) + count($sheet->answerPages());
$selectionLink = '/print?problems=' . implode(',', array_column($sheet->problems, 'setConnectionId'));
//The problem number stays whatever is left out, so the answers still match the problems
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Tsumego Hero - Problems to print</title>
<style>
	@page { margin: 12mm; }
	* { box-sizing: border-box; }
	body { margin: 0; background: #e6e6e6; color: #000; font-family: Arial, Helvetica, sans-serif; }
	.print-sheet__toolbar { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; justify-content: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #ccc; font-size: 14px; }
	.print-sheet__toolbar button { padding: 8px 24px; font-size: 16px; font-weight: bold; color: #fff; background: #2f6f3e; border: 0; border-radius: 4px; cursor: pointer; }
	.print-sheet__toolbar a { color: #1a4d8f; }
	.print-sheet__empty { text-align: center; padding: 48px 16px; }
	/* A4 is 210 × 297 mm and Letter 216 × 279 mm, so a page of 186 × 250 mm fits either within the margins of @page;
	   on the screen it is drawn as a sheet with the margins around it */
	.print-page { display: flex; flex-direction: column; width: 210mm; height: 274mm; margin: 8mm auto; padding: 12mm; background: #fff; box-shadow: 0 1px 6px rgba(0, 0, 0, 0.25); break-after: page; overflow: hidden; }
	.print-page:last-child { break-after: auto; }
	.print-page__heading { margin: 0 0 4mm; font-size: 14pt; }
	.print-page__grid { flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(var(--columns), minmax(0, 1fr)); grid-template-rows: repeat(var(--rows), minmax(0, 1fr)); gap: 6mm; }
	.print-problem { display: flex; flex-direction: column; min-height: 0; margin: 0; break-inside: avoid; }
	.print-problem__caption { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 2mm; margin-bottom: 1.5mm; font-size: 11pt; line-height: 1.25; }
	.print-problem__number { font-weight: bold; }
	.print-problem__origin { color: #444; font-size: 8.5pt; }
	.print-problem__board { flex: 1; min-height: 0; display: flex; justify-content: center; }
	.print-board { display: block; width: 100%; height: 100%; }
	.print-problem__notes { flex: 0 0 auto; display: flex; flex-direction: column; justify-content: space-around; height: 18mm; margin-top: 2mm; }
	.print-problem__notes span { display: block; border-bottom: 0.3mm solid #888; height: 0; }
	.print-problem__answer-notes, .print-problem__no-answer { margin: 1mm 0 0; font-size: 8.5pt; color: #444; }
	.print-page--answers .print-page__grid { gap: 4mm; }
	.print-page--answers .print-problem__caption { font-size: 9pt; }
	@media print {
		body { background: #fff; }
		.print-sheet__toolbar { display: none; }
		.print-page { width: 186mm; height: 250mm; margin: 0; padding: 0; box-shadow: none; }
	}
</style>
</head>
<body class="print-sheet">
<div class="print-sheet__toolbar">
	<button type="button" id="print-button" onclick="window.print();"<?= $problemCount === 0 ? ' disabled' : '' ?>>Print</button>
	<span><?= $problemCount ?> <?= $problemCount === 1 ? 'problem' : 'problems' ?>, <?= $pageCount ?> <?= $pageCount === 1 ? 'page' : 'pages' ?>. A4 and Letter both fit.</span>
	<a href="<?= h($selectionLink) ?>">Change the problems</a>
</div>
<?php if ($problemCount === 0): ?>
<p class="print-sheet__empty">No problems to print. Pick them on <a href="/print">the print page</a>.</p>
<?php endif; ?>
<?php foreach ($sheet->problemPages() as $page): ?>
<section class="print-page">
	<div class="print-page__grid" style="--columns: <?= $columns ?>; --rows: <?= $rows ?>;">
		<?php foreach ($page as $problem): ?>
		<figure class="print-problem" data-set-connection-id="<?= $problem['setConnectionId'] ?>">
			<figcaption class="print-problem__caption">
				<span class="print-problem__number"><?= $problem['number'] ?>.</span>
				<?php if ($sheet->withGoal && $problem['goal'] !== ''): ?>
				<span class="print-problem__goal"><?= h($problem['goal']) ?></span>
				<?php endif; ?>
				<?php if ($sheet->withOrigin): ?>
				<span class="print-problem__origin"><?= h(PrintSheet::origin($problem)) ?></span>
				<?php endif; ?>
			</figcaption>
			<div class="print-problem__board">
				<?= $problem['diagram'] ? $problem['diagram']->problemSvg('Problem ' . $problem['number']) : '' ?>
			</div>
			<?php if ($sheet->withNotes): ?>
			<div class="print-problem__notes"><span></span><span></span><span></span></div>
			<?php endif; ?>
		</figure>
		<?php endforeach; ?>
	</div>
</section>
<?php endforeach; ?>
<?php foreach ($sheet->answerPages() as $index => $page): ?>
<section class="print-page print-page--answers">
	<?php if ($index === 0): ?>
	<h2 class="print-page__heading">Answers</h2>
	<?php endif; ?>
	<div class="print-page__grid" style="--columns: <?= $answerColumns ?>; --rows: <?= $answerRows ?>;">
		<?php foreach ($page as $problem): ?>
		<figure class="print-problem" data-set-connection-id="<?= $problem['setConnectionId'] ?>">
			<figcaption class="print-problem__caption">
				<span class="print-problem__number"><?= $problem['number'] ?>.</span>
				<?php if ($sheet->withOrigin): ?>
				<span class="print-problem__origin"><?= h(PrintSheet::origin($problem)) ?></span>
				<?php endif; ?>
			</figcaption>
			<?php if ($problem['diagram']?->hasAnswer()): ?>
			<div class="print-problem__board">
				<?= $problem['diagram']->answerSvg('Answer ' . $problem['number']) ?>
			</div>
			<?php if (!empty($problem['diagram']->answerNotes())): ?>
			<p class="print-problem__answer-notes"><?= h(implode(', ', $problem['diagram']->answerNotes())) ?></p>
			<?php endif; ?>
			<?php else: ?>
			<p class="print-problem__no-answer">No solution recorded.</p>
			<?php endif; ?>
		</figure>
		<?php endforeach; ?>
	</div>
</section>
<?php endforeach; ?>
</body>
</html>
