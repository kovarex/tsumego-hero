<?php

/**
 * Dry run of the migration CommentCoordinatesInSgfSpace: converts exported
 * comments with LegacyCommentConverter and prints the outcome as JSON, without
 * touching any database.
 *
 * Export the comments with the current SGF and description of their problem (mysql --batch output):
 *   SELECT c.id, c.tsumego_id, c.message, c.position, c.created,
 *     (SELECT s.sgf FROM sgf s WHERE s.tsumego_id = c.tsumego_id AND s.accepted = 1 ORDER BY s.id DESC LIMIT 1) AS sgf,
 *     (SELECT t.description FROM tsumego t WHERE t.id = c.tsumego_id) AS description
 *   FROM tsumego_comment c WHERE c.deleted = 0
 *
 * Usage: php scripts/legacy-comment-conversion-report.php comments.tsv > report.json
 */

foreach (['BoardTransformation', 'CommentCoordinates', 'SgfTree', 'LegacyCommentConversion', 'LegacyCommentConverter'] as $class)
	require_once __DIR__ . '/../src/Utility/' . $class . '.php';

use App\Utility\BoardTransformation;
use App\Utility\CommentCoordinates;
use App\Utility\LegacyCommentConverter;
use App\Utility\SgfTree;

/** Undoes the escaping of mysql --batch output. */
function unescapeBatchValue(string $value): ?string
{
	if ($value === 'NULL')
		return null;
	return preg_replace_callback('/\\\\(.)/s', fn($match) => ['n' => "\n", 't' => "\t", '0' => "\0", '\\' => '\\'][$match[1]] ?? $match[1], $value);
}

function transformationName(?BoardTransformation $transformation): ?string
{
	if (!$transformation)
		return null;
	$parts = [];
	if ($transformation->transpose)
		$parts[] = 'transpose';
	if ($transformation->flipX)
		$parts[] = 'mirror left-right';
	if ($transformation->flipY)
		$parts[] = 'mirror top-bottom';
	if ($transformation->invertColors)
		$parts[] = 'swap colours';
	return empty($parts) ? 'as written' : implode(', ', $parts);
}

$lines = file($argv[1] ?? 'php://stdin', FILE_IGNORE_NEW_LINES);
$header = explode("\t", array_shift($lines));
$rows = [];
$trees = [];
foreach ($lines as $line)
{
	$values = array_map('unescapeBatchValue', explode("\t", $line));
	if (count($values) != count($header))
		continue;
	$comment = array_combine($header, $values);
	$sgf = $comment['sgf'] ?? null;
	$tree = $sgf ? ($trees[$comment['tsumego_id']] ??= SgfTree::parse($sgf)) : null;
	$row = [
		'id' => (int) $comment['id'],
		'tsumego_id' => (int) $comment['tsumego_id'],
		'created' => $comment['created'] ?? null,
		'message' => $comment['message'],
		'position' => $comment['position'],
		'description' => $comment['description'] ?? null];
	if (!$tree)
	{
		$row['converted_message'] = LegacyCommentConverter::withoutMarker($comment['message']);
		$row['converted_position'] = $comment['position'];
		$row['text_method'] = 'no SGF';
		$rows[] = $row;
		continue;
	}

	$size = $tree->boardSize();
	$conversion = (new LegacyCommentConverter($tree, $comment['description'] ?? null))->convert($comment['message'], $comment['position']);
	$stones = [];
	foreach ($tree->setupStones($size) as $key => $color)
		$stones[] = [...array_map('intval', explode('/', $key)), $color];
	$moves = [];
	foreach ($tree->allNodes() as $node)
		if ($move = $node->move($size))
			$moves[$move[1] . '/' . $move[2]] = [$move[1], $move[2]];
	$points = [];
	foreach ($conversion->labels as $label)
		if ($point = CommentCoordinates::labelToPoint($label, $size))
			$points[] = [$label, ...$conversion->textTransformation->applyToPoint($point[0], $point[1], $size)];

	$candidates = [];
	foreach ($conversion->candidates as $candidate)
		$candidates[] = [
			'transformation' => transformationName($candidate['transformation']),
			'score' => $candidate['score'],
			'site_view' => $candidate['siteView'],
			'labels' => array_map(fn(?string $key) => $key === null ? null : CommentCoordinates::pointToLabel(...array_map('intval', explode('/', $key)), size: $size), $candidate['points'])];

	$rows[] = $row + [
		'converted_message' => $conversion->message,
		'converted_position' => $conversion->position,
		'size' => $size,
		'stones' => $stones,
		'moves' => array_values($moves),
		'labels' => $points,
		'text_method' => $conversion->textMethod,
		'text_transformation' => transformationName($conversion->textTransformation),
		'text_score' => $conversion->textScore,
		'text_alternatives' => $conversion->textAlternatives,
		'candidates' => $candidates,
		'color_evidence' => $conversion->colorEvidence,
		'colors_swapped' => $conversion->colorsSwapped,
		'position_method' => $conversion->positionMethod,
		'anchor_transformation' => transformationName($conversion->anchorTransformation)];
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
