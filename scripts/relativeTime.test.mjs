import { test } from 'node:test';
import assert from 'node:assert/strict';
import { relativeTime } from '../app/shared/relativeTime.ts';

//The moments are local times, as the browser reads them
const NOW = new Date(2026, 10, 6, 14, 30);
const before = (minutes) => new Date(NOW.getTime() - minutes * 60 * 1000);
const at = (day, hour, minute = 0) => new Date(2026, 10, day, hour, minute);

test('the last minute is just now', () =>
{
	assert.equal(relativeTime(NOW, NOW), 'just now');
	assert.equal(relativeTime(before(0.99), NOW), 'just now');
	assert.equal(relativeTime(before(1), NOW), 'a minute ago');
});

test('the minutes go up to the hour', () =>
{
	assert.equal(relativeTime(before(5), NOW), '5 minutes ago');
	assert.equal(relativeTime(before(59), NOW), '59 minutes ago');
	assert.equal(relativeTime(before(60), NOW), 'an hour ago');
	assert.equal(relativeTime(before(119), NOW), 'an hour ago');
	assert.equal(relativeTime(before(120), NOW), '2 hours ago');
	assert.equal(relativeTime(at(6, 0, 0), NOW), '14 hours ago');
});

test('yesterday is the calendar day before, even when it was only two hours ago', () =>
{
	const justAfterMidnight = at(7, 1, 0);
	assert.equal(relativeTime(at(6, 23, 0), justAfterMidnight), 'yesterday');
	assert.equal(relativeTime(at(7, 0, 10), justAfterMidnight), '50 minutes ago');
	assert.equal(relativeTime(at(6, 23, 59), at(7, 0, 30)), '31 minutes ago');
	assert.equal(relativeTime(at(5, 0, 0), NOW), 'yesterday');
	assert.equal(relativeTime(at(4, 23, 59), NOW), '2 days ago');
});

test('the days go up to a week', () =>
{
	assert.equal(relativeTime(at(1, 9), NOW), '5 days ago');
	assert.equal(relativeTime(at(0, 23), NOW), '6 days ago');
	assert.equal(relativeTime(new Date(2026, 9, 30, 23, 59), NOW), 'a week ago');
	assert.equal(relativeTime(new Date(2026, 9, 23), NOW), '2 weeks ago');
});

test('the weeks go up to a month, the months up to a year', () =>
{
	assert.equal(relativeTime(new Date(2026, 9, 8), NOW), '4 weeks ago');
	assert.equal(relativeTime(new Date(2026, 9, 7), NOW), 'a month ago');
	assert.equal(relativeTime(new Date(2026, 8, 1), NOW), '2 months ago');
	assert.equal(relativeTime(new Date(2025, 10, 6), NOW), 'a year ago');
	assert.equal(relativeTime(new Date(2023, 10, 1), NOW), '3 years ago');
});
