import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { formatNumber as formatNumberApp } from '../app/shared/formatNumber.ts';

//The legacy script defines a global function; it runs here in a context of its own
const context = {};
runInNewContext(readFileSync(new URL('../webroot/js/formatNumber.js', import.meta.url), 'utf8') + '\nthis.formatNumber = formatNumber;', context);
const formatNumberLegacy = context.formatNumber;

//The same cases UtilTest::testFormatNumber checks in PHP, so the three stay identical
const cases = JSON.parse(readFileSync(new URL('../tests/formatNumberCases.json', import.meta.url), 'utf8'));

for (const [value, decimals, expected] of cases)
	test(`${value} with ${decimals} decimals is ${JSON.stringify(expected)}`, () =>
	{
		assert.equal(formatNumberLegacy(value, decimals), expected);
		assert.equal(formatNumberApp(value, decimals), expected);
	});

test('no decimals by default', () =>
{
	assert.equal(formatNumberLegacy(1234567.4), '1 234 567');
	assert.equal(formatNumberApp(1234567.4), '1 234 567');
});
