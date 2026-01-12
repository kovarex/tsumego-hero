/**
 * Base skeleton element with shimmer animation
 * Reusable building block for creating skeleton screens
 */
export function SkeletonElement({ 
	width, 
	height = '1rem', 
	borderRadius = '4px',
	style 
}: { 
	width?: string; 
	height?: string; 
	borderRadius?: string;
	style?: React.CSSProperties;
}) {
	return (
		<div 
			className="skeleton-element"
			style={{
				width,
				height,
				borderRadius,
				...style
			}}
		/>
	);
}
