<?php

namespace App\Utility;

use ClassRegistry;
use Sgf;

//Comments store their coordinates in the coordinates of the current SGF version
//of their problem. When a new version becomes the current one and it is oriented
//differently (the board editor saves the problem as it showed it: normalised to
//the top-left, maybe mirrored, rotated or with swapped colours), the comments are
//transformed with it, so they keep pointing at the same stones.
//The transformation between the two versions is known exactly when both come
//from the board editor (it records every transformation it applies and sends
//them with the saved SGF). For an SGF uploaded as a file, the setup stones are
//compared: when exactly one transformation turns the old stones into the new
//ones, the comments follow it; otherwise they are left as they are.
class SgfCommentReorientation
{
	//Transforms the comments if the accepted SGF version became the current one in a new orientation.
	public static function afterAccepted(int $sgfID): void
	{
		$Sgf = ClassRegistry::init('Sgf');
		$new = $Sgf->findById($sgfID)['Sgf'] ?? null;
		if (!$new || $new['sgf_status_id'] != Sgf::STATUS_ACCEPTED)
			return;
		$current = $Sgf->find('first', [
			'conditions' => ['tsumego_id' => $new['tsumego_id'], 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
			'order' => 'id DESC'])['Sgf'];
		if ($current['id'] != $new['id'])
			return; // a newer version is shown, the comments are in its coordinates
		$previous = $Sgf->find('first', [
			'conditions' => ['tsumego_id' => $new['tsumego_id'], 'sgf_status_id' => Sgf::STATUS_ACCEPTED, 'id <' => $new['id']],
			'order' => 'id DESC'])['Sgf'] ?? null;
		if (!$previous)
			return;

		$transformation = self::transformationBetween($previous, $new);
		if (!$transformation || $transformation->isIdentity())
			return;
		$tree = SgfTree::parse($new['sgf'] ?? '');
		if (!$tree)
			return;
		self::transformComments((int) $new['tsumego_id'], $transformation, $tree->boardSize());
	}

	//The transformation from the coordinates of $from to the coordinates of $to, null when unknown.
	public static function transformationBetween(array $from, array $to): ?BoardTransformation
	{
		// what each editor ancestor of $to needs to become $to
		$toAncestors = self::ancestors($to);
		if (isset($toAncestors[$from['id']]))
			return $toAncestors[$from['id']];
		foreach (self::ancestors($from) as $id => $toFrom)
			if (isset($toAncestors[$id]))
				return $toFrom->inverse()->then($toAncestors[$id]);
		return self::setupStonesTransformation($from['sgf'] ?? '', $to['sgf'] ?? '');
	}

	//Follows source_sgf_id from the version back through the editor saves.
	/** @return array<int, BoardTransformation> sgf id => the transformation from it to $sgf */
	private static function ancestors(array $sgf): array
	{
		$result = [$sgf['id'] => BoardTransformation::identity()];
		$toSgf = BoardTransformation::identity();
		while (!empty($sgf['source_sgf_id']) && !isset($result[$sgf['source_sgf_id']]))
		{
			$toSgf = BoardTransformation::fromJson($sgf['transformation_from_source'])->then($toSgf);
			$result[$sgf['source_sgf_id']] = $toSgf;
			$sgf = ClassRegistry::init('Sgf')->findById($sgf['source_sgf_id'])['Sgf'] ?? null;
			if (!$sgf)
				break;
		}
		return $result;
	}

	//The only transformation turning the setup stones of one SGF into the other's, null when there is no single one.
	public static function setupStonesTransformation(string $fromSgf, string $toSgf): ?BoardTransformation
	{
		$fromTree = SgfTree::parse($fromSgf);
		$toTree = SgfTree::parse($toSgf);
		if (!$fromTree || !$toTree || $fromTree->boardSize() != $toTree->boardSize())
			return null;
		$size = $fromTree->boardSize();
		$fromStones = $fromTree->setupStones($size);
		$toStones = $toTree->setupStones($size);
		if (empty($fromStones) || count($fromStones) != count($toStones))
			return null;

		$matches = [];
		foreach (BoardTransformation::all() as $transformation)
		{
			$transformed = [];
			foreach ($fromStones as $key => $color)
			{
				[$x, $y] = array_map('intval', explode('/', $key));
				[$x, $y] = $transformation->applyToPoint($x, $y, $size);
				$transformed[$x . '/' . $y] = $transformation->invertColors ? ($color == 'B' ? 'W' : 'B') : $color;
			}
			if ($transformed == $toStones)
				$matches[] = $transformation;
		}
		// a symmetric position matches several ways and can not tell where the comments belong
		return count($matches) == 1 ? $matches[0] : null;
	}

	private static function transformComments(int $tsumegoID, BoardTransformation $transformation, int $size): void
	{
		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		foreach ($TsumegoComment->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]]) as $comment)
		{
			$comment = $comment['TsumegoComment'];
			$message = CommentCoordinates::transformTextBy($comment['message'], $transformation, $size);
			$position = CommentCoordinates::transformPosition($comment['position'], $transformation, $size);
			if ($message === $comment['message'] && $position === $comment['position'])
				continue;
			$TsumegoComment->save(['id' => $comment['id'], 'message' => $message, 'position' => $position]);
		}
	}
}
