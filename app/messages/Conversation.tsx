import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { ErrorMessage } from '../shared/ErrorMessage';
import { UserLink } from '../shared/UserLink';
import { formatDate } from '../shared/dates';
import { MessageText } from './MessageText';
import type { Message, Player } from './messageTypes';
import { useConversation, useMarkRead, useSendMessage } from './useMessages';

interface ConversationProps
{
	userId: number;
	partner: Player;
	maxLength: number;
	onBack: () => void;
}

export function Conversation({ userId, partner, maxLength, onBack }: ConversationProps)
{
	const conversation = useConversation(partner.id);
	const markRead = useMarkRead(partner.id);
	const listRef = useRef<HTMLDivElement>(null);

	// Pages come newest first, each holding its messages oldest first.
	const pages = conversation.data?.pages ?? [];
	const messages: Message[] = [...pages].reverse().flatMap(page => page.messages);
	// The server knows the partner better than the link that opened the conversation (a deleted account).
	const currentPartner = pages[0]?.partner ?? partner;
	const newestMessageId = messages.length ? messages[messages.length - 1].id : 0;
	const hasUnread = messages.some(message => message.senderUserId === partner.id && message.readAt === null);

	// The newest message is kept in view: when the conversation opens and when one arrives.
	useLayoutEffect(() =>
	{
		if (listRef.current)
			listRef.current.scrollTop = listRef.current.scrollHeight;
	}, [newestMessageId]);

	// Seeing a message is reading it.
	useEffect(() =>
	{
		if (hasUnread && !markRead.isPending)
			markRead.mutate();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [hasUnread, newestMessageId]);

	const lastOwnMessage = [...messages].reverse().find(message => message.senderUserId === userId);

	return (
		<div className="messages-conversation">
			<div className="messages-conversation__header">
				<button type="button" className="link messages-conversation__back" onClick={onBack}>&larr; Conversations</button>
				<UserLink userId={currentPartner.id} name={currentPartner.displayName} avatarUrl={currentPartner.avatarUrl} rating={currentPartner.rating} />
			</div>
			<div className="messages-conversation__messages" ref={listRef}>
				{conversation.hasNextPage && (
					<button
						type="button"
						className="btn btn--small messages-conversation__older"
						onClick={() => conversation.fetchNextPage()}
						disabled={conversation.isFetchingNextPage}
					>
						Older messages
					</button>
				)}
				{conversation.isLoading && <div className="loading">Loading messages...</div>}
				{conversation.error && <ErrorMessage message={conversation.error.message} onRetry={() => conversation.refetch()} />}
				{conversation.isSuccess && messages.length === 0 && (
					<p className="hint messages-conversation__empty">No messages yet.</p>
				)}
				{messages.map(message => (
					<MessageBubble key={message.id} message={message} own={message.senderUserId === userId} />
				))}
				{lastOwnMessage?.readAt && lastOwnMessage.id === newestMessageId && (
					<div className="hint messages-conversation__seen">Seen {formatDate(lastOwnMessage.readAt)}</div>
				)}
			</div>
			{currentPartner.displayName === null
				? <p className="hint messages-conversation__closed">This account was deleted, it can't get messages.</p>
				: <MessageForm partnerUserId={partner.id} maxLength={maxLength} />}
		</div>
	);
}

function MessageBubble({ message, own }: { message: Message; own: boolean })
{
	return (
		<div className={'message' + (own ? ' message--own' : '')} data-message-id={message.id}>
			<MessageText text={message.text} />
			<time className="message__time" dateTime={message.created}>{formatDate(message.created)}</time>
		</div>
	);
}

function MessageForm({ partnerUserId, maxLength }: { partnerUserId: number; maxLength: number })
{
	const [text, setText] = useState('');
	const send = useSendMessage(partnerUserId);

	const submit = () =>
	{
		if (!text.trim() || send.isPending)
			return;
		send.mutate(text, { onSuccess: () => setText('') });
	};

	return (
		<form
			className="messages-form"
			onSubmit={event =>
			{
				event.preventDefault();
				submit();
			}}
		>
			<textarea
				className="messages-form__text"
				value={text}
				onChange={event => setText(event.target.value)}
				onKeyDown={event =>
				{
					if (event.key === 'Enter' && (event.ctrlKey || event.metaKey))
					{
						event.preventDefault();
						submit();
					}
				}}
				placeholder="Write a message"
				aria-label="Message"
				rows={3}
				maxLength={maxLength}
			/>
			{send.error && <p className="hint hint--danger messages-form__error">{send.error.message}</p>}
			<div className="messages-form__actions">
				<span className="hint">Ctrl+Enter sends</span>
				<button type="submit" className="btn btn--small" disabled={!text.trim() || send.isPending}>Send</button>
			</div>
		</form>
	);
}
