import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

//The legacy script defines a global function; it runs here in a context of its own
const context = {};
runInNewContext(readFileSync(new URL('../webroot/js/formatSignificant.js', import.meta.url), 'utf8') + '\nthis.formatSignificant = formatSignificant;', context);
const { formatSignificant } = context;

//The same cases as UtilTest::testFormatSignificant in PHP
const cases = [
	[56, 2, '56'], [5.62, 2, '5.6'], [0.153, 2, '0.15'], [0.0153, 2, '0.015'], [123.4, 2, '123'],
	[2.0, 2, '2'], [0.10, 2, '0.1'], [9.96, 2, '10'], [0.999, 2, '1'], [99.5, 2, '100'], [0.0995, 2, '0.1'],
	[0, 2, '0'], [-5.62, 2, '-5.6'], [-0.153, 2, '-0.15'], [-9.96, 2, '-10'],
	[0.53, 1, '0.5'], [0.04, 1, '0.04'], [12.3, 1, '12'], [0.95, 1, '1'], [0.25, 1, '0.3'], [-0.25, 1, '-0.3'],
	[1.005, 3, '1.01'], [0.000123, 2, '0.00012'], [0.0004, 1, '0.0004'],
];

for (const [value, significant, expected] of cases)
	test(`${value} with ${significant} significant digits is ${expected}`, () =>
	{
		assert.equal(formatSignificant(value, significant), expected);
	});

test('two significant digits by default', () =>
{
	assert.equal(formatSignificant(5.62), '5.6');
});
