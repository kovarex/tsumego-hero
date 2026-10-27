import type { MouseEvent } from 'react';
import type { PickableProblem } from './classroomTypes';

interface ProblemButtonProps
{
	problem: PickableProblem;
	picked: boolean;
	onToggle: (problem: PickableProblem) => void;
}

//A click with a modifier key opens the link the way the browser does, in a new tab or window.
function opensElsewhere(event: MouseEvent): boolean
{
	return event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0;
}

//A problem button like the ones of a collection page (TsumegoButton::render()): the number, and
//the board in the tooltip, drawn by previewBoard.js on hover. A click picks the problem or takes
//it out; a click with Ctrl or the middle button opens the problem in a new tab to look at it.
export function ProblemButton({ problem, picked, onToggle }: ProblemButtonProps)
{
	return (
		<li className={'problem-nav__item ' + (picked ? 'classes__problem--picked' : 'problem-nav__item--N')}>
			<a
				className="tooltip"
				href={`/${problem.setConnectionId}`}
				target="_blank"
				rel="noopener"
				aria-pressed={picked}
				data-tsumego-id={problem.tsumegoId}
				data-sgf-preview={problem.preview ? JSON.stringify(problem.preview) : undefined}
				onClick={event =>
				{
					if (opensElsewhere(event))
						return;
					event.preventDefault();
					onToggle(problem);
				}}
			>
				<div className="problem-nav__number">{problem.number}</div>
				<span className="tooltip-box">
					<div className={'tooltip-label ' + (picked ? 'statusS' : 'statusN')}>{picked ? 'Picked' : 'Not picked'}</div>
					<div className="tooltip-desc">
						{picked ? 'Click to take it out.' : 'Click to pick it.'} Ctrl+click opens it in a new tab.
					</div>
				</span>
			</a>
		</li>
	);
}
