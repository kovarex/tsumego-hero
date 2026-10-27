//React Query hooks of the messages page. The conversations are polled while the page is
//open, so a reply shows up without reloading.

import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post } from '../shared/api';
import type { ConversationResponse, ConversationsResponse, Message, RecipientsResponse } from './messageTypes';

const POLL_MILLISECONDS = 20 * 1000;

export function useConversations()
{
	return useQuery({
		queryKey: ['messages', 'conversations'],
		queryFn: () => get<ConversationsResponse>('/messages/conversations'),
		refetchInterval: POLL_MILLISECONDS,
		refetchOnWindowFocus: true
	});
}

//The messages with one player. The first page is the newest messages, every next page
//is the messages before the oldest one loaded.
export function useConversation(partnerUserId: number)
{
	return useInfiniteQuery({
		queryKey: ['messages', 'conversation', partnerUserId],
		queryFn: ({ pageParam }) =>
			get<ConversationResponse>(`/messages/conversations/${partnerUserId}` + (pageParam ? `?before=${pageParam}` : '')),
		initialPageParam: 0,
		getNextPageParam: lastPage => (lastPage.hasOlder && lastPage.messages.length ? lastPage.messages[0].id : undefined),
		refetchInterval: POLL_MILLISECONDS,
		refetchOnWindowFocus: true
	});
}

export function useSendMessage(partnerUserId: number)
{
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (text: string) => post<{ message: Message }>(`/messages/conversations/${partnerUserId}`, { text }),
		onSuccess: () => queryClient.invalidateQueries({ queryKey: ['messages'] })
	});
}

export function useMarkRead(partnerUserId: number)
{
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: () => post<{ unreadCount: number }>(`/messages/conversations/${partnerUserId}/read`, {}),
		onSuccess: ({ unreadCount }) =>
		{
			queryClient.invalidateQueries({ queryKey: ['messages', 'conversations'] });
			showUnreadCountInMenu(unreadCount);
		}
	});
}

export function useRecipientSearch(query: string)
{
	const trimmed = query.trim();
	return useQuery({
		queryKey: ['messages', 'recipients', trimmed],
		queryFn: () => get<RecipientsResponse>(`/messages/recipients?query=${encodeURIComponent(trimmed)}`),
		enabled: trimmed.length > 0,
		staleTime: 60 * 1000
	});
}

//The menu counts the unread messages from the time the page loaded, this brings it up to date.
function showUnreadCountInMenu(unreadCount: number): void
{
	document.querySelectorAll<HTMLElement>('[data-unread-messages]').forEach(badge =>
	{
		badge.textContent = String(unreadCount);
		badge.hidden = unreadCount === 0;
	});
}
