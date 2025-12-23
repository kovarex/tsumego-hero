<?php

/**
 * Test case data structure for EditTsumegoTest
 *
 * Uses typed properties so PHPStan can understand optional fields
 * instead of sparse arrays that require ignores.
 */
readonly class EditTestCase
{
	public function __construct(
		public string $field,
		public string $value,
		public mixed $result,
		public ?string $field2 = null,
		public ?string $value2 = null,
		public bool $invalidTsumego = false,
		public ?bool $admin = null,
		public int $public = 1,
	) {}
}
