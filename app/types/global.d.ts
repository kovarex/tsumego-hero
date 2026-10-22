/**
 * Global type definitions for Tsumego Hero.
 * These define the JavaScript globals exposed by the PHP templates.
 */

/**
 * Besogo board editor API.
 * The besogo editor is defined in webroot/besogo/js/editor.js
 */
/** What changed, as passed to editor listeners. */
interface BesogoChange
{
	navChange?: boolean;
	treeChange?: boolean;
	stoneChange?: boolean;
	/** The board was flipped, rotated or its colours swapped, or a new SGF was loaded */
	transformChange?: boolean;
}

interface BesogoEditor
{
	/**
	 * Get the current move node in the game tree.
	 */
	getCurrent(): BesogoNode;

	/**
	 * Get the root node of the game tree.
	 */
	getRoot(): BesogoNode;

	/**
	 * Set the current position to the given node.
	 */
	setCurrent(node: BesogoNode): void;

	/**
	 * Click on a board position (for navigation or playing moves).
	 * @param x X coordinate (1-19)
	 * @param y Y coordinate (1-19)
	 * @param ctrlKey If true, allows illegal moves
	 * @param shiftKey If true, does tree search for the move
	 */
	click(x: number, y: number, ctrlKey: boolean, shiftKey: boolean): void;

	/**
	 * Resolve a comment's `position` string to the game-tree node it anchors.
	 * Returns null when the node cannot be found. Does not navigate/mutate.
	 * @param positionString The comment.position value, see makeCommentPosition
	 */
	findNodeForPosition(positionString: string): BesogoNode | null;

	/**
	 * Show the position a comment is anchored to on the board. Moves the tree
	 * no longer has are played as local moves. Returns false without a position.
	 * @param positionString The comment.position value, see makeCommentPosition
	 */
	showCommentPosition(positionString: string): boolean;

	/**
	 * Build the position string anchoring a comment to a node:
	 * "x/y/parentX/parentY/childX/childY/moveNumber/childCount/sgf|path" with the
	 * path of moves from the root, all in coordinates of the stored SGF.
	 * Returns null for a node without a move.
	 */
	makeCommentPosition(node: BesogoNode): string | null;

	/**
	 * Convert a coordinate label of the stored SGF to the label of the same
	 * intersection on the board as shown, or null when it is not on the board.
	 */
	sgfLabelToView(label: string): string | null;

	/**
	 * Convert a coordinate label read on the board as shown to the label of
	 * the same intersection in the stored SGF, or null when it is not on the board.
	 */
	viewLabelToSgf(label: string): string | null;

	/** Whether black and white are swapped compared to the stored SGF. */
	isColorInverted(): boolean;

	/**
	 * The transformation from the stored SGF to the board as shown: an optional
	 * transpose (x and y swapped) followed by the flips.
	 */
	getTransformationFromSgf(): { transpose: boolean; flipX: boolean; flipY: boolean; invertColors: boolean };

	/**
	 * Get the number of position-anchored comments attached to a node.
	 */
	getAnchoredCommentCount(node: BesogoNode): number;

	/**
	 * Set which nodes have position-anchored comments (Map of node -> count).
	 * Triggers a tree rebuild so badges appear when review mode is on.
	 */
	setAnchoredComments(nodeCounts: Map<BesogoNode, number>): void;

	/**
	 * Convert a western coordinate label (e.g. "R19") to its (x, y) point.
	 */
	coordLabelToXY(coord: string): { x: number; y: number } | null;

	/**
	 * Convert an (x, y) point back to a western coordinate label.
	 * Inverse of coordLabelToXY.
	 */
	coordXYToLabel(x: number, y: number): string | null;

	/**
	 * Register a listener for editor state changes (navChange/treeChange/etc).
	 * transformChange means the board was flipped, rotated or its colours swapped.
	 */
	addListener(listener: (msg: BesogoChange) => void): void;

	/**
	 * Remove a previously registered listener.
	 */
	removeListener(listener: (msg: BesogoChange) => void): void;

	/**
	 * Play a move at the given coordinates.
	 * @param x X coordinate (1-19)
	 * @param y Y coordinate (1-19)
	 * @param color 0=auto (alternating), 1=black, 2=white
	 * @param allowAll If true, allows moves even if not in game tree (creates exploratory moves)
	 */
	playMove(x: number, y: number, color: number, allowAll: boolean): void;
}

/**
 * A node in the besogo game tree.
 */
interface BesogoNode
{
	/** The move at this node, or null if root */
	move: { x: number; y: number } | null;

	/** Move number (1-indexed) */
	moveNumber: number;

	/** Parent node, or null if root */
	parent: BesogoNode | null;

	/** Child nodes (variations) */
	children: BesogoNode[];

	/** Get board size */
	getSize(): { x: number; y: number };
}

/**
 * Besogo board API exposed globally.
 */
interface Besogo
{
	/** The board editor instance */
	editor: BesogoEditor;

	/** Board rendering parameters */
	scaleParameters: {
		orientation: string;
	};

	/** Board display parameters */
	boardParameters: {
		corner: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
		coord: string;
	};
}

/**
 * A coordinate hovered as part of a comment move sequence (e.g. R19-P19-Q19).
 */
interface CoordSequence
{
	/** All coordinates of the sequence, as displayed */
	coords: string[];
	/** Per-move colour marker from the comment ('b' / 'w'), null to alternate */
	colors?: ('b' | 'w' | null)[];
	/** Index of the hovered coordinate */
	index: number;
}

declare global
{
	interface Window
	{
		/** Besogo board editor (defined in PHP templates) */
		besogo: Besogo;

		/**
		 * Show coordinate popup on hover (displays position on floating board).
		 * Defined in play.ctp.
		 * @param coord Go coordinate like "C3", "D4"
		 * @param event Mouse event for positioning
		 * @param position Optional comment.position anchor - when present, the
		 *   preview shows the anchored board position instead of the current one.
		 * @param sequence Optional sequence context - when the coordinate is part
		 *   of a dash-joined sequence (e.g. R19-P19-Q19), the preview replays the
		 *   moves before the hovered coordinate so the board reflects the
		 *   position right before that move.
		 */
		showCoordPopup(coord: string, event: MouseEvent, position?: string, sequence?: CoordSequence): void;

		/**
		 * Hide coordinate popup.
		 * Defined in play.ctp.
		 */
		hideCoordPopup(): void;
	}
}

export type { BesogoNode, BesogoEditor, BesogoChange, Besogo, CoordSequence };
