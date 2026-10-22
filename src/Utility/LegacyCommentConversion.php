<?php

namespace App\Utility;

/** The outcome of LegacyCommentConverter for one comment, with what the choices were based on. */
class LegacyCommentConversion
{
	public string $originalMessage = '';
	public ?string $originalPosition = null;
	public string $message = '';
	public ?string $position = null;

	/** @var string[] the coordinates found in the text, as written */
	public array $labels = [];
	/** 'no coordinates', 'unique', 'tie, decided by the anchor' or 'tie, decided by the top-left orientation' */
	public string $textMethod = '';
	/** from the author's view to the SGF */
	public ?BoardTransformation $textTransformation = null;
	/** @var int[] coordinates on moves, on setup stones and near the stones for the chosen orientation */
	public array $textScore = [];
	/** how many other orientations scored the same but put the coordinates elsewhere */
	public int $textAlternatives = 0;
	/** @var array{same: int, swapped: int} */
	public array $colorEvidence = ['same' => 0, 'swapped' => 0];
	public bool $colorsSwapped = false;

	/** 'none', 'exact', 'found in another orientation' or 'moves not in the tree' */
	public string $positionMethod = '';
	/** from the author's view to the SGF, when the anchor was found in the tree */
	public ?BoardTransformation $anchorTransformation = null;

	public function changed(): bool
	{
		return $this->message !== $this->originalMessage || $this->position !== $this->originalPosition;
	}
}
