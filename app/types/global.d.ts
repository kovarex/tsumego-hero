/**
 * Global type definitions for Tsumego Hero.
 * These define the JavaScript globals exposed by the PHP templates.
 */

/**
 * Besogo board editor API.
 * The besogo editor is defined in webroot/besogo/js/editor.js
 */
interface BesogoEditor {
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
	 * Get the board orientation.
	 * Returns [corner, orientation] where:
	 * - corner: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right'
	 * - orientation: 'full-board' | corner
	 */
	getOrientation(): [string, string];

	/**
	 * Navigate to a saved board position.
	 * @param positionParams Array of position parameters from database:
	 *   [x, y, parentX, parentY, childX, childY, moveNumber, childrenCount, orientation, path]
	 */
	commentPosition(positionParams: (number | string)[]): void;

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
interface BesogoNode {
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
interface Besogo {
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

declare global {
	interface Window {
		/** Besogo board editor (defined in PHP templates) */
		besogo: Besogo;

		/**
		 * Navigate to a comment's saved board position.
		 * Defined in play.ctp, called by comment position icons.
		 * @param x Current move X coord
		 * @param y Current move Y coord
		 * @param pX Parent move X coord (-1 if none)
		 * @param pY Parent move Y coord (-1 if none)
		 * @param cX Child move X coord (-1 if none)
		 * @param cY Child move Y coord (-1 if none)
		 * @param mNum Move number
		 * @param cNum Number of children
		 * @param orientation Board orientation when saved
		 * @param newX Optional new X coord (unused)
		 * @param newY Optional new Y coord (unused)
		 */
		commentPosition(
			x: number,
			y: number,
			pX: number,
			pY: number,
			cX: number,
			cY: number,
			mNum: number,
			cNum: number,
			orientation: string,
			newX?: number,
			newY?: number
		): void;

		/**
		 * Show coordinate popup on hover (displays position on floating board).
		 * Defined in play.ctp.
		 * @param coord Go coordinate like "C3", "D4"
		 * @param event Mouse event for positioning
		 */
		showCoordPopup(coord: string, event: MouseEvent): void;

		/**
		 * Hide coordinate popup.
		 * Defined in play.ctp.
		 */
		hideCoordPopup(): void;
	}
}

export {};
