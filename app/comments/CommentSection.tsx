import { useEffect, useRef, useState, useMemo } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import Sortable from 'sortablejs';
import { Issue } from '../issues/Issue';
import { Comment } from './Comment';
import { CommentForm } from './CommentForm';
import type { Issue as IssueType, Comment as CommentType, CommentCounts } from './commentTypes';
import { useCommentsQuery, useAddComment, useDeleteComment, useReplyToIssue, useCloseReopenIssue, useMakeIssue } from './useComments';
import { moveComment } from '../shared/api';

interface CommentSectionProps {
    tsumegoId: number;
    userId: number | null;
    isAdmin: boolean;
    initialCounts: CommentCounts;
}

export function CommentSection({ tsumegoId, userId, isAdmin, initialCounts }: CommentSectionProps) {
    const contentRef = useRef<HTMLDivElement>(null);
    const sortablesRef = useRef<Sortable[]>([]);
    const dragStateRef = useRef<{ commentId: number; sourceIssueId: number | null } | null>(null);
    
    // Local UI state (tabs and visibility)
    const [activeTab, setActiveTab] = useState<'open' | 'closed' | null>(null);
    const [hasEverOpened, setHasEverOpened] = useState(false);  // Track if user clicked a tab
    
    // React Query for comments data - only fetch when user clicks tab
    const queryClient = useQueryClient();
    const commentsQuery = useCommentsQuery(tsumegoId, hasEverOpened);
    
    // Use query data as source of truth (empty until first fetch)
    const issues = commentsQuery.data?.issues ?? [];
    const standalone = commentsQuery.data?.standalone ?? [];
    const counts = commentsQuery.data?.counts ?? initialCounts;
    
    // Merge and sort issues + standalone comments chronologically (oldest first)
    const allItems = useMemo(() => {
        const items: Array<{type: 'issue' | 'comment', created: string, data: IssueType | CommentType, issueNumber?: number}> = [];
        
        // Add issues - use EARLIEST comment date for sorting (not issue creation)
        issues.forEach((issue) => {
            let sortDate = issue.created; // Fallback to issue creation
            if (issue.comments && issue.comments.length > 0) {
                // Comments in issue are ASC, so first is earliest
                const earliestCommentDate = issue.comments[0].created;
                if (new Date(earliestCommentDate) < new Date(sortDate)) {
                    sortDate = earliestCommentDate;
                }
            }
            items.push({
                type: 'issue',
                created: sortDate,
                data: issue,
                issueNumber: issue.id
            });
        });
        
        // Add standalone comments
        standalone.forEach(comment => {
            items.push({
                type: 'comment',
                created: comment.created,
                data: comment
            });
        });
        
        // Sort by date (oldest first - matches old PHP)
        items.sort((a, b) => new Date(a.created).getTime() - new Date(b.created).getTime());
        
        return items;
    }, [issues, standalone]);
    
    // Toggle tab helper - fetch on first click
    const toggleTab = (tab: 'open' | 'closed') => {
        setHasEverOpened(true);  // Enable query
        setActiveTab(current => current === tab ? null : tab);
    };
    
    // TanStack Query mutations
    const addMutation = useAddComment();
    const deleteMutation = useDeleteComment();
    const replyMutation = useReplyToIssue();
    const closeReopenMutation = useCloseReopenIssue();
    
    // Reset tabs when switching problems
    useEffect(() => {
        // Don't auto-expand tabs - let user click to expand
        // Reset query enabled state when switching problems
        setActiveTab(null);
        setHasEverOpened(false);  // Require user to click tab again
    }, [tsumegoId]);
    
    // Initialize SortableJS drag-and-drop
    useEffect(() => {
        if (!isAdmin || !contentRef.current) return;

        // Cleanup previous sortables
        sortablesRef.current.forEach(s => s.destroy());
        sortablesRef.current = [];

        console.log('[Comment DnD] Initializing SortableJS for tsumego:', tsumegoId);

        const showIssueDropTargets = (show: boolean, sourceIssueId: number | null) => {
            document.querySelectorAll('.tsumego-issue').forEach((issueEl: Element) => {
                const htmlIssueEl = issueEl as HTMLElement;
                const issueId = parseInt(htmlIssueEl.dataset.issueId || '0');
                const overlay = issueEl.querySelector('.tsumego-issue__drop-overlay') as HTMLElement;
                if (overlay) {
                    if (show) {
                        overlay.style.display = 'flex';
                        const span = overlay.querySelector('span');
                        if (issueId === sourceIssueId) {
                            overlay.classList.add('tsumego-issue__drop-overlay--source');
                            if (span) span.textContent = 'Drop to return comment';
                        } else {
                            overlay.classList.remove('tsumego-issue__drop-overlay--source');
                            if (span) span.textContent = 'Drop here to add to this issue';
                        }
                    } else {
                        overlay.style.display = 'none';
                        overlay.classList.remove('tsumego-issue__drop-overlay--source');
                    }
                }
            });
        };

        // Create main sortable for standalone comments
        const mainSortable = new Sortable(contentRef.current, {
            group: { name: 'comments', pull: true, put: true } as any,
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
            onStart: (evt) => {
                const commentEl = evt.item.querySelector('.tsumego-comment') || evt.item;
                const commentId = parseInt((commentEl as HTMLElement).dataset.commentId || '0');
                dragStateRef.current = { commentId, sourceIssueId: null };
                console.log('[Comment DnD] Dragging standalone comment:', commentId);
                showIssueDropTargets(true, null);
            },
            onEnd: () => {
                console.log('[Comment DnD] onEnd (main)');
                showIssueDropTargets(false, null);
                dragStateRef.current = null;
            },
            onAdd: async (evt) => {
                // Revert SortableJS DOM change - prevent React conflict
                // SortableJS already moved the DOM node, but React will handle the move
                if (evt.from && evt.item.parentNode !== evt.from) {
                    evt.from.appendChild(evt.item);
                }
                
                // Now update React state, which will properly re-render the element
                const commentEl = evt.item.querySelector('.tsumego-comment') || evt.item;
                const commentId = parseInt((commentEl as HTMLElement).dataset.commentId || '0');
                console.log('[Comment DnD] Comment', commentId, 'dropped to standalone');
                await handleMoveComment(commentId, 'standalone');
            }
        });
        sortablesRef.current.push(mainSortable);

        // Create sortable for each issue
        contentRef.current.querySelectorAll('.tsumego-dnd__issue-dropzone').forEach((container: Element) => {
            const htmlContainer = container as HTMLElement;
            const issueId = parseInt(htmlContainer.dataset.issueId || '0');
            const issueEl = container.closest('.tsumego-issue') as HTMLElement;
            
            const issueSortable = new Sortable(htmlContainer, {
                group: { name: `issue-${issueId}`, pull: 'comments', put: false } as any,
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
                onStart: (evt) => {
                    const commentId = parseInt((evt.item as HTMLElement).dataset.commentId || '0');
                    dragStateRef.current = { commentId, sourceIssueId: issueId };
                    console.log('[Comment DnD] Dragging from issue:', issueId);
                    issueEl.classList.add('tsumego-issue--drag-source');
                    showIssueDropTargets(true, issueId);
                },
                onEnd: () => {
                    console.log('[Comment DnD] onEnd (issue)');
                    issueEl.classList.remove('tsumego-issue--drag-source');
                    showIssueDropTargets(false, null);
                    dragStateRef.current = null;
                }
            });
            sortablesRef.current.push(issueSortable);

            // Setup drop overlay on issue header
            setupIssueDropTarget(issueEl, issueId);
        });

        // ESC key handler to cancel drag
        const handleEscKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && dragStateRef.current) {
                console.log('[Comment DnD] ESC pressed - cancelling drag');
                // Hide all overlays
                showIssueDropTargets(false, null);
                // Cancel all sortables
                sortablesRef.current.forEach(s => {
                    if ((s as any).el) {
                        (s as any).el.classList.remove('tsumego-issue--drag-source');
                    }
                });
                dragStateRef.current = null;
            }
        };
        document.addEventListener('keydown', handleEscKey);

        // Cleanup on unmount
        return () => {
            sortablesRef.current.forEach(s => s.destroy());
            sortablesRef.current = [];
            document.removeEventListener('keydown', handleEscKey);
        };
    }, [isAdmin, tsumegoId, issues.length]); // Reinit when issue count changes (new issue added/removed)
    
    const setupIssueDropTarget = (issueEl: HTMLElement, issueId: number) => {
        let overlay = issueEl.querySelector('.tsumego-issue__drop-overlay') as HTMLElement;
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'tsumego-issue__drop-overlay';
            overlay.innerHTML = '<span>Drop here to add to this issue</span>';
            overlay.style.display = 'none';
            issueEl.appendChild(overlay);
        }

        const handleDragOver = (e: DragEvent) => {
            e.preventDefault();
            overlay.classList.add('tsumego-issue__drop-overlay--active');
        };

        const handleDragLeave = () => {
            overlay.classList.remove('tsumego-issue__drop-overlay--active');
        };

        const handleDrop = async (e: DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            overlay.classList.remove('tsumego-issue__drop-overlay--active');

            if (!dragStateRef.current) return;

            const { commentId, sourceIssueId } = dragStateRef.current;

            // Check if this is a return to source issue
            if (sourceIssueId === issueId) {
                console.log('[Comment DnD] Comment returned to source issue');
                return;
            }

            console.log('[Comment DnD] Comment', commentId, 'dropped into issue', issueId);
            
            // Find the dragged element and revert any SortableJS DOM changes
            const draggedElement = document.querySelector(`[data-comment-id="${commentId}"]`)?.closest('.tsumego-comment--standalone, .tsumego-comment') as HTMLElement;
            if (draggedElement) {
                const originalParent = draggedElement.parentElement;
                // Store reference to revert later if needed
                const revertMove = () => {
                    if (originalParent && draggedElement.parentElement !== originalParent) {
                        originalParent.appendChild(draggedElement);
                    }
                };
                
                // Revert SortableJS move before React updates
                setTimeout(revertMove, 0);
            }
            
            await handleMoveComment(commentId, issueId);
        };

        overlay.addEventListener('dragover', handleDragOver);
        overlay.addEventListener('dragleave', handleDragLeave);
        overlay.addEventListener('drop', handleDrop);
    };

    const handleMoveComment = async (commentId: number, targetIssueId: number | 'standalone') => {
        try {
            console.log('[Comment DnD] Moving comment:', commentId, 'to:', targetIssueId);
            const result = await moveComment(commentId, targetIssueId);
            if (result.success) {
                // Refetch from server - let backend tell us the new state
                console.log('[Comment DnD] Move successful, invalidating queries to refetch');
                await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
            }
        } catch (error) {
            console.error('[Comment DnD] Move failed:', error);
            alert('Failed to move comment. Please try again.');
        }
    };
    
    // Handlers
    const handleAdd = async (text: string, position?: string, reportAsIssue?: boolean) => {
        return new Promise<void>((resolve) => {
            addMutation.mutate(
                { data: { text, tsumego_id: tsumegoId, position, report_as_issue: reportAsIssue } },
                { 
                    onSuccess: async () => { 
                        console.log('[handleAdd] Comment added, refetching from server');
                        await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
                        resolve(); 
                    } 
                }
            );
        });
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this comment?')) return;
        await deleteMutation.mutateAsync({ commentId: id });
        console.log('[handleDelete] Comment deleted, refetching from server');
        await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
    };

    const handleReply = async (issueId: number, text: string, position?: string) => {
        return new Promise<void>((resolve) => {
            replyMutation.mutate(
                { issueId, text, tsumegoId, position },
                { 
                    onSuccess: async () => { 
                        console.log('[handleReply] Reply added, refetching from server');
                        await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
                        resolve(); 
                    } 
                }
            );
        });
    };

    const handleCloseReopen = async (issueId: number, newStatus: 'open' | 'closed') => {
        return new Promise<void>((resolve, reject) => {
            closeReopenMutation.mutate(
                { issueId, newStatus },
                { 
                    onSuccess: async () => { 
                        console.log('[handleCloseReopen] Issue status changed, refetching from server');
                        await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
                        resolve(); 
                    }, 
                    onError: reject 
                }
            );
        });
    };

    const handleMakeIssue = async (commentId: number) => {
        try {
            const formData = new FormData();
            formData.append('data[Comment][tsumego_issue_id]', 'new');
            
            const response = await fetch(`/tsumego-issues/move-comment/${commentId}`, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json() as { success: boolean; issue: IssueType; comment_id: number };
            console.log('[handleMakeIssue] Issue created, refetching from server');
            await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
        } catch (error) {
            console.error('[CommentSection] Make issue failed:', error);
        }
    };

    // Filtering
    const shouldShowItem = (item: IssueType | CommentType, type: 'issue' | 'standalone') => {
        if (!activeTab) return false;
        if (activeTab === 'open') return type === 'standalone' || (item as IssueType).status === 'open';
        return type === 'issue' && (item as IssueType).status === 'closed';
    };

    const showContent = activeTab !== null;
    const showForm = activeTab === 'open';
    const hasContent = counts.comments > 0 || counts.openIssues > 0;
    const commentsPart = counts.comments > 0 ? `${counts.comments} COMMENT${counts.comments > 1 ? 'S' : ''}` : '';
    const issuesPart = counts.openIssues > 0 ? `🔴 ${counts.openIssues} OPEN ISSUE${counts.openIssues > 1 ? 'S' : ''}` : '';
    
    let commentsTabText = 'COMMENTS';
    if (commentsPart && issuesPart) commentsTabText = `${commentsPart} ${issuesPart}`;
    else if (commentsPart) commentsTabText = commentsPart;
    else if (issuesPart) commentsTabText = issuesPart;

    const closedCount = counts.issues - counts.openIssues;
    const closedTabText = closedCount === 0 ? 'CLOSED ISSUES' : `${closedCount} CLOSED ISSUE${closedCount > 1 ? 'S' : ''}`;

    return (
        <>
            <div className="tsumego-comments__tabs">
                <button 
                    className={`tsumego-comments__tab ${activeTab === 'open' ? 'active' : ''}${!hasContent ? ' tsumego-comments__tab--empty' : ''}`}
                    data-filter="open" 
                    onClick={() => toggleTab('open')}
                >
                    {commentsTabText}
                </button>
                <button 
                    className={`tsumego-comments__tab ${activeTab === 'closed' ? 'active' : ''}${closedCount === 0 ? ' tsumego-comments__tab--empty' : ''}`}
                    data-filter="closed" 
                    onClick={() => toggleTab('closed')}
                >
                    {closedTabText}
                </button>
            </div>

            <div className="tsumego-comments__content" id="msg2x" ref={contentRef} style={{ display: showContent ? '' : 'none' }}>
                {allItems.map(item => {
                    if (item.type === 'issue') {
                        const issue = item.data as IssueType;
                        return (
                            <div key={`issue-${issue.id}`} style={{ display: shouldShowItem(issue, 'issue') ? '' : 'none' }}>
                                <Issue issue={issue} issueNumber={item.issueNumber!} currentUserId={userId} isAdmin={isAdmin}
                                    onDelete={handleDelete} onReply={handleReply} onCloseReopen={handleCloseReopen} />
                            </div>
                        );
                    } else {
                        const comment = item.data as CommentType;
                        return (
                            <div className="tsumego-comment--standalone" key={`comment-${comment.id}`} style={{ display: shouldShowItem(comment, 'standalone') ? '' : 'none' }}>
                                <Comment comment={comment} currentUserId={userId} isAdmin={isAdmin}
                                    onDelete={handleDelete} onMakeIssue={handleMakeIssue} showIssueContext={true} />
                            </div>
                        );
                    }
                })}

                {userId ? (
                    <div className="tsumego-comments__form" style={{ display: showForm ? '' : 'none' }}>
                        <h4>Add Comment</h4>
                        <CommentForm onSubmit={handleAdd} isSubmitting={addMutation.isPending} />
                    </div>
                ) : (
                    <div className="tsumego-comments__login-prompt" style={{ display: showForm ? '' : 'none' }}>
                        <p><a href="/users/login">Log in</a> to leave a comment.</p>
                    </div>
                )}
            </div>
        </>
    );
}
