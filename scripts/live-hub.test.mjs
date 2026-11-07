import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { mkdtemp, rename, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createHub } from './live-hub.mjs';

//A hub on a free port over a stamp directory of its own, quick enough for a test
async function startHub(options = {})
{
	const directory = await mkdtemp(join(tmpdir(), 'live-hub-'));
	const hub = createHub({directory, heartbeatMs: 150, pollMs: 30, ...options});
	await new Promise(resolve => hub.server.listen(0, '127.0.0.1', resolve));
	const port = hub.server.address().port;
	return {
		directory,
		hub,
		port,
		async stop()
		{
			await hub.close();
			await rm(directory, {recursive: true, force: true});
		},
	};
}

//Bumps the stamp the way Live::bump() does, through a file renamed over it
async function bump(directory, topic, content)
{
	const temporary = join(directory, '.' + topic + '-tmp');
	await writeFile(temporary, content);
	await rename(temporary, join(directory, topic));
}

//An SSE stream, with what it received so far and a way to wait for more
function open(port, path)
{
	return new Promise((resolve, reject) =>
	{
		const request = http.get({host: '127.0.0.1', port, path}, response =>
		{
			let text = '';
			const waiting = [];
			response.setEncoding('utf8');
			response.on('data', chunk =>
			{
				text += chunk;
				for (const wait of waiting.splice(0))
					wait();
			});
			resolve({
				response,
				text: () => text,
				until: async (condition, what) =>
				{
					const deadline = Date.now() + 3000;
					while (!condition(text))
					{
						if (Date.now() > deadline)
							throw new Error('No ' + what + ' in: ' + text);
						await new Promise(wake => { waiting.push(wake); setTimeout(wake, 50); });
					}
				},
				close: () => request.destroy(),
			});
		});
		request.on('error', reject);
	});
}

function stampEvents(text)
{
	return [...text.matchAll(/event: stamp\ndata: (.*)\n\n/g)].map(match => JSON.parse(match[1]));
}

test('a stream gets the stamps it watches, then each change of them, and heartbeats', async () =>
{
	const {directory, port, stop} = await startHub();
	try
	{
		await bump(directory, 'challenge-1', '{"version":1}');
		const stream = await open(port, '/live/sse?topics=challenge-1,challenge-2');
		assert.equal(stream.response.statusCode, 200);
		assert.match(stream.response.headers['content-type'], /^text\/event-stream/);
		assert.equal(stream.response.headers['cache-control'], 'no-cache, no-transform');
		assert.equal(stream.response.headers['x-accel-buffering'], 'no');

		await stream.until(text => stampEvents(text).length === 2, 'first stamps');
		assert.match(stream.text(), /^retry: \d+\n\n/);
		assert.deepEqual(stampEvents(stream.text()), [
			{topic: 'challenge-1', stamp: '{"version":1}'},
			{topic: 'challenge-2', stamp: ''}]);

		await bump(directory, 'challenge-2', '{"version":1}');
		await stream.until(text => stampEvents(text).length === 3, 'the change of challenge-2');
		assert.deepEqual(stampEvents(stream.text())[2], {topic: 'challenge-2', stamp: '{"version":1}'});

		await bump(directory, 'challenge-1', '{"version":2}');
		await stream.until(text => stampEvents(text).length === 4, 'the change of challenge-1');
		assert.deepEqual(stampEvents(stream.text())[3], {topic: 'challenge-1', stamp: '{"version":2}'});

		// a stamp nobody watches changes nothing on the stream
		await bump(directory, 'challenge-3', '{"version":1}');
		await stream.until(text => text.includes(': heartbeat\n\n'), 'heartbeat');
		assert.equal(stampEvents(stream.text()).length, 4);
		stream.close();
	}
	finally
	{
		await stop();
	}
});

test('two streams of the same topic both hear of its change, and a closed one is forgotten', async () =>
{
	const {directory, hub, port, stop} = await startHub();
	try
	{
		const first = await open(port, '/live/sse?topics=challenge-5');
		const second = await open(port, '/live/sse?topics=challenge-5');
		await first.until(text => stampEvents(text).length === 1, 'first stamp');
		await second.until(text => stampEvents(text).length === 1, 'first stamp');
		assert.equal(hub.connectionCount(), 2);

		first.close();
		await new Promise(resolve => setTimeout(resolve, 100));
		assert.equal(hub.connectionCount(), 1);
		await bump(directory, 'challenge-5', 'x');
		await second.until(text => stampEvents(text).length === 2, 'the change');
		second.close();
	}
	finally
	{
		await stop();
	}
});

test('only topics that are plain names, only GET, only /live/sse, only so many streams', async () =>
{
	const {port, stop} = await startHub({maximumConnections: 1});
	try
	{
		const status = async path => (await open(port, path)).response.statusCode;
		assert.equal(await status('/live/sse?topics=../index.php'), 400);
		assert.equal(await status('/live/sse?topics=Challenge-1'), 400);
		assert.equal(await status('/live/sse'), 400);
		assert.equal(await status('/live/sse?topics=' + Array.from({length: 21}, (_, index) => 'c-' + index).join(',')), 400);
		assert.equal(await status('/elsewhere?topics=challenge-1'), 404);

		const posted = await new Promise(resolve => http.request({host: '127.0.0.1', port, path: '/live/sse?topics=a', method: 'POST'},
			response => { response.resume(); resolve(response.statusCode); }).end());
		assert.equal(posted, 405);

		const kept = await open(port, '/live/sse?topics=a');
		const refused = await open(port, '/live/sse?topics=a');
		assert.equal(refused.response.statusCode, 503);
		assert.equal(refused.response.headers['retry-after'], '60');
		kept.close();
	}
	finally
	{
		await stop();
	}
});

test('closing the hub ends its streams', async () =>
{
	const {port, stop} = await startHub();
	const stream = await open(port, '/live/sse?topics=a');
	const ended = new Promise(resolve => stream.response.on('end', resolve));
	await stop();
	await ended;
});
