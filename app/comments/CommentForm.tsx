import { useState } from 'react';

interface CommentFormProps {
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
}: CommentFormProps) {
	const [form, setForm] = useState({ text: '', position: undefined as string | undefined, reportAsIssue: false });
	const [isLocalSubmitting, setIsLocalSubmitting] = useState(false);
	
	const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
		e.preventDefault();
		if (!form.text.trim() || isSubmitting || isLocalSubmitting) return;
		
		setIsLocalSubmitting(true);
		
		try {
			await onSubmit(form.text, form.position, form.reportAsIssue);
			setForm({ text: '', position: undefined, reportAsIssue: false });
		} finally {
			setIsLocalSubmitting(false);
		}
	};
	
	const togglePosition = () => {
		if (!form.position) {
			// Get position from besogo editor
			const besogo = window.besogo;
			if (!besogo || !besogo.editor) {
				alert('Board editor not available');
				return;
			}
			
			const current = besogo.editor.getCurrent();
			if (!current || !current.move) {
				alert('No move at current position');
				return;
			}
			
			// Get orientation from besogo
			const besogoOrientation = besogo.editor.getOrientation();
			const orientation = besogoOrientation[1] === 'full-board' ? 'full-board' : besogoOrientation[0];
			
			// Use coordinates directly from besogo (NO normalization)
			const moveX = current.move.x;
			const moveY = current.move.y;
			
			// Get parent coordinates
			let pX = -1, pY = -1;
			if (current.moveNumber > 1 && current.parent && current.parent.move) {
				pX = current.parent.move.x;
				pY = current.parent.move.y;
			}
			
			// Get first child coordinates
			let cX = -1, cY = -1;
			if (current.children && current.children.length > 0 && current.children[0].move) {
				cX = current.children[0].move.x;
				cY = current.children[0].move.y;
			}
			
			// Build path from ROOT to CURRENT (reversed order)
			const pathCoords: [number, number][] = [];
			pathCoords.push([moveX, moveY]);
			let newP = current.parent;
			while (newP && newP.move) {
				pathCoords.push([newP.move.x, newP.move.y]);
				newP = newP.parent;
			}
			// Reverse to go from root to current (matches PHP version)
			pathCoords.reverse();
			const newPcoords = pathCoords.map(c => `${c[0]}/${c[1]}`).join('+');
			
			// Store with current orientation (matches original PHP behavior)
			const pos = `${moveX}/${moveY}/${pX}/${pY}/${cX}/${cY}/${current.moveNumber}/${current.children?.length || 0}/${orientation}|${newPcoords}`;
			
			// Add "[current position]" to message text
			const newText = form.text + '[current position]';
			if (newText.length > 2000) {
				alert('Cannot add board position: comment would exceed 2000 character limit. Please shorten your message first.');
				return;
			}
			
			setForm(f => ({ ...f, text: newText, position: pos }));
		} else {
			// Remove position and "[current position]" text
			const textWithoutPosition = form.text.replace('[current position]', '');
			setForm(f => ({ ...f, text: textWithoutPosition, position: undefined }));
		}
	};
	
	return (
		<div className="tsumego-comments__form">
			<form id="tsumegoCommentForm" onSubmit={handleSubmit}>
				<textarea id="commentMessage-tsumegoCommentForm" value={form.text} onInput={(e) => setForm(f => ({ ...f, text: (e.target as HTMLTextAreaElement).value }))}
					placeholder={placeholder} rows={3} maxLength={2000} disabled={isSubmitting} required />
				
				<div className="tsumego-comments__char-counter" style={{color: form.text.length > 1950 ? '#d9534f' : form.text.length > 1800 ? '#f0ad4e' : '#777'}}>
					<span>{form.text.length}</span> / <span>2000</span> characters
				</div>

				<div className="tsumego-comments__form-actions">
					{showReportAsIssue && (
						<label>
							<input type="checkbox" id="reportIssueCheckbox-tsumegoCommentForm" checked={form.reportAsIssue} 
									onChange={(e) => setForm(f => ({ ...f, reportAsIssue: (e.target as HTMLInputElement).checked }))} 
									disabled={isSubmitting} />
							Report as an issue (missing move, wrong answer, etc.)
						</label>
					)}
					
					<button type="button" className="tsumego-comments__position-btn" onClick={togglePosition}>
						<img src="/img/positionIcon1.png" width="16" alt="" />
						Add board position
					</button>
					
					{form.position && (
						<span className="tsumego-comments__position-indicator">
							✓ Position attached
							<a href="#" onClick={(e) => { e.preventDefault(); setForm(f => ({ ...f, position: undefined })); }}>Remove</a>
						</span>
					)}
				</div>

				<div className="tsumego-comments__form-buttons">
					<button type="submit" id="submitBtn-tsumegoCommentForm" disabled={!form.text.trim() || isSubmitting || isLocalSubmitting} className="tsumego-comments__submit-btn">
						{isSubmitting || isLocalSubmitting ? (
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

