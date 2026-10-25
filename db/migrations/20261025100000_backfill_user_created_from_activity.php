<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The profile shows "Member since" from user.created, and for 13 992 accounts that column holds
 * a later date than the oldest thing the player did: the first problem they solved, the first
 * comment they wrote, the first day they trained. The worst are 7 years off, the oldest accounts
 * among them, which say 2025 while their attempts, statuses and day records go back to 2018.
 *
 * Nothing needs to be invented to fix that. Every table that records something a player did is
 * evidence that the account existed that day, so the earliest row across all of them is the
 * earliest day the player can be shown as a member. Those are the attempts, the problem statuses
 * (their `updated` is the last touch of a problem, never earlier than the first), the days of the
 * daily training, comments, time mode sessions, earned achievements, contributions with their
 * queries and proposals, reported issues, deleted progress, rejected SGF proposals, the old
 * activation mails, uploaded SGFs, tag connections, admin activity and the legacy user.reward
 * stamp. A source that has no row for a player simply does not vote.
 *
 * The date only ever moves back: an account whose created is already the earliest is untouched,
 * so an account made today keeps the moment it was made. An account with an empty date takes its
 * earliest activity. Accounts without a single row anywhere keep what they have, and deleted
 * accounts are left out.
 *
 * created then means "the day we can prove this player was here", which is what the profile
 * claims. On the data this runs against it moves 13 992 accounts, 3 739 of them by more than a
 * year and the farthest by 2 674 days.
 *
 * IRREVERSIBLE: the date that is overwritten is not stored anywhere else, so a rollback cannot
 * bring it back. Rolling back restores nothing rather than an older value.
 */
final class BackfillUserCreatedFromActivity extends AbstractMigration
{
	/**
	 * Every table that records something a player did, each as the select of a user id and the
	 * moment of that row. Anything added here must be evidence that the account existed, never a
	 * date the account could have been given before it was made.
	 */
	private const ACTIVITY = [
		'SELECT user_id, created AS at FROM tsumego_attempt WHERE user_id IS NOT NULL',
		'SELECT user_id, updated AS at FROM tsumego_status WHERE user_id IS NOT NULL',
		'SELECT user_id, date AS at FROM day_record WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM tsumego_comment WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM time_mode_session WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM achievement_status WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM user_contribution WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM tsumego_issue WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM progress_deletion WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM reject WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM activate WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM sgf WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM tag_connection WHERE user_id IS NOT NULL',
		'SELECT user_id, created AS at FROM admin_activity WHERE user_id IS NOT NULL',
		'SELECT id AS user_id, reward AS at FROM user WHERE reward IS NOT NULL',
	];

	/**
	 * The statement that moves every account's created back to its earliest trace of activity.
	 */
	public static function firstActivitySql(): string
	{
		return 'UPDATE user u'
			. ' JOIN (SELECT user_id, MIN(at) AS first_seen FROM (' . implode(' UNION ALL ', self::ACTIVITY) . ') activity'
			. ' GROUP BY user_id) f ON f.user_id = u.id'
			. ' SET u.created = f.first_seen'
			. ' WHERE u.deleted IS NULL AND (u.created IS NULL OR DATE(f.first_seen) < DATE(u.created))';
	}

	public function up(): void
	{
		$this->execute(self::firstActivitySql());
	}

	/**
	 * Nothing to undo: the overwritten date is gone.
	 */
	public function down(): void
	{
	}
}
