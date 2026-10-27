import dayjs from 'dayjs';
import { Avatar } from '../shared/Avatar';
import { ErrorMessage } from '../shared/ErrorMessage';
import { formatDate } from '../shared/dates';
import type { ConversationSummary, Player } from './messageTypes';
import { RecipientSearch } from './RecipientSearch';
import { useConversations } from './useMessages';

const DELETED_NAME = '[account deleted]';

interface ConversationListProps
{
	userId: number;
	selectedPartnerId: number | null;
	onSelect: (player: Player) => void;
}

//Today's messages show their time, older ones their day.
function shortDate(created: string): string
{
	return formatDate(created, dayjs(created).isSame(dayjs(), 'day') ? 'time' : 'date');
}

export function ConversationList({ userId, selectedPartnerId, onSelect }: ConversationListProps)
{
	const conversations = useConversations();

	return (
		<div className="messages-list">
			<RecipientSearch onSelect={onSelect} />
			{conversations.isLoading && <div className="loading">Loading conversations...</div>}
			{conversations.error && <ErrorMessage message={conversations.error.message} onRetry={() => conversations.refetch()} />}
			{conversations.data?.conversations.length === 0 && (
				<p className="hint messages-list__empty">No messages yet. Find a player above to write to them.</p>
			)}
			<ul className="messages-list__items">
				{conversations.data?.conversations.map(conversation => (
					<ConversationRow
						key={conversation.partner.id}
						conversation={conversation}
						userId={userId}
						selected={conversation.partner.id === selectedPartnerId}
						onSelect={onSelect}
					/>
				))}
			</ul>
		</div>
	);
}

interface ConversationRowProps
{
	conversation: ConversationSummary;
	userId: number;
	selected: boolean;
	onSelect: (player: Player) => void;
}

function ConversationRow({ conversation, userId, selected, onSelect }: ConversationRowProps)
{
	const { partner, lastMessage, unreadCount } = conversation;
	const classes = ['messages-list__item'];
	if (selected)
		classes.push('messages-list__item--selected');
	if (unreadCount > 0)
		classes.push('messages-list__item--unread');

	return (
		<li>
			<button type="button" className={classes.join(' ')} onClick={() => onSelect(partner)} data-partner-id={partner.id}>
				<span className="messages-list__name">
					<Avatar url={partner.avatarUrl} fallbackUrl={`/avatar/${partner.id}`} />
					{partner.displayName ?? DELETED_NAME}
					{unreadCount > 0 && <span className="messages-list__unread">{unreadCount}</span>}
				</span>
				<time className="messages-list__date" dateTime={lastMessage.created}>{shortDate(lastMessage.created)}</time>
				<span className="messages-list__preview">
					{lastMessage.senderUserId === userId && 'You: '}
					{lastMessage.text}
				</span>
			</button>
		</li>
	);
}
