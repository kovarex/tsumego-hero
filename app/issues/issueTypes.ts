/**
 * Types for Issues.
 */

import type { Comment } from '../comments/commentTypes';

/**
 * Issue status IDs from database (tsumego_issue_status table).
 *
 * Maps to: 1 = open, 2 = closed
 */
export const IssueStatus = {
	OPEN: 1,
	CLOSED: 2
} as const;

export type IssueStatusId = (typeof IssueStatus)[keyof typeof IssueStatus];

/**
 * Issue entity from database.
 */
export interface Issue
{
	id: number;
	tsumego_issue_status_id: IssueStatusId;
	created: string;
	user_id: number;
	user_display_name: string | null;
	user_avatar_url: string | null;
	user_rating: number | null;
	isAdmin: boolean;
	comments: Comment[];
}

/**
 * Issue with context for global issues list display.
 *
 * Contains the issue itself plus metadata about which problem it belongs to.
 */
export interface IssueWithContext
{
	// The issue itself
	issue: Issue;

	// Comments belonging to this issue
	comments: Comment[];

	// Issue author (simplified - just name for display)
	author: {
		name: string;
	};

	// Problem context
	tsumegoId: number;
	TsumegoNum: number | null; // Problem number in set (e.g., #5)
	Set: {
		id: number;
		title: string;
	} | null;
}

/**
 * Response from GET /tsumego-issues/index
 */
export interface IssuesListResponse
{
	issues: IssueWithContext[];
	counts: {
		open: number;
		closed: number;
	};
	totalPages: number;
	currentPage: number;
}

/**
 * Filter options for issues list
 */
export type IssueStatusFilter = 'opened' | 'closed' | 'all';
