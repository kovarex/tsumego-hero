import { SkeletonElement } from '../shared/SkeletonElement';
import { CommentSkeleton } from '../comments/CommentSkeleton';

/**
 * Skeleton for an issue card
 */
export function IssueSkeleton()
{
	return (
		<div className="tsumego-issue skeleton-wrapper">
			{/* Issue header */}
			<div className="tsumego-issue__header">
				<div
					style={{
						display: 'flex',
						alignItems: 'center',
						gap: '12px',
						marginBottom: '12px'
					}}
				>
					<SkeletonElement width="80px" height="24px" borderRadius="12px" /> {/* Status badge */}
					<SkeletonElement width="120px" height="14px" /> {/* Author */}
					<SkeletonElement width="80px" height="12px" /> {/* Date */}
				</div>
				<SkeletonElement width="95%" height="18px" style={{ marginBottom: '8px' }} />
				<SkeletonElement width="80%" height="18px" />
			</div>

			{/* Issue comments (2-3 nested comments) */}
			<div style={{ paddingLeft: '20px', borderLeft: '2px solid #e0e0e0' }}>
				<CommentSkeleton />
				<CommentSkeleton />
			</div>
		</div>
	);
}

/**
 * Skeleton for issues page list
 */
export function IssuesListSkeleton()
{
	return (
		<div className="issues-list">
			<IssueSkeleton />
			<IssueSkeleton />
			<IssueSkeleton />
			<IssueSkeleton />
			<IssueSkeleton />
		</div>
	);
}
