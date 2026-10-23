import { useState } from 'react';
import { positionMoveNumber } from './boardPosition';
import { commentTextFromBoardView } from './boardView';

interface CommentFormProps
{
	onSubmit: (text: string, position?: string, reportAsIssue?: boolean) => Promise<void>;
	isSubmitting: boolean;
	showReportAsIssue?: boolean;
	submitButtonText?: string;
	placeholder?: string;
}

export function CommentForm({
	onSubmit,
	isSubmitting,
	showReportAsIssue = true,
	submitButtonText = 'Post Comment',
	placeholder = 'Write a comment...'
}: CommentFormProps)
{
	const [form, setForm] = useState({
		text: '',
		position: undefined as string | undefined,
		reportAsIssue: false
	});

	const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) =>
	{
		e.preventDefault();
		if (!form.text.trim() || isSubmitting) 
			return;

		// Saved in the coordinates and colours of the SGF, see boardView
		await onSubmit(commentTextFromBoardView(form.text), form.position, form.reportAsIssue);
		setForm({ text: '', position: undefined, reportAsIssue: false });
	};

	// Builds the position string for the currently-selected board node, or null
	// when the board is at a node without a move (e.g. the root position).
	const buildPositionFromCurrent = (): string | null =>
	{
		const editor = window.besogo.editor;
		const position = editor.makeCommentPosition(editor.getCurrent());
		if (!position)
			alert('No move at current position');
		return position;
	};

	// The checkbox is the single "Attach board position" toggle.
	// Checking captures the current position; unchecking removes it.
	const handlePositionToggle = (e: React.ChangeEvent<HTMLInputElement>) =>
	{
		if (e.target.checked)
		{
			const pos = buildPositionFromCurrent();
			if (pos)
				setForm(f => ({ ...f, position: pos }));
		}
		else
			setForm(f => ({ ...f, position: undefined }));
	};

	// Move number of the attached position, for display ("Move 5 attached").
	const attachedMoveNumber = form.position ? positionMoveNumber(form.position) : null;

	return (
		<div className="tsumego-comments__form">
			<form id="tsumegoCommentForm" onSubmit={handleSubmit}>
				<textarea
					id="commentMessage-tsumegoCommentForm"
					value={form.text}
					onInput={e => setForm(f => ({ ...f, text: (e.target as HTMLTextAreaElement).value }))}
					placeholder={placeholder}
					rows={3}
					maxLength={2000}
					disabled={isSubmitting}
					required
				/>

				<div
					className="tsumego-comments__char-counter"
					style={{
						color: form.text.length > 1950 ? 'var(--feedback-error)' : form.text.length > 1800 ? 'var(--feedback-warning)' : 'var(--text-softer-color)'
					}}
				>
					<span>{form.text.length}</span> / <span>2000</span> characters
				</div>

				<div className="tsumego-comments__form-actions">
					{showReportAsIssue && (
						<label>
							<input
								type="checkbox"
								id="reportIssueCheckbox-tsumegoCommentForm"
								checked={form.reportAsIssue}
								onChange={e =>
									setForm(f => ({
										...f,
										reportAsIssue: (e.target as HTMLInputElement).checked
									}))
								}
								disabled={isSubmitting}
							/>
							Report as an issue (missing move, wrong answer, etc.)
						</label>
					)}

					<label className="tsumego-comments__position-toggle">
						<input
							type="checkbox"
							id="attachPositionCheckbox-tsumegoCommentForm"
							checked={!!form.position}
							onChange={handlePositionToggle}
							disabled={isSubmitting}
						/>
						<span aria-hidden="true">📌</span>
						<span>
							{form.position
								? `Move ${attachedMoveNumber} attached`
								: 'Attach board position'}
						</span>
					</label>
				</div>

				<div className="tsumego-comments__form-buttons">
					<button
						type="submit"
						id="submitBtn-tsumegoCommentForm"
						disabled={!form.text.trim() || isSubmitting}
						className="btn"
					>
						{isSubmitting ? (
							<>
								<span className="spinner" aria-hidden="true" />
								<span>Posting...</span>
							</>
						) : (
							submitButtonText
						)}
					</button>
				</div>
			</form>
		</div>
	);
}
