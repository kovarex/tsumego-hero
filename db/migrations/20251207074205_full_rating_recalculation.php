<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Complete rating recalculation from scratch.
 *
 * WARNING: This migration recalculates ALL user and tsumego ratings from the beginning.
 * - Resets everyone to INITIAL_RATING (1000.0) + INITIAL_RD (300.0)
 * - Replays EVERY tsumego_attempt chronologically
 * - Execution time: potentially HOURS on production database
 * - User impact: ALL users will see rating changes
 *
 * Use this migration to:
 * - Guarantee complete consistency across all ratings
 * - Fix any historical rating bugs (not just Dec 6, 2025)
 * - Start with a clean slate mathematically
 *
 * This migration creates backup tables and supports rollback via down().
 */
final class FullRatingRecalculation extends AbstractMigration
{
	public function up(): void
	{
		// Initialize production code (loads constants from config/define.php)
		$this->initializeProductionCode();
		
		$this->getOutput()->writeln("\n" . str_repeat('=', 70));
		$this->getOutput()->writeln("FULL RATING RECALCULATION - WARNING");
		$this->getOutput()->writeln(str_repeat('=', 70));
		$this->getOutput()->writeln("This will recalculate ALL ratings from scratch.");
		$this->getOutput()->writeln("ALL users and tsumegos will be reset and recalculated.");
		$this->getOutput()->writeln(str_repeat('=', 70) . "\n");
		
		// Step 1: Create backup tables
		$this->createBackupTables();
		
		// Step 2: Reset all ratings to initial values
		$this->resetAllRatings();
		
		// Step 3: Replay all attempts chronologically
		$this->replayAllAttempts();
		
		// Step 4: Apply rating bounds
		$this->applyRatingBounds();
		
		$this->getOutput()->writeln("\n" . str_repeat('=', 70));
		$this->getOutput()->writeln("FULL RECALCULATION COMPLETE");
		$this->getOutput()->writeln(str_repeat('=', 70));
	}

	private function createBackupTables(): void
	{
		$this->getOutput()->writeln("\n=== Creating Backup Tables ===");
		
		// Drop existing backups (for repeatability during testing)
		$this->execute('DROP TABLE IF EXISTS user_backup_full_recalc');
		$this->execute('DROP TABLE IF EXISTS tsumego_backup_full_recalc');
		
		// Backup ALL user ratings
		$this->execute(
			"CREATE TABLE user_backup_full_recalc AS
			 SELECT id, name, rating, rd FROM user"
		);
		
		// Backup ALL tsumego ratings
		$this->execute(
			"CREATE TABLE tsumego_backup_full_recalc AS
			 SELECT id, rating, rd, minimum_rating, maximum_rating FROM tsumego"
		);
		
		$userCount = $this->fetchRow('SELECT COUNT(*) as cnt FROM user_backup_full_recalc')['cnt'];
		$tsumegoCount = $this->fetchRow('SELECT COUNT(*) as cnt FROM tsumego_backup_full_recalc')['cnt'];
		$this->getOutput()->writeln("Backed up $userCount user ratings and $tsumegoCount tsumego ratings");
	}

	private function resetAllRatings(): void
	{
		$this->getOutput()->writeln("\n=== Resetting All Ratings to Initial Values ===");
		
		// Reset all users to INITIAL_RATING and INITIAL_RD
		$this->execute(
			"UPDATE user 
			 SET rating = " . INITIAL_RATING . ", rd = " . INITIAL_RD
		);
		
		// Reset all tsumegos to INITIAL_RATING and INITIAL_RD
		$this->execute(
			"UPDATE tsumego 
			 SET rating = " . INITIAL_RATING . ", rd = " . INITIAL_RD
		);
		
		$this->getOutput()->writeln("Reset all users and tsumegos to " . INITIAL_RATING . " rating, " . INITIAL_RD . " RD");
	}

	private function replayAllAttempts(): void
	{
		$this->getOutput()->writeln("\n=== Replaying All Attempts Chronologically ===");
		
		// Get total count first (for progress reporting)
		$totalAttempts = (int) $this->fetchRow("SELECT COUNT(*) as cnt FROM tsumego_attempt")['cnt'];
		$this->getOutput()->writeln("Found $totalAttempts attempts to replay");
		$this->getOutput()->writeln("This will take a while...\n");
		
		// Process attempts in LARGER batches to reduce DB overhead
		$batchSize = 100000;  // Increased from 10k to 100k
		$offset = 0;
		$userRatings = [];
		$tsumegoRatings = [];
		
		while (true)
		{
			// Fetch batch of attempts, ordered chronologically
			$attempts = $this->fetchAll(
				"SELECT 
					ta.id,
					ta.user_id,
					ta.tsumego_id,
					ta.misplays,
					ta.solved,
					ta.created
				 FROM tsumego_attempt ta
				 ORDER BY ta.created ASC
				 LIMIT $batchSize OFFSET $offset"
			);
			
			// Break if no more attempts
			if (empty($attempts))
				break;
			
			// Process each attempt in this batch
			foreach ($attempts as $attempt)
			{
				$userId = (int) $attempt['user_id'];
				$tsumegoId = (int) $attempt['tsumego_id'];
				$misplays = (int) $attempt['misplays'];
				$solved = (bool) $attempt['solved'];
				
				// Get current ratings (load from DB if not in memory yet)
				if (!isset($userRatings[$userId]))
				{
					$user = $this->fetchRow("SELECT rating FROM user WHERE id = $userId");
					$userRatings[$userId] = $user ? (float) $user['rating'] : INITIAL_RATING;
				}
				if (!isset($tsumegoRatings[$tsumegoId]))
				{
					$tsumego = $this->fetchRow("SELECT rating FROM tsumego WHERE id = $tsumegoId");
					$tsumegoRatings[$tsumegoId] = $tsumego ? (float) $tsumego['rating'] : INITIAL_RATING;
				}
				
				$userRating = $userRatings[$userId];
				$tsumegoRating = $tsumegoRatings[$tsumegoId];
				
				// Apply each misplay - both ratings update together
				for ($i = 0; $i < $misplays; $i++)
				{
					$userDelta = $this->calculateUserRatingChange($userRating, $tsumegoRating, false);
					$tsumegoDelta = $this->calculateTsumegoRatingChange($tsumegoRating, $userRating, true);
					$userRating += $userDelta;
					$tsumegoRating += $tsumegoDelta;
				}
				
				// Apply solve if successful - both ratings update together
				if ($solved)
				{
					$userDelta = $this->calculateUserRatingChange($userRating, $tsumegoRating, true);
					$tsumegoDelta = $this->calculateTsumegoRatingChange($tsumegoRating, $userRating, false);
					$userRating += $userDelta;
					$tsumegoRating += $tsumegoDelta;
				}
				
				// Update in-memory ratings
				$userRatings[$userId] = $userRating;
				$tsumegoRatings[$tsumegoId] = $tsumegoRating;
			}
			
			// Save this batch to database using BULK SQL (major speedup!)
			$this->saveBatchRatingsBulk($userRatings, $tsumegoRatings);
			
			// Clear rating arrays to free memory (will reload from DB in next batch)
			$userRatings = [];
			$tsumegoRatings = [];
			
			// Progress report
			$offset += $batchSize;
			$processed = min($offset, $totalAttempts);
			$percent = round(($processed / $totalAttempts) * 100, 1);
			$this->getOutput()->writeln("Processed $processed / $totalAttempts attempts ($percent%)");
			
			// If we processed fewer attempts than batch size, we're done
			if (count($attempts) < $batchSize)
				break;
		}
		
		$this->getOutput()->writeln("\nReplayed all $totalAttempts attempts successfully");
	}

	private function saveBatchRatingsBulk(array $userRatings, array $tsumegoRatings): void
	{
		// Save user ratings using CASE statement for bulk update (much faster!)
		if (!empty($userRatings))
		{
			$userIds = array_keys($userRatings);
			$userIdList = implode(',', $userIds);
			
			$caseStatements = [];
			foreach ($userRatings as $userId => $rating)
			{
				$rating = round($rating, 2);
				$caseStatements[] = "WHEN $userId THEN $rating";
			}
			$caseSQL = implode(' ', $caseStatements);
			
			$this->execute("UPDATE user SET rating = CASE id $caseSQL END WHERE id IN ($userIdList)");
		}
		
		// Save tsumego ratings with per-tsumego bounds using CASE + LEAST/GREATEST (bulk operation)
		if (!empty($tsumegoRatings))
		{
			$tsumegoIds = array_keys($tsumegoRatings);
			$tsumegoIdList = implode(',', $tsumegoIds);
			
			$caseStatements = [];
			foreach ($tsumegoRatings as $tsumegoId => $rating)
			{
				$rating = round($rating, 2);
				$caseStatements[] = "WHEN $tsumegoId THEN $rating";
			}
			$caseSQL = implode(' ', $caseStatements);
			
			// Apply per-tsumego min/max bounds in SQL using LEAST/GREATEST
			$this->execute("
				UPDATE tsumego 
				SET rating = CASE 
					WHEN minimum_rating IS NOT NULL AND maximum_rating IS NOT NULL THEN LEAST(GREATEST(CASE id $caseSQL END, minimum_rating), maximum_rating)
					WHEN minimum_rating IS NOT NULL THEN GREATEST(CASE id $caseSQL END, minimum_rating)
					WHEN maximum_rating IS NOT NULL THEN LEAST(CASE id $caseSQL END, maximum_rating)
					ELSE CASE id $caseSQL END
				END
				WHERE id IN ($tsumegoIdList)
			");
		}
	}

	private function applyRatingBounds(): void
	{
		$this->getOutput()->writeln("\n=== Applying Rating Bounds ===");
		
		// User ratings: NO bounds (production behavior)
		$this->getOutput()->writeln("User ratings: No bounds applied (matches production Auth::saveUser())");
		
		// Tsumego ratings: Per-tsumego min/max already applied in saveBatchRatings()
		$this->getOutput()->writeln("Tsumego ratings: Per-tsumego min/max bounds already applied");
	}

	/**
	 * Load production code and constants
	 * - Loads INITIAL_RATING, INITIAL_RD from config/define.php
	 * - Loads Constants class with PLAYER/TSUMEGO_RATING_CALCULATION_MODIFIER
	 * - Loads Rating class for calculateRatingChange()
	 */
	private function initializeProductionCode(): void
	{
		// Define required constants for config/define.php
		if (!defined('DS'))
		{
			define('DS', DIRECTORY_SEPARATOR);
		}
		if (!defined('ROOT'))
		{
			define('ROOT', dirname(__DIR__, 2));
		}
		
		// Load config constants (INITIAL_RATING, INITIAL_RD, C, Q)
		require_once dirname(__DIR__) . '/../config/define.php';
		
		// Load production classes (Constants, Rating)
		require_once dirname(__DIR__) . '/../src/Utility/Rating.php';
		require_once dirname(__DIR__) . '/../src/Utility/Constants.php';
	}

	/**
	 * Calculates rating change using production Rating::calculateRatingChange
	 */
	private function calculateUserRatingChange(float $rating, float $opponentRating, bool $isWin): float
	{
		return \Rating::calculateRatingChange($rating, $opponentRating, $isWin ? 1 : 0, \Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
	}

	/**
	 * Calculates rating change for tsumego using production Rating::calculateRatingChange
	 * @param float $tsumegoRating Current tsumego rating
	 * @param float $userRating User's rating at start of attempt
	 * @param bool $isWin Whether tsumego won this interaction (misplay = true, solve = false)
	 */
	private function calculateTsumegoRatingChange(float $tsumegoRating, float $userRating, bool $isWin): float
	{
		return \Rating::calculateRatingChange($tsumegoRating, $userRating, $isWin ? 1 : 0, \Constants::$TSUMEGO_RATING_CALCULATION_MODIFIER);
	}

	public function down(): void
	{
		$this->getOutput()->writeln("\n=== Restoring Ratings from Backup ===");
		
		// Check if backup tables exist
		$userBackupExists = $this->fetchRow("SHOW TABLES LIKE 'user_backup_full_recalc'");
		$tsumegoBackupExists = $this->fetchRow("SHOW TABLES LIKE 'tsumego_backup_full_recalc'");
		
		if (!$userBackupExists && !$tsumegoBackupExists)
		{
			$this->getOutput()->writeln('ERROR: Backup tables not found! Cannot restore ratings.');
			$this->getOutput()->writeln('You will need to restore from a database backup.');
			return;
		}
		
		// Restore user ratings
		if ($userBackupExists)
		{
			$this->execute(
				"UPDATE user u 
				 JOIN user_backup_full_recalc b ON u.id = b.id
				 SET u.rating = b.rating, u.rd = b.rd"
			);
			$userCount = $this->fetchRow("SELECT COUNT(*) as cnt FROM user_backup_full_recalc")['cnt'];
			$this->getOutput()->writeln("Restored $userCount user ratings from backup");
		}
		
		// Restore tsumego ratings
		if ($tsumegoBackupExists)
		{
			$this->execute(
				"UPDATE tsumego t
				 JOIN tsumego_backup_full_recalc b ON t.id = b.id
				 SET t.rating = b.rating, t.rd = b.rd"
			);
			$tsumegoCount = $this->fetchRow("SELECT COUNT(*) as cnt FROM tsumego_backup_full_recalc")['cnt'];
			$this->getOutput()->writeln("Restored $tsumegoCount tsumego ratings from backup");
		}
		
		$this->getOutput()->writeln("\nRatings restored successfully!");
		$this->getOutput()->writeln("NOTE: Backup tables still exist. Drop them manually if you want.");
	}
}
