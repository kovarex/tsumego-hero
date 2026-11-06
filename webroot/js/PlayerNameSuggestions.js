//The list of players under a field that takes names separated by commas (the invitations of a challenge).
//Typing a part of a name asks the server (data-player-suggestions, the address of the JSON, see
//PlayerNameSuggestions.php) for the players it can mean, friends first; picking one puts the whole name in
//place of the part and a comma after it, ready for the next name. It is the combobox of WAI-ARIA: the arrows
//move through the list, Enter or Tab picks the highlighted one, Escape closes it, and a screen reader hears the choice.
class PlayerNameSuggestions
{
	static MINIMUM_LENGTH = 2;
	static DELAY_MILLISECONDS = 200;

	static #count = 0;

	constructor(input)
	{
		this.input = input;
		this.url = input.dataset.playerSuggestions;
		this.players = [];
		this.active = -1;
		this.timer = null;
		this.request = null;

		const id = 'player-suggestions-' + ++PlayerNameSuggestions.#count;
		this.list = document.createElement('ul');
		this.list.id = id;
		this.list.className = 'player-suggestions';
		this.list.setAttribute('role', 'listbox');
		this.list.setAttribute('aria-label', 'Players');
		this.list.hidden = true;
		input.insertAdjacentElement('afterend', this.list);
		input.parentElement.classList.add('player-suggestions-field');

		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-controls', id);
		input.setAttribute('autocomplete', 'off');

		input.addEventListener('input', () => this.scheduleLookup());
		input.addEventListener('keydown', (event) => this.keydown(event));
		input.addEventListener('blur', () => this.close());
		//mousedown, not click: a click would blur the field first, which closes the list
		this.list.addEventListener('mousedown', (event) =>
		{
			const option = event.target.closest('[role="option"]');
			if (!option)
				return;
			event.preventDefault();
			this.pick(Number(option.dataset.index));
		});
	}

	static attachAll()
	{
		document.querySelectorAll('input[data-player-suggestions]').forEach((input) => new PlayerNameSuggestions(input));
	}

	//The name the caret is in: from the comma before it to the comma after it
	currentPart()
	{
		const value = this.input.value;
		const caret = this.input.selectionStart ?? value.length;
		const start = value.lastIndexOf(',', caret - 1) + 1;
		const commaAfter = value.indexOf(',', caret);
		const end = commaAfter === -1 ? value.length : commaAfter;
		return { start, end, text: value.slice(start, end).trim() };
	}

	//The names in the field already, so the list doesn't offer them again
	chosenNames(except)
	{
		return this.input.value.split(',').map((name) => name.trim().toLowerCase()).filter((name) => name !== '' && name !== except.toLowerCase());
	}

	scheduleLookup()
	{
		clearTimeout(this.timer);
		const typed = this.currentPart().text;
		if (typed.length < PlayerNameSuggestions.MINIMUM_LENGTH)
		{
			this.close();
			return;
		}
		this.timer = setTimeout(() => this.lookup(typed), PlayerNameSuggestions.DELAY_MILLISECONDS);
	}

	async lookup(typed)
	{
		//only the answer to what is typed now counts
		this.request?.abort();
		this.request = new AbortController();
		let players;
		try
		{
			const response = await fetch(this.url + '?query=' + encodeURIComponent(typed), { signal: this.request.signal, headers: { Accept: 'application/json' } });
			if (!response.ok)
				return;
			players = (await response.json()).players;
		}
		catch (error)
		{
			if (error.name === 'AbortError')
				return;
			throw error;
		}
		if (document.activeElement !== this.input || this.currentPart().text !== typed)
			return;
		const chosen = this.chosenNames(typed);
		this.show(players.filter((player) => !chosen.includes(player.name.toLowerCase())));
	}

	show(players)
	{
		this.players = players;
		this.active = -1;
		this.input.removeAttribute('aria-activedescendant');
		this.list.replaceChildren(...players.map((player, index) =>
		{
			const option = document.createElement('li');
			option.id = this.list.id + '-' + index;
			option.className = 'player-suggestions__option';
			option.setAttribute('role', 'option');
			option.setAttribute('aria-selected', 'false');
			option.dataset.index = String(index);
			const name = document.createElement('span');
			name.className = 'player-suggestions__name';
			name.textContent = player.name;
			const details = document.createElement('span');
			details.className = 'player-suggestions__details';
			details.textContent = player.rank + (player.friend ? ' · friend' : '');
			option.append(name, ' ', details);
			return option;
		}));
		const open = players.length > 0;
		this.list.hidden = !open;
		this.input.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	close()
	{
		clearTimeout(this.timer);
		this.request?.abort();
		this.show([]);
	}

	highlight(index)
	{
		const options = this.list.children;
		if (options.length === 0)
			return;
		this.active = (index + options.length) % options.length;
		for (const option of options)
			option.setAttribute('aria-selected', option.dataset.index === String(this.active) ? 'true' : 'false');
		this.input.setAttribute('aria-activedescendant', options[this.active].id);
		options[this.active].scrollIntoView({ block: 'nearest' });
	}

	keydown(event)
	{
		const open = !this.list.hidden;
		if (event.key === 'ArrowDown' && open)
			this.highlight(this.active + 1);
		else if (event.key === 'ArrowUp' && open)
			this.highlight(this.active - 1);
		else if (event.key === 'Enter' && open && this.active >= 0)
			this.pick(this.active);
		//Tab picks too, and goes on to the next field as it always does
		else if (event.key === 'Tab' && open && this.active >= 0)
		{
			this.pick(this.active);
			return;
		}
		else if (event.key === 'Escape' && open)
			this.close();
		else
			return;
		event.preventDefault();
	}

	//The name replaces the part being typed, and a comma follows it for the next one
	pick(index)
	{
		const player = this.players[index];
		if (!player)
			return;
		const part = this.currentPart();
		const value = this.input.value;
		const before = value.slice(0, part.start).replace(/\s*$/, '');
		const after = value.slice(part.end).replace(/^,?\s*/, '');
		const head = (before === '' ? '' : before + ' ') + player.name + ', ';
		this.input.value = head + after;
		this.input.setSelectionRange(head.length, head.length);
		this.close();
	}
}

if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', () => PlayerNameSuggestions.attachAll());
else
	PlayerNameSuggestions.attachAll();
