import { useState } from 'react';

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

		await onSubmit(form.text, form.position, form.reportAsIssue);
		setForm({ text: '', position: undefined, reportAsIssue: false });
	};

	// Builds the position string for the currently-selected board node, or null
	// when the board is at a node without a move (e.g. the root position).
	const buildPositionFromCurrent = (): string | null =>
	{
		// Get position from besogo editor
		const current = window.besogo.editor.getCurrent();
		if (!current.move)
		{
			alert('No move at current position');
			return null;
		}

		// Get orientation from besogo
		const besogoOrientation = window.besogo.editor.getOrientation();
		const orientation = besogoOrientation[1] === 'full-board' ? 'full-board' : besogoOrientation[0];

		// Use coordinates directly from besogo (NO normalization)
		const moveX = current.move.x;
		const moveY = current.move.y;

		// Get parent coordinates
		let pX = -1;
		let pY = -1;
		if (current.moveNumber > 1 && current.parent && current.parent.move)
		{
			pX = current.parent.move.x;
			pY = current.parent.move.y;
		}

		// Get first child coordinates
		let cX = -1;
		let cY = -1;
		if (current.children && current.children.length > 0 && current.children[0].move)
		{
			cX = current.children[0].move.x;
			cY = current.children[0].move.y;
		}

		// Build path from ROOT to CURRENT (reversed order)
		const pathCoords: [number, number][] = [];
		pathCoords.push([moveX, moveY]);
		let newP = current.parent;
		while (newP && newP.move)
		{
			pathCoords.push([newP.move.x, newP.move.y]);
			newP = newP.parent;
		}
		// Reverse to go from root to current (matches PHP version)
		pathCoords.reverse();
		const newPcoords = pathCoords.map(c => `${c[0]}/${c[1]}`).join('+');

		// Store with current orientation (matches original PHP behavior)
		return `${moveX}/${moveY}/${pX}/${pY}/${cX}/${cY}/${current.moveNumber}/${current.children?.length || 0}/${orientation}|${newPcoords}`;
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
	const positionMoveNumber = form.position ? parseInt(form.position.split('/')[6], 10) : null;

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
								? `Move ${positionMoveNumber ?? '?'} attached`
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
