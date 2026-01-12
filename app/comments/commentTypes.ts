export interface Comment {
	id: number;
	text: string;
	user_id: number;
	user_name: string | null;
	user_picture: string | null;
	user_rating: number | null;
	user_external_id: string | null;
	isAdmin: boolean;
	created: string;
	position: string | null;
}

export interface CommentCounts {
	total: number;
	comments: number;
	issues: number;
	openIssues: number;
}

export interface CommentsData {
	issues: Issue[];
	standalone: Comment[];
	counts: CommentCounts;
}

export interface AddCommentRequest {
	text: string;
	tsumego_id: number;
	issue_id?: number;
	position?: string;
	report_as_issue?: boolean;
}

export interface CloseIssueRequest {
	message?: string;
	source?: string;
}
