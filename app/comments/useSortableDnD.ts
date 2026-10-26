import { useEffect, useRef } from 'react';
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

/**
 * Custom hook to manage SortableJS drag-and-drop for comments and issues.
 *
 * Handles:
 * - Main content area sortable (standalone comments)
 * - Issue dropzone sortables (comments within issues)
 * - Drop overlay creation and event handling
 * - ESC key to cancel drag
 * - Cleanup on unmount
 */
export function useSortableDnD({ containerRef, isAdmin, tsumegoId, issues, onMoveComment }: UseSortableDnDProps)
{
	const sortablesRef = useRef<Sortable[]>([]);
	const dragStateRef = useRef<{ commentId: number; sourceIssueId: number | null } | null>(null);

	useEffect(() =>
	{
		if (!isAdmin || !containerRef.current) 
			return;

		// Cleanup previous sortables
		sortablesRef.current.forEach(s => s.destroy());
		sortablesRef.current = [];

		// Show/hide drop overlays on all issues
		const showIssueDropTargets = (show: boolean, sourceIssueId: number | null) =>
		{
			document.querySelectorAll('.tsumego-issue').forEach((issueEl: Element) =>
			{
				const htmlIssueEl = issueEl as HTMLElement;
				const issueId = parseInt(htmlIssueEl.dataset.issueId || '0');
				const overlay = issueEl.querySelector('.tsumego-issue__drop-overlay') as HTMLElement;
				if (overlay)
					if (show)
					{
						overlay.style.display = 'flex';
						const span = overlay.querySelector('span');
						if (issueId === sourceIssueId)
						{
							overlay.classList.add('tsumego-issue__drop-overlay--source');
							if (span) 
								span.textContent = 'Drop to return comment';
						}
						else
						{
							overlay.classList.remove('tsumego-issue__drop-overlay--source');
							if (span) 
								span.textContent = 'Drop here to add to this issue';
						}
					}
					else
					{
						overlay.style.display = 'none';
						overlay.classList.remove('tsumego-issue__drop-overlay--source');
					}
			});
		};

		// Setup drop overlay on issue element
		const setupIssueDropTarget = (issueEl: HTMLElement, issueId: number) =>
		{
			let overlay = issueEl.querySelector('.tsumego-issue__drop-overlay') as HTMLElement;
			if (!overlay)
			{
				overlay = document.createElement('div');
				overlay.className = 'tsumego-issue__drop-overlay';
				overlay.innerHTML = '<span>Drop here to add to this issue</span>';
				overlay.style.display = 'none';
				issueEl.appendChild(overlay);
			}

			const handleDragOver = (e: DragEvent) =>
			{
				e.preventDefault();
				overlay.classList.add('tsumego-issue__drop-overlay--active');
			};

			const handleDragLeave = () =>
			{
				overlay.classList.remove('tsumego-issue__drop-overlay--active');
			};

			const handleDrop = async (e: DragEvent) =>
			{
				e.preventDefault();
				e.stopPropagation();
				overlay.classList.remove('tsumego-issue__drop-overlay--active');

				if (!dragStateRef.current) 
					return;

				const { commentId, sourceIssueId } = dragStateRef.current;

				// Check if returning to source issue
				if (sourceIssueId === issueId)
					return; // Do nothing - SortableJS handles returning to source

				await onMoveComment(commentId, issueId);
			};

			// Remove old listeners before adding new ones
			overlay.removeEventListener('dragover', handleDragOver);
			overlay.removeEventListener('dragleave', handleDragLeave);
			overlay.removeEventListener('drop', handleDrop);

			overlay.addEventListener('dragover', handleDragOver);
			overlay.addEventListener('dragleave', handleDragLeave);
			overlay.addEventListener('drop', handleDrop);
		};

		// Create main sortable for standalone comments
		const mainSortable = new Sortable(containerRef.current, {
			group: { name: 'comments', pull: true, put: true },
			sort: false,
			animation: 150,
			scroll: true,
			scrollSensitivity: 80,
			scrollSpeed: 15,
			bubbleScroll: true,
			handle: '.tsumego-comment__drag-handle',
			draggable: '.tsumego-comment--standalone',
			ghostClass: 'tsumego-comment--ghost',
			chosenClass: 'tsumego-comment--dragging',
			filter: '.tsumego-issue',
			onStart: (evt: Sortable.SortableEvent) =>
			{
				const commentEl = evt.item.querySelector('.tsumego-comment') || evt.item;
				const commentId = parseInt((commentEl as HTMLElement).dataset.commentId || '0');
				dragStateRef.current = { commentId, sourceIssueId: null };
				showIssueDropTargets(true, null);
			},
			onEnd: () =>
			{
				showIssueDropTargets(false, null);
				dragStateRef.current = null;
			},
			onAdd: async evt =>
			{
				// Revert SortableJS DOM change to prevent React conflict
				if (evt.from && evt.item.parentNode !== evt.from) 
					evt.from.appendChild(evt.item);

				// SortableJS routes both 'drop' and 'dragend' events through _onDrop, so onAdd
				// fires even for cancelled drags (Escape key, or drop on the source-issue overlay
				// which calls stopPropagation and prevents the 'drop' event from reaching SortableJS).
				// Detect cancellation by checking the original event type: 'dragend' = cancelled,
				// 'drop' = intentional drop on the standalone area.
				// Note: @types/sortablejs omits originalEvent, but SortableJS sets it at runtime.
				const origEvt = (evt as Sortable.SortableEvent & { originalEvent?: Event }).originalEvent;
				if (origEvt?.type === 'dragend')
					return;

				const commentEl = evt.item.querySelector('.tsumego-comment') || evt.item;
				const commentId = parseInt((commentEl as HTMLElement).dataset.commentId || '0');
				await onMoveComment(commentId, 'standalone');
			}
		});
		sortablesRef.current.push(mainSortable);

		// Create sortable for each issue
		containerRef.current.querySelectorAll('.tsumego-dnd__issue-dropzone').forEach((container: Element) =>
		{
			const htmlContainer = container as HTMLElement;
			const issueId = parseInt(htmlContainer.dataset.issueId || '0');
			const issueEl = container.closest('.tsumego-issue') as HTMLElement;

			const issueSortable = new Sortable(htmlContainer, {
				group: {
					name: `issue-${issueId}`,
					pull: true,
					// Allow items to be dropped back into this issue (drag cancel / return).
					// Without this, SortableJS commits the item to the main sortable on drop-back,
					// firing onAdd → onMoveComment('standalone') which incorrectly deletes the issue.
					//
					// Two return paths are handled:
					// 1. Direct: user drops straight back on the issue without hovering main (from.el = issue dropzone)
					// 2. Via main: user hovered over main container, then dropped on issue (from.el = main container)
					//    Only allowed when the drag originated from THIS issue (sourceIssueId === issueId),
					//    preventing accidental cross-issue moves.
					put: (_to: Sortable, from: Sortable) =>
						from.el === htmlContainer ||
						(
							containerRef.current !== null &&
							from.el === containerRef.current &&
							dragStateRef.current?.sourceIssueId === issueId
						)
				},
				sort: false,
				animation: 0,
				scroll: true,
				scrollSensitivity: 80,
				scrollSpeed: 15,
				bubbleScroll: true,
				handle: '.tsumego-comment__drag-handle',
				draggable: '.tsumego-comment',
				ghostClass: 'tsumego-comment--ghost',
				chosenClass: 'tsumego-comment--dragging',
				onStart: (evt: Sortable.SortableEvent) =>
				{
					const commentId = parseInt((evt.item as HTMLElement).dataset.commentId || '0');
					dragStateRef.current = { commentId, sourceIssueId: issueId };
					issueEl.classList.add('tsumego-issue--drag-source');
					showIssueDropTargets(true, issueId);
				},
				onAdd: (_evt: Sortable.SortableEvent) =>
				{
					// Comment was dropped back into this issue (drag cancelled/returned).
					// SortableJS placed the item here; React's virtual DOM also has it here. No API call.
				},
				onEnd: () =>
				{
					issueEl.classList.remove('tsumego-issue--drag-source');
					showIssueDropTargets(false, null);
					dragStateRef.current = null;
				}
			});
			sortablesRef.current.push(issueSortable);

			// Setup drop overlay
			setupIssueDropTarget(issueEl, issueId);
		});

		// ESC key handler to cancel drag
		const handleEscKey = (e: KeyboardEvent) =>
		{
			if (e.key === 'Escape' && dragStateRef.current)
			{
				showIssueDropTargets(false, null);
				sortablesRef.current.forEach(s =>
				{
					if ((s as { el?: HTMLElement }).el)
						(s as { el: HTMLElement }).el.classList.remove('tsumego-issue--drag-source');
				});
				dragStateRef.current = null;
			}
		};
		document.addEventListener('keydown', handleEscKey);

		const handleDragEnd = () =>
		{
			showIssueDropTargets(false, null);
			dragStateRef.current = null;
		};
		document.addEventListener('dragend', handleDragEnd);

		// Cleanup on unmount
		return () =>
		{
			sortablesRef.current.forEach(s => s.destroy());
			sortablesRef.current = [];
			showIssueDropTargets(false, null);
			document.removeEventListener('keydown', handleEscKey);
			document.removeEventListener('dragend', handleDragEnd);
		};
	}, [isAdmin, tsumegoId, issues.length, onMoveComment, containerRef]);
}
