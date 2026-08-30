import { useState, useRef, useCallback, useEffect } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useAuth } from '../shared/AuthContext';
import type { TagItem } from './tagTypes';
import { addTag, removeTag } from './api';

declare function makeIdValidName(name: string): string;

interface Props
{
	tsumegoId: number;
	isTimeMode: boolean;
	problemSolved: boolean;
	canAddMoreTags: boolean;
	isAllowedToContribute: boolean;
	initialTags: TagItem[];
}

export function TagEditor({ tsumegoId, isTimeMode, problemSolved, canAddMoreTags, isAllowedToContribute, initialTags }: Props)
{
	const { userId, isAdmin } = useAuth();
	const [tags, setTags] = useState<TagItem[]>(initialTags);
	const [query, setQuery] = useState('');
	const [selectedIndex, setSelectedIndex] = useState(0);
	const [open, setOpen] = useState(false);
	const inputRef = useRef<HTMLInputElement>(null);
	const dropdownRef = useRef<HTMLDivElement>(null);

	const addMutation = useMutation({
		mutationFn: (name: string) => addTag(tsumegoId, name),
		onSuccess: (_data, name) =>
		{
			setTags(prev => prev.map(t =>
				t.name === name ? { ...t, isAdded: true, isApproved: isAdmin, isMine: true } : t
			));
			setQuery('');
			setOpen(false);
			inputRef.current?.focus();
		},
	});

	const removeMutation = useMutation({
		mutationFn: (name: string) => removeTag(tsumegoId, name),
		onSuccess: (_data, name) =>
		{
			setTags(prev => prev.map(t =>
				t.name === name ? { ...t, isAdded: false } : t
			));
		},
	});

	// Listen for problem-solved event from legacy JS
	useEffect(() =>
	{
		const handler = () => setTags(prev => prev.map(t => ({ ...t, isHint: false })));
		window.addEventListener('tag-editor-solved', handler);
		return () => window.removeEventListener('tag-editor-solved', handler);
	}, []);

	const isNonAddable = (tag: TagItem) => tag.isAdded && !tag.isApproved;

	const available = tags.filter(t => !t.isAdded || (!t.isMine && !t.isApproved));
	const visible = query
		? available.filter(t => t.name.toLowerCase().includes(query.toLowerCase()))
		: available;

	const handleAdd = useCallback((name: string) =>
	{
		addMutation.mutate(name);
	}, [addMutation]);

	const handleRemove = useCallback((tag: TagItem) =>
	{
		removeMutation.mutate(tag.name);
	}, [removeMutation]);

	const error = addMutation.error?.message || removeMutation.error?.message || null;

	const handleKeyDown = (e: React.KeyboardEvent) =>
	{
		if (!open || !visible.length) return;

		if (e.key === 'ArrowDown')
		{
			e.preventDefault();
			setSelectedIndex(i => Math.min(i + 1, visible.length - 1));
		}
		else if (e.key === 'ArrowUp')
		{
			e.preventDefault();
			setSelectedIndex(i => Math.max(i - 1, 0));
		}
		else if (e.key === 'Enter')
		{
			e.preventDefault();
			const selected = visible[selectedIndex];
			if (selected && !isNonAddable(selected))
				handleAdd(selected.name);
		}
		else if (e.key === 'Escape')
		{
			setOpen(false);
		}
	};

	// Reset selection when query changes
	useEffect(() => setSelectedIndex(0), [query]);

	// Close dropdown on outside click
	useEffect(() =>
	{
		const handler = (e: MouseEvent) =>
		{
			if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node))
				setOpen(false);
		};
		document.addEventListener('mousedown', handler);
		return () => document.removeEventListener('mousedown', handler);
	}, []);

	const addedTags = tags.filter(t =>
		t.isAdded && (t.isApproved || t.isMine) &&
		(t.isMine || problemSolved || (!isTimeMode && !t.isHint))
	);

	const hiddenCount = problemSolved ? 0 : isTimeMode
		? tags.filter(t => t.isAdded && !t.isMine && (t.isApproved || t.isMine)).length
		: tags.filter(t => t.isAdded && t.isHint && !t.isMine && (t.isApproved || t.isMine)).length;

	const tagList = (addedTags.length > 0 || hiddenCount > 0) && (
		<div style={{ marginBottom: 8 }} data-testid="tag-list">
			{addedTags.map(t => (
					<span key={t.id} className="tag-pill">
						<a href={`/tags/view/${t.id}`} data-testid={makeIdValidName(t.name)} id={makeIdValidName(t.name)}>{t.name}</a>
						{userId && ((t.isMine && !t.isApproved) || isAdmin) && (
							<button className="tag-pill__remove" onClick={() => handleRemove(t)} title="Remove tag" id={makeIdValidName(t.name).replace('tag-', 'remove-')}>×</button>
					)}
				</span>
			))}
			{hiddenCount > 0 && <span style={{ color: 'var(--text-softer-color)', fontSize: 12 }}>({hiddenCount} hidden)</span>}
		</div>
	);

	if (!isAllowedToContribute)
	{
		if (!tagList) return null;
		return <div data-testid="tag-editor">{tagList}</div>;
	}

	return (
		<div data-testid="tag-editor">
			{tagList}

			{error && <div style={{ color: 'var(--feedback-error)', marginBottom: 6 }} data-testid="tag-error">{error}</div>}

			{!canAddMoreTags && (
				<div style={{ color: 'var(--text-softer-color)', fontSize: 14 }}>Daily limit reached.</div>
			)}

			{canAddMoreTags && !error && (
			<>
			<div style={{ position: 'relative', display: 'inline-block', maxWidth: 300, width: '100%' }}>
				<input
					ref={inputRef}
					type="text"
					placeholder="Add tag..."
					data-testid="tag-search-input"
					value={query}
					onChange={e => { setQuery(e.target.value); setOpen(true); }}
					onFocus={() => setOpen(true)}
					onKeyDown={handleKeyDown}
					style={{ width: '100%', maxWidth: 300, padding: '6px 10px', background: 'var(--info-box-background)', border: '1px solid var(--current-border-color)', borderRadius: 4, color: 'var(--text-color)', fontSize: 14 }}
				/>

				{open && visible.length > 0 && (
					<div
						ref={dropdownRef}
						style={{ position: 'absolute', top: '100%', left: 0, zIndex: 50, width: '100%', maxWidth: 300, maxHeight: 200, overflowY: 'auto', background: 'var(--info-box-background)', border: '1px solid var(--current-border-color)', borderRadius: 4, marginTop: 2, boxShadow: '0 2px 8px rgba(0,0,0,0.15)' }}
					>
						{visible.map((tag, i) => {
							const tagStyle: React.CSSProperties = {
								padding: '6px 10px',
								cursor: 'pointer',
								fontSize: 14,
								color: 'var(--text-color)',
								background: i === selectedIndex ? 'rgba(128, 128, 128, 0.15)' : 'transparent',
							};

						if (isNonAddable(tag))
						{
							const label = tag.isMine ? 'pending' : 'already proposed';
							return (
								<div key={tag.id} style={{ ...tagStyle, color: tag.isMine ? 'var(--text-softer-color)' : 'var(--feedback-error)', cursor: 'default' }}>
									{tag.name} <span style={{ color: 'var(--text-softer-color)', fontSize: 12 }}>({label})</span>
								</div>
							);
						}

							return (
								<div
									key={tag.id}
									style={tagStyle}
									onClick={() => handleAdd(tag.name)}
									onMouseEnter={e => { (e.target as HTMLElement).style.background = 'rgba(128, 128, 128, 0.15)'; }}
									onMouseLeave={e => { (e.target as HTMLElement).style.background = i === selectedIndex ? 'rgba(128, 128, 128, 0.15)' : 'transparent'; }}
									id={makeIdValidName(tag.name)}
									role="option"
								>
									{tag.name}{tag.isHint && <span style={{ color: 'var(--text-softer-color)', fontSize: 12 }}> (hint)</span>}
								</div>
							);
						})}
					</div>
				)}
			</div>

			<div style={{ marginTop: 6 }}>
				<a href="/tags/add" style={{ color: 'var(--text-softer-color)', fontSize: 12 }} id="create-new-tag">+ New tag</a>
			</div>
			</>
			)}
		</div>
	);
}
