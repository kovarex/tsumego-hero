//A number with significant significant digits, e.g. with 2: 56, 5.6, 0.15, 0.015, and 123 (a whole number is never
//cut). Rounded half away from zero, the trailing zeros dropped (2, not 2.0), 9.96 gives 10. The same as
//Util::formatSignificant() in PHP.
function formatSignificant(value, significant = 2)
{
	if (!Number.isFinite(value))
		return String(value);
	const size = Math.abs(value);
	let magnitude = value === 0 ? 0 : Math.floor(Math.log10(size));
	let decimals = Math.max(0, significant - 1 - magnitude);
	//Rounded on the decimal text of the number, so 1.005 to two places is 1.01 as written, not 1.00 of its binary
	const roundTo = places => Number(Math.round(Number(size + 'e' + places)) + 'e-' + places);
	let rounded = roundTo(decimals);
	//Rounding up to the next power of ten keeps one digit fewer behind the point: 9.96 is 10, not 10.0
	if (rounded >= 10 ** (magnitude + 1))
	{
		decimals = Math.max(0, significant - 2 - magnitude);
		rounded = roundTo(decimals);
	}
	if (rounded === 0)
		return '0';
	let text = rounded.toFixed(decimals);
	if (text.includes('.'))
		text = text.replace(/0+$/, '').replace(/\.$/, '');
	return (value < 0 ? '-' : '') + text;
}
