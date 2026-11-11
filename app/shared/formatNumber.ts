//A quantity for players: 999, 1000, 9999, then 12 345 and 1 234 567 (thousands get a narrow no-break space from five
//digits on, never a comma), a point before decimals digits, rounded half away from zero. The same as
//Util::formatNumber() in PHP and webroot/js/formatNumber.js.
export function formatNumber(value: number, decimals = 0): string
{
	if (!Number.isFinite(value))
		return String(value);
	//Rounded on the decimal text of the number, so 1.005 to two places is 1.01 as written, not 1.00 of its binary
	const rounded = Number(Math.round(Number(Math.abs(value) + 'e' + decimals)) + 'e-' + decimals);
	const parts = rounded.toFixed(decimals).split('.');
	if (parts[0].length >= 5)
		parts[0] = parts[0].replace(/\B(?=(\d{3})+$)/g, ' ');
	//A value that rounds to zero has no minus
	const negative = value < 0 && rounded !== 0;
	return (negative ? '-' : '') + parts.join('.');
}
