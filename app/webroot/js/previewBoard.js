	function createPreviewBoard(n=null, masterArrayBW=null, xMax=0, yMax=0, boardSize=19){
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
		if(boardSize==13){
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
		setPreviewBoard(xMax, yMax, svg, img, n, w3, w32);
		for(i=0;i<19;i++){
			for(j=0;j<19;j++){
				if(masterArrayBW[i][j]!=="-"){
					let fill = (masterArrayBW[i][j]==="x") ? "black" : "white";
					placePreviewStone(xPos, yPos, size, fill, svg, w3);
				}
				xPos += increment;
			}
			xPos = size+border;
			yPos += increment;
		}
		setPreviewBoardCss(xMax, yMax, n, svg);
		hoverForPreviewBoard(n);
	}
	function setPreviewBoard(xMax, yMax, svg, img, n, w3, w32){
		svg.setAttributeNS(w3,"width", xMax);
		svg.setAttributeNS(w3,"height", yMax);
		let svgImg = document.createElementNS(w3,"image");
		svgImg.setAttributeNS(w3,"width", xMax);
		svgImg.setAttributeNS(w3,"height", yMax);
		svgImg.setAttributeNS(w32,"href", img);
		svgImg.setAttributeNS(w3,"id","theImage"+n);
		svgImg.setAttributeNS(w3,"x","0");
		svgImg.setAttributeNS(w3,"y","0");
		svg.appendChild(svgImg);
	}
	function placePreviewStone(x, y, size, fill, svg, w3){
		let svgCircle = document.createElementNS(w3, "circle");
		svgCircle.setAttribute("cx", x);
		svgCircle.setAttribute("cy", y);
		svgCircle.setAttribute("r", size);
		svgCircle.setAttribute("fill", fill);
		svg.appendChild(svgCircle);
	}
	function setPreviewBoardCss(xMax, yMax, n, svg){
		document.querySelector("#tooltipSvg"+n).appendChild(svg);
		$("#tooltipSvg"+n+" svg").css("width", xMax);
		$("#tooltipSvg"+n+" svg").css("height", yMax);
	}
	function hoverForPreviewBoard(n){
		$("#tooltip-hover"+n).mouseover(function(){
		  $("#tooltip-hover"+n+" span").css({"display":"block","position":"absolute","overflow":"hidden"});
		});
		$("#tooltip-hover"+n).mouseout(function(){
		  $("#tooltip-hover"+n+" span").css({"display":"none"});
		});
	}