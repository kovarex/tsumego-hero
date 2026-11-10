import { mountApp } from './shared/mountApp';
import { CommentSection } from './comments/CommentSection';
import { IssuesList } from './issues/IssuesList';
import { RecentAchievements } from './home/RecentAchievements';
import { TagEditor } from './tags/TagEditor';
import { Changelog } from './changelog/Changelog';
import { ClassroomProgress } from './classrooms/ClassroomProgress';
import { AssignmentEditor } from './classrooms/AssignmentEditor';
import { PrintSelection } from './print/PrintSelection';
import { Messages } from './messages/Messages';
import { LineChart } from './charts/LineChart';
import { BarChart } from './charts/BarChart';
import { LevelChart } from './charts/LevelChart';
import { syncMenuNewBadge } from './changelog/changelogCount';
import { queryClient } from './queryClient';
import { localizeTimeElements } from './shared/dates';

function initializeApp()
{
	localizeTimeElements();
	mountApp('[data-comments-root]', CommentSection);
	mountApp('[data-issues-root]', IssuesList);
	mountApp('[data-recent-achievements-root]', RecentAchievements);
	mountApp('[data-tag-editor-root]', TagEditor);
	mountApp('[data-changelog-root]', Changelog);
	mountApp('[data-classroom-progress-root]', ClassroomProgress);
	mountApp('[data-assignment-editor-root]', AssignmentEditor);
	mountApp('[data-print-selection-root]', PrintSelection);
	mountApp('[data-messages-root]', Messages);
	mountApp('[data-line-chart-root]', LineChart);
	mountApp('[data-bar-chart-root]', BarChart);
	mountApp('[data-level-chart-root]', LevelChart);

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
