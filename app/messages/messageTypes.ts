//The shapes MessagesController sends, see Message::playerPayload and Message::messagePayload.

export interface Player
{
	id: number;
	//Null for a deleted account.
	displayName: string | null;
	avatarUrl: string | null;
	rating: number;
}

export interface Message
{
	id: number;
	senderUserId: number;
	text: string;
	created: string;
	//When the recipient opened the conversation, null while unread.
	readAt: string | null;
}

export interface ConversationSummary
{
	partner: Player;
	lastMessage: Message;
	//Messages of the partner the player did not read yet.
	unreadCount: number;
}

export interface ConversationsResponse
{
	conversations: ConversationSummary[];
}

export interface ConversationResponse
{
	partner: Player;
	messages: Message[];
	hasOlder: boolean;
}

export interface RecipientsResponse
{
	players: Player[];
}
