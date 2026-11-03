//The tag overview for admins (Tags/index.php): a count of problems opens the list of them, loaded when it is first opened.
document.querySelectorAll('.tag-overview__toggle').forEach(toggle => toggle.addEventListener('click', async () =>
{
	const row = document.getElementById('tag-problems-' + toggle.dataset.tagId);
	row.hidden = !row.hidden;
	toggle.setAttribute('aria-expanded', String(!row.hidden));
	if (row.hidden || row.dataset.loaded)
		return;
	row.dataset.loaded = 'true';

	const list = row.querySelector('ol');
	list.textContent = 'Loading…';
	const response = await fetch('/tags/problems/' + toggle.dataset.tagId);
	if (!response.ok)
	{
		list.textContent = 'Loading the problems failed.';
		delete row.dataset.loaded;
		return;
	}
	list.textContent = '';
	for (const problem of await response.json())
	{
		const item = document.createElement('li');
		const link = document.createElement('a');
		link.href = problem.url;
		link.textContent = problem.name;
		item.append(link);
		if (!problem.approved)
		{
			const note = document.createElement('i');
			note.className = 'tag-overview__note';
			note.textContent = ' (proposed)';
			item.append(note);
		}
		list.append(item);
	}
}));
