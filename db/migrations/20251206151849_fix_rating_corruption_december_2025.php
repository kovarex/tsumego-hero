<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fix user and tsumego ratings corrupted starting 2025-12-06 00:17:47.
 *
 * Rating corruption timeline:
 * - Started: 2025-12-06 00:17:47 UTC
 * - Ended: 2025-12-06 14:17:28 UTC (code fix deployed)
 *
 * The corruption caused rating changes to be applied incorrectly.
 * This affected BOTH user ratings AND tsumego ratings.
 *
 * Fix strategy:
 * - Users: Recalculate ratings for users who played during corruption window
 * - Tsumegos: Recalculate ratings for tsumegos that HAD attempts during corruption window
 *   (note: corrupted tsumego ratings affect ALL subsequent players, so we recalculate
 *    from corruption start through present day)
 *
 * Method: Find pre-corruption baseline, replay all attempts with correct formula.
 */
final class FixRatingCorruptionDecember2025 extends AbstractMigration
{
	private const CORRUPTION_START = '2025-12-06 00:17:47';

	// Rating calculation constants (from Constants.php)
	private const PLAYER_RATING_MODIFIER = 0.5;
	private const TSUMEGO_RATING_MODIFIER = 0.5;

	public function up(): void
	{
		// Fix user ratings
		$this->fixUserRatings();
		
		// Fix tsumego ratings
		$this->fixTsumegoRatings();
	}

	private function fixUserRatings(): void
	{
		$this->getOutput()->writeln("\n=== Fixing User Ratings ===");
		
		// Step 1: Find all users affected
		// Any user who had attempts from corruption start onwards is affected
		// (either directly by the bug, or indirectly through corrupted tsumego ratings)
		$affectedUsers = $this->fetchAll(
			"SELECT DISTINCT user_id FROM tsumego_attempt
			 WHERE created >= '" . self::CORRUPTION_START . "'"
		);

		$this->getOutput()->writeln('Found ' . count($affectedUsers) . ' affected users');

		$totalCorrected = 0;
		foreach ($affectedUsers as $userRow)
		{
			$userId = (int) $userRow['user_id'];
			if ($this->fixUserRating($userId))
				$totalCorrected++;
		}

		$this->getOutput()->writeln("Corrected ratings for $totalCorrected users");
	}

	private function fixTsumegoRatings(): void
	{
		$this->getOutput()->writeln("\n=== Fixing Tsumego Ratings ===");
		
		// Step 1: Find all tsumegos affected (had attempts during bug window)
		// NOTE: We don't use CORRUPTION_END here because corrupted tsumego ratings
		// affect ALL subsequent players, not just those during the corruption window
		$affectedTsumegos = $this->fetchAll(
			"SELECT DISTINCT tsumego_id FROM tsumego_attempt
			 WHERE created >= '" . self::CORRUPTION_START . "'"
		);

		$this->getOutput()->writeln('Found ' . count($affectedTsumegos) . ' affected tsumegos');

		$totalCorrected = 0;
		foreach ($affectedTsumegos as $tsumegoRow)
		{
			$tsumegoId = (int) $tsumegoRow['tsumego_id'];
			if ($this->fixTsumegoRating($tsumegoId))
				$totalCorrected++;
		}

		$this->getOutput()->writeln("Corrected ratings for $totalCorrected tsumegos\n");
	}

	private function fixUserRating(int $userId): bool
	{
		// Get user's current data
		$user = $this->fetchRow("SELECT id, name, rating FROM user WHERE id = $userId");
		if (!$user)
			return false;

		$currentDbRating = (float) $user['rating'];

		// Find user's rating just BEFORE the bug window from their last pre-bug attempt
		// We use the 'elo' field which records user's rating at start of that attempt
		$lastAttemptBeforeBug = $this->fetchRow(
			"SELECT elo, tsumego_elo, solved, misplays FROM tsumego_attempt
			 WHERE user_id = $userId
			   AND created < '" . self::CORRUPTION_START . "'
			 ORDER BY created DESC
			 LIMIT 1"
		);

		if (!$lastAttemptBeforeBug)
		{
			// User had no attempts before bug - check their first bugged attempt
			$firstBuggedAttempt = $this->fetchRow(
				"SELECT elo FROM tsumego_attempt
				 WHERE user_id = $userId
				   AND created >= '" . self::CORRUPTION_START . "'
				 ORDER BY created ASC
				 LIMIT 1"
			);

			if (!$firstBuggedAttempt)
				return false;

			// Use the elo at start of first bugged attempt as baseline
			$startRating = (float) $firstBuggedAttempt['elo'];
		}
		else
		{
			// Calculate what their rating should have been AFTER the last pre-bug attempt
			// The 'elo' field is rating at START of attempt, so we need to apply that attempt's result
			$startRating = (float) $lastAttemptBeforeBug['elo'];
			$tsumegoRating = (float) $lastAttemptBeforeBug['tsumego_elo'];
			$misplays = (int) $lastAttemptBeforeBug['misplays'];
			$solved = (bool) $lastAttemptBeforeBug['solved'];

			// Apply last pre-bug attempt to get post-attempt rating
			for ($i = 0; $i < $misplays; $i++)
				$startRating += $this->calculateRatingChange($startRating, $tsumegoRating, false);
			if ($solved)
				$startRating += $this->calculateRatingChange($startRating, $tsumegoRating, true);
		}

		// Get all attempts from bug start onwards
		$allAttempts = $this->fetchAll(
			"SELECT tsumego_elo, solved, misplays, created
			 FROM tsumego_attempt
			 WHERE user_id = $userId
			   AND created >= '" . self::CORRUPTION_START . "'
			 ORDER BY created ASC"
		);

		$userRating = $startRating;

		foreach ($allAttempts as $attempt)
		{
			$tsumegoRating = (float) $attempt['tsumego_elo'];

			// Skip attempts with invalid tsumego rating data
			if ($tsumegoRating <= 0)
				continue;

			$misplays = (int) $attempt['misplays'];
			$solved = (bool) $attempt['solved'];

			// Apply rating changes correctly (single application per step)
			for ($i = 0; $i < $misplays; $i++)
				$userRating += $this->calculateRatingChange($userRating, $tsumegoRating, false);

			if ($solved)
				$userRating += $this->calculateRatingChange($userRating, $tsumegoRating, true);
		}

		// Round to reasonable precision
		$newRating = round($userRating, 2);
		$correction = $newRating - $currentDbRating;

		// Update if there's any difference (even small corrections matter for rating integrity)
		if ($correction != 0)
		{
			$this->getOutput()->writeln(
				sprintf(
					'User %d (%s): %.2f -> %.2f (correction: %+.2f)',
					$userId,
					$user['name'],
					$currentDbRating,
					$newRating,
					$correction
				)
			);

			$this->execute("UPDATE user SET rating = $newRating WHERE id = $userId");
			return true;
		}

		return false;
	}

	private function fixTsumegoRating(int $tsumegoId): bool
	{
		// Get tsumego's current data
		$tsumego = $this->fetchRow("SELECT id, rating, minimum_rating, maximum_rating FROM tsumego WHERE id = $tsumegoId");
		if (!$tsumego)
			return false;

		$currentDbRating = (float) $tsumego['rating'];
		$minRating = $tsumego['minimum_rating'] ? (float) $tsumego['minimum_rating'] : null;
		$maxRating = $tsumego['maximum_rating'] ? (float) $tsumego['maximum_rating'] : null;

		// Find last attempt before bug to get pre-bug rating
		$lastAttemptBeforeBug = $this->fetchRow(
			"SELECT tsumego_elo FROM tsumego_attempt
			 WHERE tsumego_id = $tsumegoId
			   AND created < '" . self::CORRUPTION_START . "'
			 ORDER BY created DESC
			 LIMIT 1"
		);

		if (!$lastAttemptBeforeBug)
		{
			// No pre-bug attempts, use first bugged attempt's tsumego_elo as baseline
			$firstBuggedAttempt = $this->fetchRow(
				"SELECT tsumego_elo FROM tsumego_attempt
				 WHERE tsumego_id = $tsumegoId
				   AND created >= '" . self::CORRUPTION_START . "'
				 ORDER BY created ASC
				 LIMIT 1"
			);

			if (!$firstBuggedAttempt)
				return false;

			$startRating = (float) $firstBuggedAttempt['tsumego_elo'];
		}
		else
		{
			// Use the recorded rating from last pre-corruption attempt
			$startRating = (float) $lastAttemptBeforeBug['tsumego_elo'];
		}

		// Get ALL attempts from corruption start onwards
		// (corrupted tsumego rating affects all subsequent players, not just during corruption window)
		$attemptsToReplay = $this->fetchAll(
			"SELECT elo as user_rating, tsumego_elo, solved, misplays, created
			 FROM tsumego_attempt
			 WHERE tsumego_id = $tsumegoId
			   AND created >= '" . self::CORRUPTION_START . "'
			 ORDER BY created ASC"
		);

		$tsumegoRating = $startRating;

		foreach ($attemptsToReplay as $attempt)
		{
			// For each attempt, we need to replay the rating calculation
			// The user's rating at start of attempt is recorded in 'elo'
			$userRating = (float) $attempt['user_rating'];
			if ($userRating <= 0)
				continue;

			$misplays = (int) $attempt['misplays'];
			$solved = (bool) $attempt['solved'];

			// Apply rating changes correctly (single application per step)
			// For misplays, tsumego "wins" (user loses)
			for ($i = 0; $i < $misplays; $i++)
			{
				$change = $this->calculateTsumegoRatingChange($tsumegoRating, $userRating, true);
				$tsumegoRating += $change;
			}

			// For solve, tsumego "loses" (user wins)
			if ($solved)
			{
				$change = $this->calculateTsumegoRatingChange($tsumegoRating, $userRating, false);
				$tsumegoRating += $change;
			}
		}

		// Clamp to min/max if set
		if ($minRating !== null && $tsumegoRating < $minRating)
			$tsumegoRating = $minRating;
		if ($maxRating !== null && $tsumegoRating > $maxRating)
			$tsumegoRating = $maxRating;

		// Round to reasonable precision
		$newRating = round($tsumegoRating, 2);
		$correction = $newRating - $currentDbRating;

		// Update if there's any difference
		if ($correction != 0)
		{
			$this->getOutput()->writeln(
				sprintf(
					'Tsumego %d: %.2f -> %.2f (correction: %+.2f, %d attempts replayed)',
					$tsumegoId,
					$currentDbRating,
					$newRating,
					$correction,
					count($attemptsToReplay)
				)
			);

			$this->execute("UPDATE tsumego SET rating = $newRating WHERE id = $tsumegoId");
			return true;
		}

		return false;
	}

	/**
	 * Calculates rating change using the same formula as Rating::calculateRatingChange
	 */
	private function calculateRatingChange(float $rating, float $opponentRating, bool $isWin): float
	{
		$result = $isWin ? 1 : 0;
		$modifier = self::PLAYER_RATING_MODIFIER;

		$Se = 1.0 / (1.0 + exp($this->beta($opponentRating) - $this->beta($rating)));
		$con = pow(((3300 - $rating) / 200), 1.6);
		$bonus = log(1 + exp((2300 - $rating) / 80)) / 5;

		return $modifier * ($con * ($result - $Se) + $bonus);
	}

	/**
	 * Calculates rating change for tsumego using the same formula
	 * @param float $tsumegoRating Current tsumego rating
	 * @param float $userRating User's rating at start of attempt
	 * @param bool $isWin Whether tsumego won this interaction (misplay = true, solve = false)
	 */
	private function calculateTsumegoRatingChange(float $tsumegoRating, float $userRating, bool $isWin): float
	{
		$result = $isWin ? 1 : 0;
		$modifier = self::TSUMEGO_RATING_MODIFIER;

		$Se = 1.0 / (1.0 + exp($this->beta($userRating) - $this->beta($tsumegoRating)));
		$con = pow(((3300 - $tsumegoRating) / 200), 1.6);
		$bonus = log(1 + exp((2300 - $tsumegoRating) / 80)) / 5;

		return $modifier * ($con * ($result - $Se) + $bonus);
	}

	private function beta(float $rating): float
	{
		return -7 * log(3300 - $rating);
	}

	public function down(): void
	{
		// This migration cannot be safely reversed automatically
		// Rating recalculation would need to be done manually if needed
		$this->getOutput()->writeln('Warning: This migration cannot be automatically reversed.');
		$this->getOutput()->writeln('If you need to undo this, you would need to restore from backup.');
	}
}

