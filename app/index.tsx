import { mountApp, unmountApps } from './shared/mountApp';
import { CommentSection } from './comments/CommentSection';
import { IssuesList } from './issues/IssuesList';
import { RecentAchievements } from './home/RecentAchievements';
import { TagEditor } from './tags/TagEditor';
import { Changelog } from './changelog/Changelog';
import { ClassroomProgress } from './classrooms/ClassroomProgress';
import { AssignmentEditor } from './classrooms/AssignmentEditor';
import { Messages } from './messages/Messages';
import { LineChart } from './charts/LineChart';
import { BarChart } from './charts/BarChart';
import { syncMenuNewBadge } from './changelog/changelogCount';
import { queryClient } from './queryClient';
import { localizeTimeElements } from './shared/dates';

function mountApps(container: ParentNode = document)
{
	mountApp('[data-comments-root]', CommentSection, container);
	mountApp('[data-issues-root]', IssuesList, container);
	mountApp('[data-recent-achievements-root]', RecentAchievements, container);
	mountApp('[data-tag-editor-root]', TagEditor, container);
	mountApp('[data-changelog-root]', Changelog, container);
	mountApp('[data-classroom-progress-root]', ClassroomProgress, container);
	mountApp('[data-assignment-editor-root]', AssignmentEditor, container);
	mountApp('[data-messages-root]', Messages, container);
	mountApp('[data-line-chart-root]', LineChart, container);
	mountApp('[data-bar-chart-root]', BarChart, container);
}

function initializeApp()
{
	localizeTimeElements();
	mountApps();

	// For the play page, which replaces its content with the next problem's (webroot/js/PlayNavigation.js)
	(window as unknown as { mountApps?: typeof mountApps }).mountApps = mountApps;
	(window as unknown as { unmountApps?: typeof unmountApps }).unmountApps = unmountApps;

	syncMenuNewBadge();

	// For the plain scripts (webroot/js) to format the dates they put on the page, with the same formatter
	(window as unknown as { localizeTimeElements?: typeof localizeTimeElements }).localizeTimeElements = localizeTimeElements;

	// Expose React Query invalidation for Selenium testing
	(window as unknown as { __invalidateComments?: () => void }).__invalidateComments = () =>
		queryClient.invalidateQueries({ queryKey: ['comments'] });
}

// Wait for DOM to be fully loaded, then initialize
if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', initializeApp);
else
	initializeApp();
