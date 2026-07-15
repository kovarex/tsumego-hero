class PlayStateManager
{
	constructor(tsumegoId)
	{
		this.tsumegoId = tsumegoId;
		this.key = 'tsumegoState_' + tsumegoId;
	}

	save(misplays, seconds, rotation, playerColor)
	{
		try
		{
			localStorage.setItem(this.key, JSON.stringify({
				tsumegoId: this.tsumegoId,
				misplays: misplays,
				seconds: seconds,
				rotation: rotation,
				playerColor: playerColor,
				timestamp: Date.now(),
			}));
		}
		catch (e) { /* localStorage disabled or full */ }
	}

	restore()
	{
		try
		{
			var saved = localStorage.getItem(this.key);
			if (!saved)
				return null;

			var state = JSON.parse(saved);
			if (state.tsumegoId !== this.tsumegoId)
				return null;
			if (Date.now() - state.timestamp > 30 * 60 * 1000)
			{
				this.clear();
				return null;
			}
			return state;
		}
		catch (e)
		{
			this.clear();
			return null;
		}
	}

	clear()
	{
		try { localStorage.removeItem(this.key); } catch (e) {}
	}

	static cleanupStale(currentTsumegoId)
	{
		try
		{
			var keys = Object.keys(localStorage);
			for (var i = 0; i < keys.length; i++)
			{
				if (!keys[i].startsWith('tsumegoState_'))
					continue;

				if (parseInt(keys[i].split('_')[1]) === currentTsumegoId)
					continue;

				var state = JSON.parse(localStorage.getItem(keys[i]));
				if (Date.now() - state.timestamp > 30 * 60 * 1000)
					localStorage.removeItem(keys[i]);
			}
		}
		catch (e) {}
	}
}
