//Showing, hiding and fading elements the way jQuery 2.1.3 did, which the scripts were written against:
//- hide() remembers the display the element had, and show() gives it back; an element that CSS or an
//  inline style hides is shown with the default display of its tag (block for a div, inline for a span);
//- fadeIn() shows the element at the start and takes its opacity from 0 up, fadeOut() takes it down
//  and hides the element at the end, then gives the opacity back; an element already shown (hidden)
//  is left as it is by fadeIn (fadeOut);
//- the fades of an element run one after another, so a quick fadeIn and fadeOut ends hidden, and
//  whether an element is hidden is looked at when its fade starts.
//A target is an element, a CSS selector or a list of elements; the fades return a promise of their end.
class Display
{
	static DEFAULT_DURATION = 400;
	static oldDisplay = new WeakMap();
	static queues = new WeakMap();
	static defaultDisplays = {};

	static elements(target)
	{
		if (!target)
			return [];
		if (typeof target === 'string')
			return Array.from(document.querySelectorAll(target));
		if (target instanceof Element)
			return [target];
		return Array.from(target);
	}

	static isHidden(element)
	{
		return !element.isConnected || getComputedStyle(element).display === 'none';
	}

	//The display a tag has when nothing hides it
	static defaultDisplay(nodeName)
	{
		if (!Display.defaultDisplays[nodeName])
		{
			let probe = document.createElement(nodeName);
			document.body.appendChild(probe);
			let display = getComputedStyle(probe).display;
			probe.remove();
			Display.defaultDisplays[nodeName] = !display || display === 'none' ? 'block' : display;
		}
		return Display.defaultDisplays[nodeName];
	}

	static show(target)
	{
		Display.elements(target).forEach(element => Display.showElement(element));
	}

	static hide(target)
	{
		Display.elements(target).forEach(element => Display.hideElement(element));
	}

	static showElement(element)
	{
		let display = Display.oldDisplay.get(element);
		if (!display && element.style.display === 'none')
			element.style.display = '';
		if (element.style.display === '' && Display.isHidden(element))
		{
			display = display || Display.defaultDisplay(element.nodeName);
			Display.oldDisplay.set(element, display);
		}
		if (element.style.display === 'none' || element.style.display === '')
			element.style.display = display || '';
	}

	static hideElement(element)
	{
		let display = element.style.display;
		let hidden = Display.isHidden(element);
		if (display !== 'none' || !hidden)
			Display.oldDisplay.set(element, hidden ? display : getComputedStyle(element).display);
		element.style.display = 'none';
	}

	static fadeIn(target, duration = Display.DEFAULT_DURATION)
	{
		return Promise.all(Display.elements(target).map(element => Display.queue(element, () =>
		{
			if (!Display.isHidden(element))
				return null;
			let opacity = element.style.opacity;
			element.style.opacity = '0';
			Display.showElement(element);
			element.style.opacity = opacity;
			let to = parseFloat(getComputedStyle(element).opacity);
			return Display.animateOpacity(element, 0, isNaN(to) ? 1 : to, duration).then(() => element.style.opacity = opacity);
		})));
	}

	static fadeOut(target, duration = Display.DEFAULT_DURATION)
	{
		return Promise.all(Display.elements(target).map(element => Display.queue(element, () =>
		{
			if (Display.isHidden(element))
				return null;
			let opacity = element.style.opacity;
			let from = parseFloat(getComputedStyle(element).opacity);
			return Display.animateOpacity(element, isNaN(from) ? 1 : from, 0, duration).then(() =>
			{
				Display.hideElement(element);
				element.style.opacity = opacity;
			});
		})));
	}

	//Runs step once the earlier fades of the element are over, right away when there are none, as jQuery does
	static queue(element, step)
	{
		let run = () =>
		{
			try
			{
				return Promise.resolve(step());
			}
			catch (error)
			{
				return Promise.reject(error);
			}
		};
		let previous = Display.queues.get(element);
		let next = previous ? previous.then(run) : run();
		let tail = next.catch(() => null);
		Display.queues.set(element, tail);
		tail.then(() =>
		{
			if (Display.queues.get(element) === tail)
				Display.queues.delete(element);
		});
		return next;
	}

	//jQuery's swing easing, ticked by a timer like jQuery 2, so it also ends in a tab in the background
	static animateOpacity(element, from, to, duration)
	{
		return new Promise(resolve =>
		{
			let start = performance.now();
			let tick = () =>
			{
				let progress = duration > 0 ? Math.min(1, (performance.now() - start) / duration) : 1;
				element.style.opacity = String(from + (to - from) * (0.5 - Math.cos(progress * Math.PI) / 2));
				if (progress < 1)
					setTimeout(tick, 13);
				else
					resolve();
			};
			tick();
		});
	}
}
