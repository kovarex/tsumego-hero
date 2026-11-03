import { useEffect } from 'react';
import Sortable from 'sortablejs';
import type { Issue } from '../issues/issueTypes';

interface UseSortableDnDProps
{
	containerRef: React.RefObject<HTMLDivElement | null>;
	isAdmin: boolean;
	tsumegoId: number;
	issues: Issue[];
	onMoveComment: (commentId: number, targetIssueId: number | 'standalone') => Promise<void>;
}

interface DragState
{
	commentId: number;
	//The issue the comment is dragged out of, null for a standalone comment
	sourceIssueId: number | null;
}

//What the sortables of the standalone comments and of the comments of each issue share.
const SHARED_OPTIONS: Sortable.Options = {
	sort: false,
	scroll: true,
	scrollSensitivity: 80,
	scrollSpeed: 15,
	bubbleScroll: true,
	handle: '.tsumego-comment__drag-handle',
	ghostClass: 'tsumego-comment--ghost',
	chosenClass: 'tsumego-comment--dragging'
};

const OVERLAY_TEXT = 'Drop here to add to this issue';
const OVERLAY_RETURN_TEXT = 'Drop to return comment';

//The comment of a dragged element: the element itself, or the comment inside a standalone wrapper.
function commentIdOf(element: HTMLElement): number
{
	const comment = element.querySelector<HTMLElement>('.tsumego-comment') ?? element;
	return parseInt(comment.dataset.commentId || '0');
}

//Drag and drop of the comments for an admin, with SortableJS: a standalone comment dropped on an
//issue goes into it, a comment of an issue dropped on another issue moves there, and one dropped
//on the standalone area leaves its issue. While a comment is dragged, every issue shows an overlay
//to drop it on; the issue it came from says it takes the comment back. Escape cancels the drag.
export function useSortableDnD({ containerRef, isAdmin, tsumegoId, issues, onMoveComment }: UseSortableDnDProps)
{
	useEffect(() =>
	{
		const container = containerRef.current;
		if (!isAdmin || !container)
			return;

		// every listener of this run goes with it, so a run after the issues changed doesn't add a second one
		const listeners = new AbortController();
		const { signal } = listeners;
		const sortables: Sortable[] = [];
		let dragState: DragState | null = null;

		const overlays = () => container.querySelectorAll<HTMLElement>('.tsumego-issue__drop-overlay');

		const startDrag = (state: DragState) =>
		{
			dragState = state;
			overlays().forEach(overlay =>
			{
				const isSource = parseInt(overlay.dataset.issueId || '0') === state.sourceIssueId;
				overlay.style.display = 'flex';
				overlay.classList.toggle('tsumego-issue__drop-overlay--source', isSource);
				overlay.querySelector('span')!.textContent = isSource ? OVERLAY_RETURN_TEXT : OVERLAY_TEXT;
			});
		};

		const endDrag = () =>
		{
			dragState = null;
			overlays().forEach(overlay =>
			{
				overlay.style.display = 'none';
				overlay.classList.remove('tsumego-issue__drop-overlay--source', 'tsumego-issue__drop-overlay--active');
			});
			container.querySelectorAll('.tsumego-issue--drag-source').forEach(issue => issue.classList.remove('tsumego-issue--drag-source'));
		};

		//The overlay an issue shows to drop a comment on; it stays in the issue between runs, its listeners don't.
		const addDropOverlay = (issueElement: HTMLElement, issueId: number) =>
		{
			let overlay = issueElement.querySelector<HTMLElement>(':scope > .tsumego-issue__drop-overlay');
			if (!overlay)
			{
				overlay = document.createElement('div');
				overlay.className = 'tsumego-issue__drop-overlay';
				overlay.dataset.issueId = String(issueId);
				overlay.style.display = 'none';
				overlay.append(document.createElement('span'));
				overlay.querySelector('span')!.textContent = OVERLAY_TEXT;
				issueElement.appendChild(overlay);
			}

			const active = overlay;
			active.addEventListener('dragover', event =>
			{
				event.preventDefault();
				active.classList.add('tsumego-issue__drop-overlay--active');
			}, { signal });
			active.addEventListener('dragleave', () => active.classList.remove('tsumego-issue__drop-overlay--active'), { signal });
			active.addEventListener('drop', async event =>
			{
				event.preventDefault();
				event.stopPropagation();
				active.classList.remove('tsumego-issue__drop-overlay--active');
				// back on the issue it came from, SortableJS puts it back on its own
				if (!dragState || dragState.sourceIssueId === issueId)
					return;
				await onMoveComment(dragState.commentId, issueId);
			}, { signal });
		};

		sortables.push(new Sortable(container, {
			...SHARED_OPTIONS,
			group: { name: 'comments', pull: true, put: true },
			animation: 150,
			draggable: '.tsumego-comment--standalone',
			filter: '.tsumego-issue',
			onStart: event => startDrag({ commentId: commentIdOf(event.item), sourceIssueId: null }),
			onEnd: endDrag,
			onAdd: async event =>
			{
				// SortableJS moved the element, React owns it: it goes back, the refetch draws the move
				if (event.from && event.item.parentNode !== event.from)
					event.from.appendChild(event.item);

				// SortableJS routes both 'drop' and 'dragend' through _onDrop, so onAdd fires for a
				// cancelled drag too (Escape, or a drop on the overlay of the source issue, which stops
				// the 'drop' from reaching SortableJS); only a 'drop' is a drop on the standalone area.
				// @types/sortablejs omits originalEvent, but SortableJS sets it.
				const originalEvent = (event as Sortable.SortableEvent & { originalEvent?: Event }).originalEvent;
				if (originalEvent?.type === 'dragend')
					return;
				await onMoveComment(commentIdOf(event.item), 'standalone');
			}
		}));

		container.querySelectorAll<HTMLElement>('.tsumego-dnd__issue-dropzone').forEach(dropzone =>
		{
			const issueId = parseInt(dropzone.dataset.issueId || '0');
			const issueElement = dropzone.closest<HTMLElement>('.tsumego-issue')!;
			sortables.push(new Sortable(dropzone, {
				...SHARED_OPTIONS,
				group: {
					name: `issue-${issueId}`,
					pull: true,
					// A comment can come back into its issue, so a cancelled drag isn't committed to the
					// standalone area (which would move it out and delete the issue): straight back from
					// this issue, or back after passing over the standalone area when it started here.
					put: (_to: Sortable, from: Sortable) =>
						from.el === dropzone || (from.el === container && dragState?.sourceIssueId === issueId)
				},
				animation: 0,
				draggable: '.tsumego-comment',
				onStart: event =>
				{
					issueElement.classList.add('tsumego-issue--drag-source');
					startDrag({ commentId: commentIdOf(event.item), sourceIssueId: issueId });
				},
				// back in its issue: SortableJS and React have it at the same place, nothing to save
				onAdd: () => {},
				onEnd: endDrag
			}));
			addDropOverlay(issueElement, issueId);
		});

		document.addEventListener('keydown', event =>
		{
			if (event.key === 'Escape' && dragState)
				endDrag();
		}, { signal });
		document.addEventListener('dragend', endDrag, { signal });

		return () =>
		{
			sortables.forEach(sortable => sortable.destroy());
			listeners.abort();
			endDrag();
		};
	}, [isAdmin, tsumegoId, issues.length, onMoveComment, containerRef]);
}
