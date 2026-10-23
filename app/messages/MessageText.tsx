/**
 * The text of a message. It is plain text: the line breaks stay and the web addresses
 * in it become links, nothing else is interpreted.
 */

const ADDRESS = /(https?:\/\/[^\s<>"]+)/g;

/** Punctuation that ends a sentence rather than the address it follows. */
const TRAILING_PUNCTUATION = /[.,;:!?)\]'"]+$/;

export function MessageText({ text }: { text: string })
{
	const parts = text.split(ADDRESS);
	return (
		<div className="message__text">
			{parts.map((part, index) =>
			{
				// split() with a capturing group puts the addresses at the odd positions
				if (index % 2 === 0)
					return part;
				const trailing = part.match(TRAILING_PUNCTUATION)?.[0] ?? '';
				const address = part.slice(0, part.length - trailing.length);
				return (
					<span key={index}>
						<a href={address} target="_blank" rel="noopener noreferrer nofollow">{address}</a>
						{trailing}
					</span>
				);
			})}
		</div>
	);
}
