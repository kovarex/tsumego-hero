/**
 * Types for Issues List page.
 * 
 * Extends types from comments.ts since issues contain comments.
 */

import type { Issue, Comment } from '../comments/commentTypes';

/**
 * Issue with context for global issues list display.
 * 
 * Contains the issue itself plus metadata about which problem it belongs to.
 */
export interface IssueWithContext {
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
	TsumegoNum: number | null;  // Problem number in set (e.g., #5)
	Set: {
		id: number;
		title: string;
	} | null;
}

/**
 * Response from GET /tsumego-issues/index
 */
export interface IssuesListResponse {
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
