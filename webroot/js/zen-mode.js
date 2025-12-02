/**
 * Zen Mode functionality for Tsumego Hero
 *
 * Provides a distraction-free puzzle-solving experience with:
 * - Toggle on/off with 'z' key or button click
 * - Exit with Escape key or exit button
 * - Auto-advance to next puzzle after solving (no page flash)
 *
 * Dependencies:
 * - Expects #zen-mode-toggle and #zen-mode-exit elements in DOM
 * - Expects global variables: nextButtonLink, previousButtonLink, tsumegoID, setID, etc.
 * - Expects besogo library for Go board manipulation
 * - Expects #puzzle-data JSON element on fetched pages
 */
(function() {
	// Check if zen mode is active (body class persists since we only swap #content)
	var zenModeActive = document.body.classList.contains('zen-mode');

	function enableZenMode() {
		if (zenModeActive) return;
		zenModeActive = true;
		document.body.classList.add('zen-mode');
	}

	function disableZenMode() {
		if (!zenModeActive) return;
		zenModeActive = false;
		document.body.classList.remove('zen-mode');
	}

	function toggleZenMode() {
		if (zenModeActive)
			disableZenMode();
		else
			enableZenMode();
	}

	// Toggle button click
	var toggleBtn = document.getElementById('zen-mode-toggle');
	if (toggleBtn)
		toggleBtn.addEventListener('click', toggleZenMode);

	// Exit button click
	var exitBtn = document.getElementById('zen-mode-exit');
	if (exitBtn)
		exitBtn.addEventListener('click', disableZenMode);

	// Keyboard shortcuts
	document.addEventListener('keydown', function(e) {
		// Don't trigger if typing in an input/textarea
		if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')
			return;

		if (e.key === 'z' || e.key === 'Z') {
			toggleZenMode();
			e.preventDefault();
		}
		else if (e.key === 'Escape' && zenModeActive) {
			disableZenMode();
			e.preventDefault();
		}
	});

	// Expose for displayResult to check zen mode state
	window.isZenModeActive = function() {
		return zenModeActive;
	};

	/**
	 * Zen Mode Navigation: Fetches the next puzzle and morphs the content without full page reload.
	 * Uses idiomorph to swap #content, then reads puzzle-data and updates the board.
	 * Returns a Promise that resolves when navigation is complete.
	 */
	window.zenModeNavigateToNext = function() {
		return new Promise(function(resolve, reject) {
			if (typeof nextButtonLink === 'undefined' || !nextButtonLink) {
				reject(new Error('No nextButtonLink available'));
				return;
			}

			fetch(nextButtonLink)
				.then(function(response) {
					if (!response.ok) throw new Error('HTTP ' + response.status);
					return response.text();
				})
				.then(function(html) {
					// Parse HTML and extract #content and puzzle-data
					var parser = new DOMParser();
					var doc = parser.parseFromString(html, 'text/html');
					
					var newContent = doc.getElementById('content');
					var dataEl = doc.getElementById('puzzle-data');

					if (!newContent) {
						throw new Error('Could not find #content in fetched page');
					}
					if (!dataEl) {
						throw new Error('Could not find puzzle-data element');
					}

					var data = JSON.parse(dataEl.textContent);

					if (!data.tsumegoID || !data.sgf) {
						throw new Error('Invalid puzzle data');
					}

					// CRITICAL: Save reference to the actual board DOM element before morph
					// The board contains the besogo SVG which we must preserve
					// Note: ui=2 uses #target, ui!=2 uses #board
					var boardElement = document.getElementById('board') || document.getElementById('target');
					var boardId = boardElement ? boardElement.id : null;
					var boardParent = boardElement ? boardElement.parentNode : null;
					
					// Remove board from DOM temporarily so idiomorph doesn't touch it
					if (boardElement && boardParent) {
						boardParent.removeChild(boardElement);
					}
					
					// Also remove the board from the new content - we don't want idiomorph to add an empty one
					var newBoardEl = newContent.querySelector('#board') || newContent.querySelector('#target');
					if (newBoardEl) {
						newBoardEl.parentNode.removeChild(newBoardEl);
					}
					
					// Morph the content - this updates all UI elements EXCEPT the board
					var currentContent = document.getElementById('content');
					if (currentContent && typeof Idiomorph !== 'undefined') {
						Idiomorph.morph(currentContent, newContent, {
							morphStyle: 'innerHTML'
						});
					}
					
					// Re-insert the preserved board element
					if (boardElement) {
						// Find where to insert - look for the board's original location
						// It should be near targetLockOverlay or similar
						var insertPoint = document.getElementById('targetLockOverlay');
						if (insertPoint && insertPoint.parentNode) {
							insertPoint.parentNode.insertBefore(boardElement, insertPoint.nextSibling);
						} else {
							// Fallback: append to content
							currentContent.appendChild(boardElement);
						}
					}
					
					// Process any htmx attributes in the new content
					if (typeof htmx !== 'undefined') {
						htmx.process(document.getElementById('content'));
					}

					// Update global variables from puzzle-data
					tsumegoID = data.tsumegoID;
					nextButtonLink = data.nextButtonLink || '';
					previousButtonLink = data.previousButtonLink || '';
					setID = data.setID;
					file = data.file || '';
					author = data.author || '';
					clearFile = data.title || '';

					// Reset puzzle state
					ko = false;
					lastMove = false;
					lastHover = false;
					moveCounter = 0;
					move = 0;
					branch = "";
					misplays = 0;
					hoverLocked = false;
					tryAgainTomorrow = false;
					isCorrect = false;
					playedWrong = false;
					freePlayMode = false;
					problemSolved = false;
					boardLockValue = 0;

					// Update besogo player color
					besogoPlayerColor = data.playerColor || 'black';

					// Parse and load the new SGF into besogo
					console.log('ZEN: Loading SGF, length:', data.sgf ? data.sgf.length : 0);
					console.log('ZEN: SGF content:', data.sgf);
					var parsedSgf = besogo.parseSgf(data.sgf);
					console.log('ZEN: parsedSgf props:', parsedSgf ? parsedSgf.props : 'null');
					console.log('ZEN: parsedSgf props with AB/AW:', parsedSgf ? parsedSgf.props.filter(function(p) { return p.id === 'AB' || p.id === 'AW'; }) : 'null');
					
					if (parsedSgf && besogo.editor) {
						besogo.playerColor = besogoPlayerColor;
						
						// Get size from SGF
						var szProp = parsedSgf.props.find(function(p) { return p.id === 'SZ'; });
						console.log('ZEN: SGF size property:', szProp);
						
						// Load the SGF which will call loadRoot internally
						var scaleParams = besogo.loadSgf(parsedSgf, besogo.editor);
						console.log('ZEN: scaleParameters returned:', scaleParams);
						console.log('ZEN: After loadSgf, getRoot().getSize():', besogo.editor.getRoot().getSize());
						
						var current = besogo.editor.getCurrent();
						console.log('ZEN: After loadSgf getCurrent board length:', current.board ? current.board.length : 'no board');
						console.log('ZEN: getCurrent getStone(1,1):', current.getStone ? current.getStone(1,1) : 'no getStone');
						console.log('ZEN: getCurrent getStone(10,10):', current.getStone ? current.getStone(10,10) : 'no getStone');
						
						besogo.editor.setAutoPlay(true);
						besogo.editor.setCurrent(besogo.editor.getRoot());
						besogo.editor.notifyListeners({
							treeChange: true,
							navChange: true,
							stoneChange: true
						});
						console.log('ZEN: SGF loaded and notified');
					} else {
						console.error('ZEN: Failed to load SGF - parsedSgf:', !!parsedSgf, 'editor:', !!besogo.editor);
					}

					// Update URL without page reload
					history.pushState({}, '', nextButtonLink);

					// Remove any result glow effects
					var besogoBoard = document.querySelector('.besogo-board');
					if (besogoBoard) {
						besogoBoard.style.boxShadow = '';
					}

					resolve();
				})
				.catch(function(error) {
					console.error('Zen mode navigation error:', error);
					reject(error);
				});
		});
	};
})();
