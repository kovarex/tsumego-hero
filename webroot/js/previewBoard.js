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
		let svg = document.createElementNS(w3,"svg");
		let zoom = (xMax>=9||yMax>=13) ? false : true;
		if(boardSize==13) zoom = false;
		let size = zoom ? 6 : 4;
		let border = zoom ? 3 : 2;
		xMax = (xMax>=9) ? 19 : xMax+4;
		let borderPixelsX = (xMax==19) ? size : size/2
		yMax = (yMax>=13) ? 19 : yMax+4;
		let borderPixelsY = (yMax==19) ? size : size/2;
		let increment = size*2;
		if(boardSize==13)
		{
			xMax = 13;
			yMax = 13;
		}
		xMax = increment*xMax+borderPixelsX;
		yMax = increment*yMax+borderPixelsY;
		let xPos = size+border;
		let yPos = size+border;

		let img = zoom ? "/img/theBoard2.png" : "/img/theBoard.png";
		if(boardSize==13) img = "/img/theBoard13x13.png"
		else if(boardSize==9) img = "/img/theBoard9x9.png"
		else if(boardSize==5) img = "/img/theBoard5x5.png"
		else if(boardSize==4) img = "/img/theBoard4x4.png"
		setPreviewBoard(xMax, yMax, svg, img, w3, w32);
		drawStoneString(black, "black", size, size,increment, border, svg, w3);
		drawStoneString(white, "white", size, size, increment, border, svg, w3);
		drawStoneString(diff, "red", size, size/2, increment, border, svg, w3);
		svg.style.width = xMax + "px";
		svg.style.height = yMax + "px";
		let targetContainer = target.querySelector('span');
		targetContainer.appendChild(svg);
	}

	function createPreviewBoard(target, black, white, xMax=0, yMax=0, boardSize=19)
	{
		createBoard(target, black, white, xMax, yMax, boardSize);
		hoverForPreviewBoard(target);
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

	function hoverForPreviewBoard(target)
	{
		target.addEventListener("mouseenter", function () {
			const span = this.querySelector('span');
			span.style.display = "block";
			span.style.position = "absolute";
			span.style.overflow = "hidden";
		});

		target.addEventListener("mouseleave", function () {
			const span = this.querySelector('span');
			span.style.display = "none";
		});
	}
