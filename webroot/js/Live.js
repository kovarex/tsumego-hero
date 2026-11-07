//Live.watch(channel, onChange): calls onChange when something the channel stands for changed, see
//src/Utility/Live.php, whose bump() rewrites the stamp of the channel, the static file /live/<channel>.
//The page asks for the stamps it watches every POLL_MS while it is visible, with a conditional request
//that Apache answers with a 304 from the file system, and not at all while it is hidden; coming back it
//asks at once. onChange gets nothing but the news: the caller fetches what changed itself.
//How the news arrives is the transport's business, the callers stay the same either way: the push hub
//(scripts/live-hub.mjs, behind /live/sse where the site runs it) tells the page the moment a stamp changes,
//and where there is none, or its stream fails, the page asks for the stamps itself (polling).
var Live = (function()
{
	var POLL_MS = 1500;

	//channel => {stamp: the text of the stamp last seen, undefined before the first answer, listeners: []}
	var channels = {};
	var timer = null;
	var asking = false;

	function stampUrl(channel)
	{
		return '/live/' + channel;
	}

	//One answer of a stamp: the listeners hear of it when it isn't the one seen before. The first answer
	//only says where the page starts from, a channel without a stamp yet (204) starts from nothing.
	function received(channel, stamp)
	{
		var watched = channels[channel];
		if (!watched)
			return;
		var before = watched.stamp;
		watched.stamp = stamp;
		if (before === undefined || before === stamp)
			return;
		watched.listeners.slice().forEach(function(listener) { listener(); });
	}

	//The transport of today: polling the stamps
	var polling = {
		check: function()
		{
			timer = null;
			var names = Object.keys(channels);
			if (asking || names.length === 0)
				return;
			asking = true;
			Promise.all(names.map(function(channel)
			{
				// 'no-cache': the browser asks with If-None-Match / If-Modified-Since and gets a 304 while the stamp is the same
				return fetch(stampUrl(channel), {cache: 'no-cache', credentials: 'omit'})
					.then(function(response)
					{
						if (response.status === 204)
							return '';
						if (!response.ok)
							throw new Error('HTTP ' + response.status);
						return response.text();
					})
					.then(function(stamp) { received(channel, stamp); })
					// a stamp that can't be read now is asked for again next time
					.catch(function() {});
			})).then(function()
			{
				asking = false;
				polling.schedule();
			});
		},
		schedule: function()
		{
			if (timer === null && document.visibilityState === 'visible' && Object.keys(channels).length > 0)
				timer = setTimeout(polling.check, POLL_MS);
		},
		start: function()
		{
			if (timer !== null)
				clearTimeout(timer);
			timer = null;
			polling.check();
		},
		stop: function()
		{
			if (timer !== null)
				clearTimeout(timer);
			timer = null;
		}
	};

	//The transport of the hub: one stream for every channel the page watches, opened again when it watches
	//another one. A stream that never opens (no hub: the 204 of webroot/live/.htaccess, a 404 or a 502 of a
	//proxy) or that the browser gives up on hands the page over to polling, for good.
	var push = {
		source: null,
		topics: '',
		opened: false,
		start: function()
		{
			var names = Object.keys(channels).sort().join(',');
			if (push.source && push.topics === names)
				return;
			push.stop();
			if (names === '')
				return;
			push.topics = names;
			push.opened = false;
			var source = new EventSource('/live/sse?topics=' + encodeURIComponent(names));
			push.source = source;
			source.addEventListener('open', function() { push.opened = true; });
			source.addEventListener('stamp', function(event)
			{
				var data = JSON.parse(event.data);
				received(data.topic, data.stamp);
			});
			source.addEventListener('error', function()
			{
				// a stream that worked reconnects by itself (retry:), one that never did or is closed for good is no hub
				if (push.opened && source.readyState !== EventSource.CLOSED)
					return;
				push.stop();
				Live.transport = polling;
				polling.start();
			});
		},
		stop: function()
		{
			if (push.source)
				push.source.close();
			push.source = null;
			push.topics = '';
		}
	};

	document.addEventListener('visibilitychange', function()
	{
		if (document.visibilityState === 'visible')
			Live.transport.start();
		else
			Live.transport.stop();
	});

	return {
		transport: typeof EventSource === 'function' ? push : polling,

		//Whether the page has the stamp of the channel, from which on its changes are news
		seen: function(channel)
		{
			return channels[channel] !== undefined && channels[channel].stamp !== undefined;
		},

		//Returns the function that stops watching
		watch: function(channel, onChange)
		{
			if (!channels[channel])
				channels[channel] = {stamp: undefined, listeners: []};
			channels[channel].listeners.push(onChange);
			Live.transport.start();
			return function()
			{
				var watched = channels[channel];
				if (!watched)
					return;
				watched.listeners = watched.listeners.filter(function(listener) { return listener !== onChange; });
				if (watched.listeners.length === 0)
					delete channels[channel];
			};
		}
	};
})();
