import { get, post } from '../shared/api';
import type { TagItem } from './tagTypes';

export async function fetchTags(tsumegoId: number): Promise<TagItem[]>
{
	return get<TagItem[]>(`/api/tags/${tsumegoId}`);
}

export async function addTag(tsumegoId: number, tagName: string): Promise<void>
{
	await post<{ success: boolean }>(`/tagConnection/add/${tsumegoId}/${encodeURIComponent(tagName)}`, {});
}

export async function removeTag(tsumegoId: number, tagName: string): Promise<void>
{
	await post<{ success: boolean }>(`/tagConnection/remove/${tsumegoId}/${encodeURIComponent(tagName)}`, {});
}
