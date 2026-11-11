import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { getRankFromRating, getReadableRankFromRating, getRankMinimalRating, rankTicks } from '../app/shared/rank.ts';

//The legacy Rating.js, the same ranks the rest of the site shows
const context = {};
runInNewContext(readFileSync(new URL('../webroot/js/Rating.js', import.meta.url), 'utf8') + '\nthis.Rating = Rating;', context);

test('the ranks are those of Rating.js', () =>
{
	for (let rating = -1200; rating <= 3300; rating += 7)
	{
		assert.equal(getRankFromRating(rating), context.Rating.getRankFromRating(rating), String(rating));
		assert.equal(getReadableRankFromRating(rating), context.Rating.getReadableRankFromRating(rating), String(rating));
	}
	for (let rank = 1; rank <= 45; rank++)
		assert.equal(getRankMinimalRating(rank), context.Rating.getRankMinimalRating(rank), String(rank));
});

test('the edges of the ranks', () =>
{
	assert.equal(getRankMinimalRating(25), 1450, '6k');
	assert.equal(getReadableRankFromRating(1450), '6k');
	assert.equal(getReadableRankFromRating(1449), '7k');
	assert.equal(getReadableRankFromRating(2050), '1d');
	assert.equal(getReadableRankFromRating(2760), '8d');
	assert.equal(getReadableRankFromRating(2875), '12d', 'from 8d up a rank is 30 wide');
});

test('a narrow range has a tick at each rank edge around it', () =>
{
	const ticks = rankTicks(1400, 1800, 7);
	assert.deepEqual(ticks.map(getReadableRankFromRating), ['7k', '6k', '5k', '4k', '3k', '2k']);
	assert.deepEqual(ticks, [1350, 1450, 1550, 1650, 1750, 1850]);
});

test('a wide range thins the ranks out, keeping 1d', () =>
{
	const ticks = rankTicks(1100, 3150, 8);
	assert.ok(ticks.length <= 8, String(ticks.length));
	assert.ok(ticks[0] <= 1100 && ticks[ticks.length - 1] >= 3150);
	assert.ok(ticks.includes(getRankMinimalRating(31)), '1d is a tick');
	assert.deepEqual(ticks.map(getReadableRankFromRating).slice(0, 4), ['10k', '5k', '1d', '6d']);
});

test('the 30 point ranks from 8d up are left out where they would crowd', () =>
{
	const ticks = rankTicks(2660, 2800, 4);
	assert.deepEqual(ticks.map(getReadableRankFromRating), ['7d', '8d', '9d', '10d']);
	const crowded = rankTicks(1100, 3150, 8);
	for (let index = 1; index < crowded.length; index++)
		assert.ok(crowded[index] - crowded[index - 1] >= (crowded[crowded.length - 1] - crowded[0]) / 8 * 0.6, crowded.join(','));
});

test('a flat line still gets two ticks', () =>
{
	assert.deepEqual(rankTicks(1500, 1500, 8), [1450, 1550]);
});

test('the axis ends at the first rank edge at or over the highest rating', () =>
{
	for (const [min, max] of [[1100, 3180], [2600, 3100], [1400, 1500], [2000, 2050]])
	{
		const ticks = rankTicks(min, max, 7);
		const end = ticks[ticks.length - 1];
		assert.ok(end >= max, `${min}-${max}: ${ticks.join(',')}`);
		assert.ok(getRankMinimalRating(getRankFromRating(end) - 1) < max, `${min}-${max}: no rank edge between the highest rating and the end, ${ticks.join(',')}`);
	}
	assert.deepEqual(rankTicks(1100, 3180, 7).map(getReadableRankFromRating), ['10k', '1d', '11d', '23d'], 'not 31d, the next tenth rank');
});
