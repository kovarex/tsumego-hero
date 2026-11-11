import { test } from 'node:test';
import assert from 'node:assert/strict';
import { pageItems } from '../app/shared/pageItems.ts';

test('one page is just that page', () =>
{
	assert.deepEqual(pageItems(1, 1), [1]);
});

test('few pages are all shown', () =>
{
	assert.deepEqual(pageItems(1, 3), [1, 2, 3]);
	assert.deepEqual(pageItems(2, 4), [1, 2, 3, 4]);
});

test('the first and the last page, the current one with its neighbours, and ellipses between', () =>
{
	assert.deepEqual(pageItems(1, 12), [1, 2, '…', 12]);
	assert.deepEqual(pageItems(5, 12), [1, '…', 4, 5, 6, '…', 12]);
	assert.deepEqual(pageItems(12, 12), [1, '…', 11, 12]);
	assert.deepEqual(pageItems(3, 12), [1, 2, 3, 4, '…', 12]);
});
