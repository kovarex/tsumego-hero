import { useEffect, useState } from 'react';
import { Avatar } from '../shared/Avatar';
import type { Player } from './messageTypes';
import { useRecipientSearch } from './useMessages';

const TYPING_PAUSE_MILLISECONDS = 250;

/**
 * Finds a player to write to by their name. Picking one opens the conversation with
 * them, a new one when there was none.
 */
export function RecipientSearch({ onSelect }: { onSelect: (player: Player) => void })
{
	const [input, setInput] = useState('');
	const [query, setQuery] = useState('');

	// The search waits until the typing pauses, so a name is not looked up letter by letter.
	useEffect(() =>
	{
		const timer = window.setTimeout(() => setQuery(input), TYPING_PAUSE_MILLISECONDS);
		return () => window.clearTimeout(timer);
	}, [input]);

	const search = useRecipientSearch(query);
	const players = query.trim() ? search.data?.players ?? [] : [];

	const select = (player: Player) =>
	{
		setInput('');
		setQuery('');
		onSelect(player);
	};

	return (
		<div className="messages-search">
			<input
				type="search"
				className="messages-search__input"
				placeholder="Find a player to write to"
				aria-label="Find a player to write to"
				value={input}
				onChange={event => setInput(event.target.value)}
				onKeyDown={event =>
				{
					if (event.key === 'Enter' && players.length)
						select(players[0]);
					else if (event.key === 'Escape')
						setInput('');
				}}
			/>
			{query.trim() !== '' && !search.isLoading && (
				<ul className="messages-search__results">
					{players.length === 0 && <li className="messages-search__empty">No player found</li>}
					{players.map(player => (
						<li key={player.id}>
							<button type="button" className="messages-search__result" onClick={() => select(player)}>
								<Avatar url={player.avatarUrl} fallbackUrl={`/avatar/${player.id}`} />
								{player.displayName}
							</button>
						</li>
					))}
				</ul>
			)}
		</div>
	);
}
