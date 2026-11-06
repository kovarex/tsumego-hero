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

	//A line of moves over the stones, numbered from 1: the colours take turns from the first one, and a
	//pass (tt) is counted without a stone
	function drawNumberedLine(line, size, increment, border, svg, w3)
	{
		let black = line.firstColor !== 'W';
		for (let i = 0; i < line.moves.length; i += 2, black = !black)
		{
			if (line.moves.substr(i, 2) === 'tt')
				continue;
			let x = decodeLetter(line.moves[i]) * increment + size + border;
			let y = decodeLetter(line.moves[i + 1]) * increment + size + border;
			placePreviewStone(x, y, size, black ? "black" : "white", svg, w3);
			let number = document.createElementNS(w3, "text");
			number.setAttribute("x", x);
			number.setAttribute("y", y);
			number.setAttribute("text-anchor", "middle");
			number.setAttribute("dominant-baseline", "central");
			number.setAttribute("font-size", size * (i < 18 ? 1.2 : 0.9));
			number.setAttribute("font-family", "Helvetica, Arial, sans-serif");
			number.setAttribute("fill", black ? "white" : "black");
			number.textContent = String(i / 2 + 1);
			svg.appendChild(number);
		}
	}

	//line: the moves to draw numbered over the stones ({moves, firstColor}), null for none
	function createBoard(target, black, white, xMax=0, yMax=0, boardSize=19, diff='', line=null)
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
		if (line)
			drawNumberedLine(line, size, increment, border, svg, w3);
		svg.style.width = xMax + "px";
		svg.style.height = yMax + "px";
		let targetContainer = target.querySelector('span');
		targetContainer.insertBefore(svg, targetContainer.firstChild);
		return svg;
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

//Inline SGF preview loader.
//A single delegated mouseover listener reads pre-parsed SGF data from
//data-sgf-preview attributes and renders an SVG preview into the link element.
//No AJAX, no delay — data is embedded at render time by TsumegoButton.
//Caching:
//  - SVG in DOM is the cache — querySelector('svg') guard prevents re-render.
//  - Page navigation clears everything naturally; no eviction needed.
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
