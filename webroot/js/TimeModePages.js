/* eslint-disable no-restricted-globals -- still uses jQuery until it is converted */
//The pages of the time mode around its runs: the overview, where the player picks a category and a
//rank, and the results. Each page prints its data (timeModeOverviewPage, timeModeResultPage).

//On the overview the account bar is the bar of the time mode, full, with the given text
function showTimeModeAccountBar(text)
{
	$('#xp-bar-fill').attr('class', 'xp-bar-fill-c3').css('width', '100%');
	$('#account-bar-user a').attr('class', 'xp-text-fill-c3x');
	$('#account-bar-xp').text(text);
}

//The overview shows the ranks of one category at a time, the one picked last, which a cookie remembers
function initTimeModeOverview(page)
{
	let selectedCategoryID = Number(getCookie('lastTimeModeCategoryID')) || page.lastCategoryID;

	function select(categoryID)
	{
		selectedCategoryID = categoryID;
		for (const id of page.categoryIDs)
		{
			document.getElementById('timeMode' + id).src = '/img/timeMode' + id + (id === categoryID ? '' : 'inactive') + '2.png';
			document.getElementById('time-mode' + id).style.display = id === categoryID ? 'block' : 'none';
		}
		showTimeModeAccountBar(page.bestSolvedRankByCategory[categoryID] ?? 'no rank');
	}

	for (const id of page.categoryIDs)
	{
		const image = document.getElementById('timeMode' + id);
		image.style.cursor = 'pointer';
		image.addEventListener('click', () => {
			select(id);
			setCookie('lastTimeModeCategoryID', id);
		});
		image.addEventListener('mouseover', () => { image.src = '/img/timeMode' + id + 'hover2.png'; });
		image.addEventListener('mouseout', () => { image.src = '/img/timeMode' + id + (id === selectedCategoryID ? '' : 'inactive') + '2.png'; });
	}

	for (const image of document.querySelectorAll('img[data-hover-src]'))
	{
		const src = image.getAttribute('src');
		image.addEventListener('mouseover', () => { image.src = image.dataset.hoverSrc; });
		image.addEventListener('mouseout', () => { image.src = src; });
	}

	select(selectedCategoryID);
}

//The results list a run per category and rank, each opening to its problems and history
function initTimeModeResult(page)
{
	for (const title of document.querySelectorAll('.tScoreTitle'))
		title.addEventListener('click', () => {
			const content = title.parentElement.querySelector('.timeModeTable3');
			const opening = getComputedStyle(content).display === 'none';
			content.style.display = opening ? '' : 'none';
			title.querySelector('.rankArrow').setAttribute('src', opening ? page.rankArrowOpened : page.rankArrowClosed);
		});

	if (page.unlock)
		$(document).ready(function() {
			$('.toast').fadeIn(500);
		});

	$('#alertCheckbox').change(function() {
		$('.toast').fadeOut(500);
	});
}
