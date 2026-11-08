import type { CommentCounts } from './commentTypes';

//What the comments of a problem hold that may need a look, like "3 comments, 1 open issue", as
//TsumegoIssueTable::commentSummary() writes it; closed issues are done, so they aren't mentioned.
export function commentSummary(counts: CommentCounts): string
{
	const plural = (count: number, word: string) => `${count} ${word}${count === 1 ? '' : 's'}`;
	const parts = [];
	if (counts.comments > 0)
		parts.push(plural(counts.comments, 'comment'));
	if (counts.openIssues > 0)
		parts.push(plural(counts.openIssues, 'open issue'));
	return parts.join(', ');
}

//Keeps the summary on the comments button of the play page (templates/Tsumegos/play.php) in step with
//the counts of the comment section: it appears when the comments hold something and goes when they
//hold nothing. The counts change only in a section the player can use, so a new summary shows.
export function updateMoreSummary(counts: CommentCounts): void
{
	const toggle = document.getElementById('commentsToggle');
	if (!toggle)
		return;
	const text = commentSummary(counts);
	let summary = document.getElementById('moreCommentSummary');
	if (text === '')
	{
		summary?.remove();
		return;
	}
	if (!summary)
	{
		summary = document.createElement('span');
		summary.id = 'moreCommentSummary';
		summary.className = 'play-more__summary';
		toggle.append(summary);
	}
	summary.textContent = text;
}
