//The items of a pager of `pages` pages on page `page`: the first and the last page, the current one with one on
//each side, and an ellipsis where pages are left out, as PaginationHelper (PHP) shows them. 1 … 4 5 6 … 12
export function pageItems(page: number, pages: number): (number | '…')[]
{
	const shown = [...new Set([1, page - 1, page, page + 1, pages])].filter(number => number >= 1 && number <= pages).sort((a, b) => a - b);
	const items: (number | '…')[] = [];
	for (const number of shown)
	{
		const previous = items[items.length - 1];
		if (typeof previous === 'number' && number > previous + 1)
			items.push('…');
		items.push(number);
	}
	return items;
}
