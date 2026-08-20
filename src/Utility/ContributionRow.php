<?php

/**
 * A single row in a user's contributions timeline.
 */
final readonly class ContributionRow
{
	public function __construct(
		public string $type,
		public string $status,
		public string $created,
		public string $tagId = '',
		public string $tag = '',
		public string $tsumegoId = '',
		public string $tsumegoLabel = '',
	) {}

	public static function fromQueryRow(array $row): self
	{
		return new self(
			type: $row['type'] ?? '',
			status: $row['status'] ?? '',
			created: $row['created'] ?? '',
			tagId: $row['tag_id'] ?? '',
			tag: $row['tag'] ?? '',
			tsumegoId: $row['tsumego_id'] ?? '',
			tsumegoLabel: $row['tsumego_label'] ?? '',
		);
	}
}
