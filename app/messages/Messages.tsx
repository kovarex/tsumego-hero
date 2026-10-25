import { useEffect, useState } from 'react';
import { Conversation } from './Conversation';
import { ConversationList } from './ConversationList';
import type { Player } from './messageTypes';

interface MessagesProps
{
	userId: number;
	/** The player whose conversation the address named, /messages/<id>. */
	initialPartner: Player | null;
	maxLength: number;
}

/**
 * The messages page: the conversations on the left, the open one on the right. On a
 * phone only one of them is shown at a time. The address follows the open conversation,
 * so it can be bookmarked and the back button closes it.
 */
export function Messages({ userId, initialPartner, maxLength }: MessagesProps)
{
	const [partner, setPartner] = useState<Player | null>(initialPartner);

	useEffect(() =>
	{
		const restore = (event: PopStateEvent) => setPartner((event.state as { partner?: Player } | null)?.partner ?? null);
		window.history.replaceState({ partner: initialPartner }, '');
		window.addEventListener('popstate', restore);
		return () => window.removeEventListener('popstate', restore);
	}, [initialPartner]);

	const open = (player: Player | null) =>
	{
		if (player?.id === partner?.id)
			return;
		setPartner(player);
		window.history.pushState({ partner: player }, '', player ? `/messages/${player.id}` : '/messages');
	};

	return (
		<div className={'messages' + (partner ? ' messages--conversation-open' : '')}>
			<ConversationList userId={userId} selectedPartnerId={partner?.id ?? null} onSelect={open} />
			{partner
				? <Conversation key={partner.id} userId={userId} partner={partner} maxLength={maxLength} onBack={() => open(null)} />
				: <div className="messages-conversation messages-conversation--none hint">Pick a conversation, or find a player to write to.</div>}
		</div>
	);
}
