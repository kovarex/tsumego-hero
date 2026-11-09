//The gold frames of the finished set cards (templates/element/set_card.php), drawn as one SVG per card to
//the card's size, again whenever the size changes (a phone card takes half the row):
//- finished (.set-card--finished): a band of dragon scales along the edge;
//- finished with 100% accuracy (.set-card--perfect): a wider band that tapers from the top to the bottom,
//  with a spine and scales on both sides of it, and spikes all round the outside: a fan at each corner, a
//  crest over the pearl that sits on the top edge, and a row along every side.
//The frame takes only its band from the inside of the card; the spikes and the pearl reach out past it.
//The band is lit from the top left, so its edges bevel like metal; each line of relief has a shadow and a
//highlight. On a phone card (lower than 140px) the band is narrower, the scales fewer and the spikes shorter.
(function () {
	var NS = 'http://www.w3.org/2000/svg';
	var CARD_RADIUS = 10;
	var nextID = 0;

	function f(n) {
		return (+n).toFixed(2);
	}

	function circle(x, y, r, attributes) {
		return '<circle cx="' + f(x) + '" cy="' + f(y) + '" r="' + f(r) + '" ' + (attributes || '') + '/>';
	}

	// Three quarters of a circle, for a curl
	function arc(x, y, r) {
		return 'M' + f(x + r) + ' ' + f(y) + 'A' + f(r) + ' ' + f(r) + ' 0 1 1 ' + f(x) + ' ' + f(y - r);
	}

	// A line of relief: its shadow, then its highlight half a pixel down and right
	function relief(d, width) {
		return '<path d="' + d + '" fill="none" stroke="#5c3f08" stroke-opacity=".9" stroke-width="' + f(width) + '" stroke-linecap="round" stroke-linejoin="round"/>'
			+ '<path d="' + d + '" fill="none" stroke="#fff4c4" stroke-opacity=".75" stroke-width="' + f(width * 0.6) + '" stroke-linecap="round" stroke-linejoin="round" transform="translate(.45 .45)"/>';
	}

	// The card's outline
	function outer(w, h) {
		var r = CARD_RADIUS;
		return 'M' + r + ' 0H' + (w - r) + 'Q' + w + ' 0 ' + w + ' ' + r + 'V' + (h - r) + 'Q' + w + ' ' + h + ' ' + (w - r) + ' ' + h
			+ 'H' + r + 'Q0 ' + h + ' 0 ' + (h - r) + 'V' + r + 'Q0 0 ' + r + ' 0Z';
	}

	// The frame's inner edge, for a band of width t at the top, s at the sides and b at the bottom; the sides
	// lean with the band's taper. inset moves it out toward the card's edge (negative) or in.
	function inner(w, h, band, inset) {
		inset = inset || 0;
		var t = band[0] + inset, s = band[1] + inset, b = band[2] + inset, r = 3;
		var st = s + (t - s) * 0.35, sb = s - (s - b) * 0.35;
		return 'M' + f(st + r) + ' ' + f(t) + 'H' + f(w - st - r) + 'Q' + f(w - st) + ' ' + f(t) + ' ' + f(w - st) + ' ' + f(t + r)
			+ 'L' + f(w - sb) + ' ' + f(h - b - r) + 'Q' + f(w - sb) + ' ' + f(h - b) + ' ' + f(w - sb - r) + ' ' + f(h - b)
			+ 'H' + f(sb + r) + 'Q' + f(sb) + ' ' + f(h - b) + ' ' + f(sb) + ' ' + f(h - b - r)
			+ 'L' + f(st) + ' ' + f(t + r) + 'Q' + f(st) + ' ' + f(t) + ' ' + f(st + r) + ' ' + f(t) + 'Z';
	}

	// Scales along the middle of the band on each side, from one corner's reach to the next: arcs bulging
	// toward the card's edge; with a spine, a ridge down the middle with a row of scales on each side of it
	function scales(w, h, band, frame, phone) {
		var reach = band[0] * 1.2, d = '', spine = '', lineWidth = 0.7;
		var sm = (band[1] + band[0]) / 2, sbm = (band[1] + band[2]) / 2;
		var sides = [
			{ ax: reach, ay: band[0] / 2, bx: w - reach, by: band[0] / 2, wa: band[0], wb: band[0], nx: 0, ny: 1, top: true },
			{ ax: w - sm / 2, ay: reach, bx: w - sbm / 2, by: h - reach, wa: sm, wb: sbm, nx: -1, ny: 0 },
			{ ax: w - reach, ay: h - band[2] / 2, bx: reach, by: h - band[2] / 2, wa: band[2], wb: band[2], nx: 0, ny: -1 },
			{ ax: sbm / 2, ay: h - reach, bx: sm / 2, by: reach, wa: sbm, wb: sm, nx: 1, ny: 0 },
		];
		var pearlGap = frame.pearl ? (phone ? 15.5 : 21) : 0;
		sides.forEach(function (side) {
			var len = Math.hypot(side.bx - side.ax, side.by - side.ay);
			var dx = (side.bx - side.ax) / len, dy = (side.by - side.ay) / len;
			var middle = (side.wa + side.wb) / 2;
			var step = middle * frame.scale * (phone ? 1.3 : 1) * (frame.spine ? 1.3 : 1);
			var n = Math.max(1, Math.floor(len / step)), offset = (len - (n - 1) * step) / 2;
			var rows = frame.spine ? (phone ? [-0.2, 0.22] : [-0.24, 0.24]) : [0.08];
			var line = '';
			lineWidth = Math.max(0.7, middle * 0.11);
			for (var k = 0; k < n; k++) {
				var t = offset + k * step, width = side.wa + (side.wb - side.wa) * t / len;
				var px = side.ax + dx * t, py = side.ay + dy * t;
				if (side.top && Math.abs(t - len / 2) < pearlGap)
					continue;
				rows.forEach(function (row) {
					var cx = px + side.nx * width * row, cy = py + side.ny * width * row, r = step * (frame.spine ? 0.5 : 0.62);
					var out = row < 0 || !frame.spine ? -1 : 1;
					d += 'M' + f(cx - dx * r) + ' ' + f(cy - dy * r)
						+ 'Q' + f(cx + side.nx * out * r * 1.4) + ' ' + f(cy + side.ny * out * r * 1.4) + ' ' + f(cx + dx * r) + ' ' + f(cy + dy * r);
				});
				if (frame.spine)
					line += (line ? 'L' : 'M') + f(px) + ' ' + f(py);
			}
			spine += line;
		});
		return relief(d, lineWidth) + (spine ? relief(spine, lineWidth * 1.6) : '');
	}

	// A spike of gold: its base on the band at (x, y), pointing at angle (degrees, on the screen), length
	// long, width wide at the base, its tip bent sideways by curl like a claw; a groove runs up its middle
	function spike(x, y, angle, length, width, curl) {
		var a = angle * Math.PI / 180, dx = Math.cos(a), dy = Math.sin(a), nx = -dy, ny = dx;
		function point(along, across) {
			return f(x + dx * along + nx * across) + ' ' + f(y + dy * along + ny * across);
		}
		return {
			shape: '<path d="M' + point(0, width / 2) + 'Q' + point(length * 0.55, width * 0.35 + curl * 0.5) + ' ' + point(length, curl)
				+ 'Q' + point(length * 0.5, -width * 0.25 + curl * 0.3) + ' ' + point(0, -width / 2) + 'Z"/>',
			groove: 'M' + point(length * 0.12, 0) + 'Q' + point(length * 0.45, curl * 0.2) + ' ' + point(length * 0.68, curl * 0.5),
		};
	}

	// The spikes of the 100% accuracy frame
	function spikes(w, h, band, phone) {
		var tiny = w < 140, k = tiny ? 0.42 : phone ? 0.62 : 1, widen = phone ? 1.15 : 1, all = [];
		// a fan at each corner, smaller at the bottom
		[[0, 0, 1, 1], [w, 0, -1, 1], [w, h, -1, -1], [0, h, 1, -1]].forEach(function (corner) {
			var ix = corner[2], iy = corner[3], width = iy > 0 ? band[0] : band[2], g = iy > 0 ? 1 : 0.85;
			var base = Math.atan2(-iy, -ix) * 180 / Math.PI, x = corner[0] + ix * width * 0.45, y = corner[1] + iy * width * 0.45;
			[[0, 25, 8], [24, 19, 7], [50, 13, 6]].forEach(function (fan) {
				(fan[0] ? [-1, 1] : [0]).forEach(function (sign) {
					all.push(spike(x, y, base + sign * fan[0], fan[1] * k * g, fan[2] * k * widen, sign * fan[1] * 0.16 * k * g));
				});
			});
		});
		// a crest over the pearl
		[[0, 19, 0], [9, 15, 14], [17, 10, 28]].forEach(function (crest) {
			(crest[0] ? [-1, 1] : [0]).forEach(function (sign) {
				all.push(spike(w / 2 + sign * crest[0] * (tiny ? 0.45 : phone ? 0.62 : 0.85) * 1.4, band[0] * 0.3, -90 + sign * crest[2], crest[1] * k, 6.5 * k * widen, sign * crest[1] * 0.12 * k));
			});
		});
		// a row along every side, long and short in turn, between the corner fans and clear of the crest
		var step = tiny ? 11 : phone ? 13 : 17, cornerZone = tiny ? 14 : phone ? 20 : 30, crestZone = tiny ? 18 : phone ? 26 : 38;
		[
			[cornerZone, 0, w - cornerZone, 0, -90, band[0]],
			[w, cornerZone, w, h - cornerZone, 0, band[1]],
			[w - cornerZone, h, cornerZone, h, 90, band[2]],
			[0, h - cornerZone, 0, cornerZone, 180, band[1]],
		].forEach(function (side) {
			var ax = side[0], ay = side[1], bx = side[2], by = side[3], angle = side[4], width = side[5];
			var len = Math.hypot(bx - ax, by - ay), n = Math.max(1, Math.floor(len / step)), offset = (len - (n - 1) * step) / 2;
			var ox = Math.cos(angle * Math.PI / 180), oy = Math.sin(angle * Math.PI / 180);
			for (var j = 0; j < n; j++) {
				var t = offset + j * step, x = ax + (bx - ax) * t / len, y = ay + (by - ay) * t / len;
				if (angle === -90 && Math.abs(x - w / 2) < crestZone)
					continue;
				var long = j % 2 === 0, tilt = (j % 4 < 2 ? 1 : -1) * 8;
				all.push(spike(x - ox * width * 0.35, y - oy * width * 0.35, angle + tilt, (long ? 11 : 7) * k, (long ? 6 : 5) * k * widen, tilt * 0.12 * k));
			}
		});
		return all;
	}

	// The pearl's setting on the top edge, rising out of the card, with gold flame tongues on both sides
	function pearlSetting(w, top, u) {
		var x = w / 2, y = top * 0.3, shape = '<ellipse cx="' + f(x) + '" cy="' + f(y) + '" rx="' + f(18 * u) + '" ry="' + f(8.5 * u) + '"/>', d = '';
		[-1, 1].forEach(function (s) {
			function point(a, b) {
				return f(x + s * a * u * 1.2) + ' ' + f(y + b * u * 1.2);
			}
			shape += '<path d="M' + point(3, 5) + 'C' + point(9, 6) + ' ' + point(14, 2) + ' ' + point(16.5, -5) + 'C' + point(14.5, -1.5) + ' ' + point(12, -0.5) + ' ' + point(10.5, -0.8)
				+ 'C' + point(13, -3) + ' ' + point(13.5, -6) + ' ' + point(12.5, -9) + 'C' + point(11, -5) + ' ' + point(8, -3) + ' ' + point(4, -2) + 'Z"/>';
			d += 'M' + point(6, 2) + 'Q' + point(11, 1) + ' ' + point(14, -4);
		});
		var r = 7.5 * u;
		d += arc(x, y + 0.6 * u, r + 1.6 * u);
		return { shape: shape, d: d, pearl: [x, y + 0.6 * u, r] };
	}

	// The band's width at the top, the sides and the bottom
	var FRAMES = {
		finished: { pc: [7, 7, 7], phone: [5, 5, 5], tiny: [4, 4, 4], scale: 0.55 },
		perfect: { pc: [11, 9, 8], phone: [8, 7, 6], tiny: [6, 5, 4.5], scale: 0.45, spine: true, ridge: true, spikes: true, pearl: true },
	};

	function draw(card) {
		var w = card.offsetWidth, h = card.offsetHeight;
		if (!w || !h)
			return;
		var perfect = card.classList.contains('set-card--perfect');
		var frame = perfect ? FRAMES.perfect : FRAMES.finished;
		// TEST ONLY (3 a row): a card narrower than 140px gets a thinner band, smaller spikes and a smaller pearl
		var phone = h < 140, tiny = w < 140, band = tiny ? frame.tiny : phone ? frame.phone : frame.pc;
		var u = tiny ? 0.45 : phone ? 0.62 : 0.85, id = 'set-card-frame-' + (nextID++);
		var svg = card.querySelector(':scope > .set-card__frame');
		if (!svg) {
			svg = document.createElementNS(NS, 'svg');
			svg.setAttribute('class', 'set-card__frame');
			svg.setAttribute('aria-hidden', 'true');
			card.prepend(svg);
		}
		var gold = perfect
			? ['#c9952e', '#fff1a6', '#dcae42', '#fff7cf', '#b8862b']
			: ['#a87a22', '#efd172', '#be8d2e', '#f3dd92', '#94681a'];
		var surface = perfect ? 4 : 3;
		var defs = '<defs><linearGradient id="' + id + '-gold" x1="0" y1="0" x2="1" y2="1">'
			+ gold.map(function (color, i) { return '<stop offset="' + i / 4 + '" stop-color="' + color + '"/>'; }).join('') + '</linearGradient>'
			+ '<radialGradient id="' + id + '-pearl" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#fff"/><stop offset=".45" stop-color="#f6efe0"/>'
			+ '<stop offset=".8" stop-color="#d8c9a6"/><stop offset="1" stop-color="#a8956c"/></radialGradient>'
			+ '<filter id="' + id + '-metal" filterUnits="userSpaceOnUse" x="-30" y="-30" width="' + (w + 60) + '" height="' + (h + 60) + '" color-interpolation-filters="sRGB">'
			+ '<feGaussianBlur in="SourceAlpha" stdDeviation="' + (phone ? 0.7 : perfect ? 1.1 : 0.9) + '" result="bump"/>'
			+ '<feDiffuseLighting in="bump" surfaceScale="' + surface + '" diffuseConstant="1.15" lighting-color="#fff" result="diffuse"><feDistantLight azimuth="225" elevation="55"/></feDiffuseLighting>'
			+ '<feSpecularLighting in="bump" surfaceScale="' + surface + '" specularConstant="0.95" specularExponent="14" lighting-color="#fff6d6" result="specular"><feDistantLight azimuth="225" elevation="40"/></feSpecularLighting>'
			+ '<feComposite in="SourceGraphic" in2="diffuse" operator="arithmetic" k1="1.1" result="lit"/>'
			+ '<feComposite in="specular" in2="SourceAlpha" operator="in" result="shine"/>'
			+ '<feComposite in="lit" in2="shine" operator="arithmetic" k2="1" k3="0.8" result="metal"/>'
			+ '<feComposite in="metal" in2="SourceAlpha" operator="in"/>'
			+ '<feDropShadow dx=".7" dy="1.1" stdDeviation=".8" flood-color="#000" flood-opacity=".6"/>'
			+ '</filter></defs>';
		// the band, the spikes and the pearl's setting are one piece of metal
		var shape = '<path fill-rule="evenodd" d="' + outer(w, h) + inner(w, h, band) + '"/>';
		var detail = scales(w, h, band, frame, phone), pearl = '';
		if (frame.spikes)
			spikes(w, h, band, phone).forEach(function (s) {
				shape += s.shape;
				detail += relief(s.groove, phone ? 0.6 : 0.8);
			});
		if (frame.pearl) {
			var setting = pearlSetting(w, band[0], u);
			shape += setting.shape;
			detail += relief(setting.d, Math.max(0.7, 0.9 * u));
			var p = setting.pearl;
			pearl = circle(p[0], p[1], p[2] + 0.7, 'fill="#5c3f08"') + circle(p[0], p[1], p[2], 'fill="url(#' + id + '-pearl)"')
				+ circle(p[0] - p[2] * 0.35, p[1] - p[2] * 0.4, p[2] * 0.22, 'fill="#fff" fill-opacity=".9"');
		}
		var ridge = frame.ridge ? '<path d="' + inner(w, h, band, -1.7) + '" fill="none" stroke="url(#' + id + '-gold)" stroke-width="1.6"/>' : '';
		svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
		svg.innerHTML = defs + '<g fill="url(#' + id + '-gold)" filter="url(#' + id + '-metal)">' + shape + ridge + '</g>' + detail + pearl;
	}

	function init() {
		var cards = document.querySelectorAll('.set-card--finished');
		if (!cards.length)
			return;
		var observer = typeof ResizeObserver === 'function'
			? new ResizeObserver(function (entries) {
				entries.forEach(function (entry) { draw(entry.target); });
			})
			: null;
		for (var i = 0; i < cards.length; i++) {
			draw(cards[i]);
			if (observer)
				observer.observe(cards[i]);
		}
	}

	// This file is bundled into the global legacy bundle which loads in <head>,
	// so the set cards are not in the DOM yet - wait for them.
	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
