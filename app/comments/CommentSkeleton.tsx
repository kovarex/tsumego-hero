import { SkeletonElement } from '../shared/SkeletonElement';

/**
 * Skeleton for a comment card
 */
export function CommentSkeleton()
{
	return (
		<div className="tsumego-comment skeleton-wrapper">
			<div className="tsumego-comment__content">
				{/* Author header */}
				<div
					style={{
						display: 'flex',
						alignItems: 'center',
						gap: '8px',
						marginBottom: '8px'
					}}
				>
					<SkeletonElement width="100px" height="14px" /> {/* Author name */}
					<SkeletonElement width="80px" height="12px" /> {/* Timestamp */}
				</div>

				{/* Comment text (multiple lines) */}
				<SkeletonElement width="100%" height="16px" style={{ marginBottom: '6px' }} />
				<SkeletonElement width="90%" height="16px" style={{ marginBottom: '6px' }} />
				<SkeletonElement width="75%" height="16px" />
			</div>
		</div>
	);
}

/**
 * Skeleton for comments list (mix of comments and issues with skeletons below)
 */
export function CommentsListSkeleton()
{
	return (
		<div className="tsumego-comments__content">
			<CommentSkeleton />
			<CommentSkeleton />
			<CommentSkeleton />
			<CommentSkeleton />
			<CommentSkeleton />
		</div>
	);
}
