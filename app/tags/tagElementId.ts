//Element id for a tag: "tag-" + the name with everything but letters and digits replaced by "-".
//The browser tests find tags by these ids.
export function makeIdValidName(name: string): string
{
	return 'tag-' + name.replace(/[^a-z0-9]/gi, '-');
}
