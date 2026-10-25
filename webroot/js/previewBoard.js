	function decodeLetter(c) { return c.charCodeAt(0) - 97; }

	function drawStoneString(coordString, fill, size, dotSize, increment, border, svg, w3)
	{
		for (let i = 0; i < coordString.length; i += 2)
		{
			let x = decodeLetter(coordString[i]);
			let y = decodeLetter(coordString[i+1]);

			// Convert board coords → board coords
			let xPos = (x * increment) + size + border;
			let yPos = (y * increment) + size + border;

			placePreviewStone(xPos, yPos, dotSize, fill, svg, w3);
		}
	}

	function createBoard(target, black, white, xMax=0, yMax=0, boardSize=19, diff='')
	{
		const w3 = "http://www.w3.org/2000/svg";
		const w32 = "http://www.w3.org/1999/xlink";
		let svg = document.createElementNS(w3, "svg");

		let img, size, border, increment;

		if (boardSize <= 13)
		{
			// Small boards: render full board, ignore stone bounding box
			let zoom = boardSize <= 9;
			size = zoom ? 6 : 4;
			border = size / 2;
			increment = size * 2;
			xMax = increment * boardSize + border;
			yMax = xMax;
			img = "/img/theBoard" + boardSize + "x" + boardSize + ".png";
		}
		else
		{
			// 19x19: crop to stone bounding box with padding
			let zoom = (xMax >= 9 || yMax >= 13) ? false : true;
			size = zoom ? 6 : 4;
			border = zoom ? 3 : 2;
			increment = size * 2;
			xMax = (xMax >= 9) ? 19 : xMax + 4;
			let bpx = (xMax == 19) ? size : size / 2;
			yMax = (yMax >= 13) ? 19 : yMax + 4;
			let bpy = (yMax == 19) ? size : size / 2;
			xMax = increment * xMax + bpx;
			yMax = increment * yMax + bpy;
			img = zoom ? "/img/theBoard2.png" : "/img/theBoard.png";
		}

		setPreviewBoard(xMax, yMax, svg, img, w3, w32);
		drawStoneString(black, "black", size, size, increment, border, svg, w3);
		drawStoneString(white, "white", size, size, increment, border, svg, w3);
		drawStoneString(diff, "red", size, size / 2, increment, border, svg, w3);
		svg.style.width = xMax + "px";
		svg.style.height = yMax + "px";
		let targetContainer = target.querySelector('span');
		targetContainer.insertBefore(svg, targetContainer.firstChild);
	}

	function createPreviewBoard(target, black, white, xMax=0, yMax=0, boardSize=19, diff = '')
	{
		createBoard(target, black, white, xMax, yMax, boardSize, diff);
	}

	function setPreviewBoard(xMax, yMax, svg, img, w3, w32)
	{
		svg.setAttributeNS(w3,"width", xMax);
		svg.setAttributeNS(w3,"height", yMax);
		let svgImg = document.createElementNS(w3,"image");
		svgImg.setAttributeNS(w3,"width", xMax);
		svgImg.setAttributeNS(w3,"height", yMax);
		svgImg.setAttributeNS(w32,"href", img);
		svgImg.setAttributeNS(w3,"x","0");
		svgImg.setAttributeNS(w3,"y","0");
		svg.appendChild(svgImg);
	}

	function placePreviewStone(x, y, size, fill, svg, w3)
	{
		let svgCircle = document.createElementNS(w3, "circle");
		svgCircle.setAttribute("cx", x);
		svgCircle.setAttribute("cy", y);
		svgCircle.setAttribute("r", size);
		svgCircle.setAttribute("fill", fill);
		svg.appendChild(svgCircle);
	}

/**
 * Inline SGF preview loader.
 *
 * A single delegated mouseover listener reads pre-parsed SGF data from
 * data-sgf-preview attributes and renders an SVG preview into the link element.
 * No AJAX, no delay — data is embedded at render time by TsumegoButton.
 *
 * Caching:
 *   - SVG in DOM is the cache — querySelector('svg') guard prevents re-render.
 *   - Page navigation clears everything naturally; no eviction needed.
 */
(function () {
	'use strict';

	document.addEventListener('mouseover', function (e) {
		var target = e.target.closest('a[data-tsumego-id]');
		if (!target)
			return;

		// Already rendered or no preview data
		if (target.querySelector('svg') || !target.dataset.sgfPreview)
			return;

		var data;
		try {
			data = JSON.parse(target.dataset.sgfPreview);
		} catch (e) {
			return;
		}

		if (!data || !data.black)
			return;

		createBoard(
			target,
			data.black,
			data.white,
			data.xMax,
			data.yMax,
			data.boardSize
		);
	}, true);
})();
