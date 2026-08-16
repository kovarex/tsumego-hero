import { get } from '../shared/api';
import type { TagItem } from './tagTypes';

export async function fetchTags(tsumegoId: number): Promise<TagItem[]>
{
	return get<TagItem[]>(`/api/tags/${tsumegoId}`);
}

export async function addTag(tsumegoId: number, tagName: string): Promise<void>
{
	const response = await fetch(`/tagConnection/add/${tsumegoId}/${encodeURIComponent(tagName)}`, {
		method: 'POST',
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
	});

	if (!response.ok)
	{
		const body = await response.text();
		throw new Error(body || `Failed to add tag "${tagName}"`);
	}
}

export async function removeTag(tsumegoId: number, tagName: string): Promise<void>
{
	const response = await fetch(`/tagConnection/remove/${tsumegoId}/${encodeURIComponent(tagName)}`, {
		method: 'POST',
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
	});

	if (!response.ok)
	{
		const body = await response.text();
		throw new Error(body || `Failed to remove tag "${tagName}"`);
	}
}
