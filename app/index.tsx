import { mountApp } from './shared/mountApp';
import { CommentSection } from './comments/CommentSection';
import { IssuesList } from './issues/IssuesList';
import { RecentAchievements } from './home/RecentAchievements';
import { TagEditor } from './tags/TagEditor';
import { queryClient } from './queryClient';

function initializeApp()
{
	mountApp('[data-comments-root]', CommentSection);
	mountApp('[data-issues-root]', IssuesList);
	mountApp('[data-recent-achievements-root]', RecentAchievements);
	mountApp('[data-tag-editor-root]', TagEditor);

	// Expose React Query invalidation for Selenium testing
	(window as any).__invalidateComments = () =>
		queryClient.invalidateQueries({ queryKey: ['comments'] });
}

// Wait for DOM to be fully loaded, then initialize
if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', initializeApp);
else
	initializeApp();
