//The push hub of the change stamps (src/Utility/Live.php, webroot/js/Live.js): a small SSE server with no
//dependencies, which tells the pages connected to it the moment a stamp they watch changes, so they don't
//have to ask for it every 1.5 s. PHP never talks to it: it reads the same stamp files Apache serves,
//stating the ones somebody watches every POLL_MS, so a restart of it loses nothing and a page whose
//stream breaks falls back to asking for the stamps itself.
//  GET /live/sse?topics=challenge-123,challenge-456   text/event-stream
//    event: stamp, data: {"topic": "challenge-123", "stamp": "<the content of the stamp, '' without one>"}
//    once for each topic when the stream opens, then whenever a stamp changes; a comment every HEARTBEAT_MS.
//It runs as a daemon of NearlyFreeSpeech behind the proxy of /live/sse (see the description of PR #720):
//  node scripts/live-hub.mjs
//with the environment (all optional):
//  LIVE_HUB_PORT             the local port to listen on, 8091
//  LIVE_HUB_HOST             the address to listen on, 127.0.0.1
//  LIVE_DIR                  the directory of the stamps, webroot/live next to this script
//  LIVE_HUB_MAX_CONNECTIONS  streams open at once, beyond which a page gets a 503 and polls, 1000
//  LIVE_HUB_HEARTBEAT_MS     20000
//  LIVE_HUB_POLL_MS          500
import http from 'node:http';
import { stat, readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

//The same names as Live::CHANNEL_PATTERN, so a topic is never anything but a file name of the directory
const TOPIC_PATTERN = /^[a-z0-9-]{1,100}$/;
const MAXIMUM_TOPICS = 20;
const RETRY_MS = 3000;

/**
 * @param {{directory: string, maximumConnections?: number, heartbeatMs?: number, pollMs?: number}} options
 * @returns {{server: http.Server, close: () => Promise<void>, connectionCount: () => number}}
 */
export function createHub({directory, maximumConnections = 1000, heartbeatMs = 20000, pollMs = 500})
{
	//topic => {subscribers: Set<ServerResponse>, signature: string|null (size and time of the file), stamp: string}
	const topics = new Map();
	const connections = new Set();
	let checking = false;

	function send(response, topic, stamp)
	{
		response.write('event: stamp\ndata: ' + JSON.stringify({topic, stamp}) + '\n\n');
	}

	//What the stamp of the topic says now, and a signature that changes with every rewrite of it
	async function readStamp(topic)
	{
		const path = join(directory, topic);
		try
		{
			const stats = await stat(path);
			const signature = stats.size + ':' + stats.mtimeMs + ':' + stats.ino;
			return {signature, stamp: await readFile(path, 'utf8')};
		}
		catch
		{
			return {signature: null, stamp: ''};
		}
	}

	async function check()
	{
		if (checking)
			return;
		checking = true;
		try
		{
			for (const [topic, watched] of topics)
			{
				const now = await readStamp(topic);
				if (now.signature === watched.signature)
					continue;
				watched.signature = now.signature;
				if (now.stamp === watched.stamp)
					continue;
				watched.stamp = now.stamp;
				for (const response of watched.subscribers)
					send(response, topic, now.stamp);
			}
		}
		finally
		{
			checking = false;
		}
	}

	async function subscribe(response, names)
	{
		for (const topic of names)
		{
			let watched = topics.get(topic);
			if (!watched)
			{
				const now = await readStamp(topic);
				// another stream may have started watching it meanwhile
				watched = topics.get(topic) ?? {subscribers: new Set(), signature: now.signature, stamp: now.stamp};
				topics.set(topic, watched);
			}
			watched.subscribers.add(response);
			send(response, topic, watched.stamp);
		}
	}

	function unsubscribe(response, names)
	{
		connections.delete(response);
		for (const topic of names)
		{
			const watched = topics.get(topic);
			if (!watched)
				continue;
			watched.subscribers.delete(response);
			if (watched.subscribers.size === 0)
				topics.delete(topic);
		}
	}

	function refuse(response, status, message, headers = {})
	{
		response.writeHead(status, {'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store', ...headers});
		response.end(message + '\n');
	}

	const server = http.createServer((request, response) =>
	{
		const url = new URL(request.url ?? '/', 'http://hub');
		if (url.pathname !== '/live/sse' && url.pathname !== '/live/sse/')
			return refuse(response, 404, 'Not found');
		if (request.method !== 'GET')
			return refuse(response, 405, 'Only GET', {Allow: 'GET'});
		const names = [...new Set((url.searchParams.get('topics') ?? '').split(',').filter(name => name !== ''))];
		if (names.length === 0 || names.length > MAXIMUM_TOPICS || !names.every(name => TOPIC_PATTERN.test(name)))
			return refuse(response, 400, 'topics: 1 to ' + MAXIMUM_TOPICS + ' names of a-z, 0-9 and -, separated by commas');
		if (connections.size >= maximumConnections)
			return refuse(response, 503, 'Too many streams, ask for the stamps instead', {'Retry-After': '60'});

		response.writeHead(200, {
			'Content-Type': 'text/event-stream; charset=utf-8',
			'Cache-Control': 'no-cache, no-transform',
			'X-Accel-Buffering': 'no',
			'X-Content-Type-Options': 'nosniff',
		});
		response.write('retry: ' + RETRY_MS + '\n\n');
		connections.add(response);
		request.socket.setNoDelay(true);
		request.socket.setKeepAlive(true);
		response.on('close', () => unsubscribe(response, names));
		subscribe(response, names).catch(() => response.end());
	});

	const pollTimer = setInterval(() => { check().catch(() => {}); }, pollMs);
	const heartbeatTimer = setInterval(() =>
	{
		for (const response of connections)
			response.write(': heartbeat\n\n');
	}, heartbeatMs);

	return {
		server,
		connectionCount: () => connections.size,
		//Stops taking streams, ends the open ones (their pages reconnect, to the next hub or to polling) and stops
		close: () => new Promise(resolve =>
		{
			clearInterval(pollTimer);
			clearInterval(heartbeatTimer);
			for (const response of connections)
				response.end();
			connections.clear();
			topics.clear();
			server.close(() => resolve());
			server.closeAllConnections?.();
		}),
	};
}

//Run as a program, not imported by the tests
if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1])
{
	const environment = process.env;
	const hub = createHub({
		directory: environment.LIVE_DIR ?? join(dirname(fileURLToPath(import.meta.url)), '..', 'webroot', 'live'),
		maximumConnections: Number(environment.LIVE_HUB_MAX_CONNECTIONS ?? 1000),
		heartbeatMs: Number(environment.LIVE_HUB_HEARTBEAT_MS ?? 20000),
		pollMs: Number(environment.LIVE_HUB_POLL_MS ?? 500),
	});
	const port = Number(environment.LIVE_HUB_PORT ?? 8091);
	const host = environment.LIVE_HUB_HOST ?? '127.0.0.1';
	hub.server.listen(port, host, () => console.log('live hub listening on ' + host + ':' + hub.server.address().port));
	const stop = signal =>
	{
		console.log('live hub stopping on ' + signal);
		hub.close().then(() => process.exit(0));
		// a stream that won't close doesn't keep the daemon from restarting
		setTimeout(() => process.exit(0), 5000).unref();
	};
	process.on('SIGTERM', () => stop('SIGTERM'));
	process.on('SIGINT', () => stop('SIGINT'));
}
