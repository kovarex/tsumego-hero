import { useEffect, useState } from 'react';
import type { BesogoChange } from '../types/global';
import { transformCommentText } from './commentCoordinates';

function boardEditor()
{
	return typeof window.besogo !== 'undefined' && window.besogo ? window.besogo.editor : null;
}

/**
 * Converts comment text stored in the coordinates and colours of the SGF to
 * the board as the viewer sees it. Without a board the text stays as stored.
 */
export function commentTextToBoardView(text: string): string
{
	const editor = boardEditor();
	if (!editor)
		return text;
	return transformCommentText(text, label => editor.sgfLabelToView(label), editor.isColorInverted());
}

/**
 * Converts comment text written while looking at the board back to the
 * coordinates and colours of the stored SGF, as comments are saved.
 */
export function commentTextFromBoardView(text: string): string
{
	const editor = boardEditor();
	if (!editor)
		return text;
	return transformCommentText(text, label => editor.viewLabelToSgf(label), editor.isColorInverted());
}

/**
 * Renders the component again whenever the board is flipped, rotated or its
 * colours swapped, so text converted to the board view follows the board.
 */
export function useRerenderOnBoardTransformation(): void
{
	const [, setVersion] = useState(0);
	useEffect(() =>
	{
		const editor = boardEditor();
		if (!editor)
			return;
		const listener = (change: BesogoChange) =>
		{
			if (change.transformChange)
				setVersion(version => version + 1);
		};
		editor.addListener(listener);
		return () => editor.removeListener(listener);
	}, []);
}
