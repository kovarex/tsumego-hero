<?php

use App\Utility\Auth;

//The site updates in the left column of the home page, newest first.

/**
 * @var View $this
 * @var string $levelModeUrl
 */

$loggedIn = Auth::isLoggedIn();
//A mode a guest can't play is named without a link.
$modeLink = fn(string $url, string $class, string $label, bool $requiresLogin) => $loggedIn || !$requiresLogin
	? '<a href="' . $url . '" class="' . $class . '">' . $label . '</a>'
	: '<span class="' . $class . '">' . $label . '</span>';
?>
<div class="news-article">
	<p class="title4">Update 29.11.2024</p>
	<div class="new1">
		<b>Customizable collection index page</b><br><br>
		The collection index page got a makeover, which makes use of the newly introduced tags. You can set filters for three categories:
		<b class="text-topic">Topics</b>, <b class="text-difficulty">Difficulty</b> and <b class="text-tag">Tags</b>.
		<br>
		<div class="text-center">
			<img class="img-padded" src="/img/customizable-collections-example.png" alt="Customizable Collections Example" title="Customizable Collections Example" style="width: 85%">
		</div>
		Further, there are two grouping parameters: Collection types and collection sizes. The type is set to one of the three filters.
		In this example, the type is difficulty, which groups the problems in collections for the rank.
		The applied filters remain set on the problem pages until they are changed or removed.
		<br><br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 26.10.2024</p>
	<div class="new1">
		<b>Tags, proposals and rewards</b><br><br>
		We need your help! The problems on this website are currently uncategorized, which we should change.
		The goal is to assign matching tags to every problem. All users that have passed level 40 or 6k
		are welcome to help with this meaningful task. There are amazing rewards for those who
		help with adding and creating tags and/or proposals. So the next time you see a problem, for example,
		with a <a href="/tags/view/8">Seki</a>, a <a href="/tags/view/6">Snapback</a> or an
		<a href="/tags/view/7">Under the Stones</a> tesuji, leave a tag.
		<br>
		<div class="text-center">
			<img class="img-padded" src="/img/example-tags-proposals.png" alt="Example for tags and proposals" title="Example for tags and proposals" style="width: 72%">
		</div>
		It is now also possible to make proposals for improving the problem files. You select "Make Proposal", modify the move tree and save. An
		admin is going to check and approve the changes. For getting rewards, a point system has been implemented: <b>Add tag (1 pt)</b>, <b>create new tag (3 pts)</b>,
		<b>make proposal (5 pts)</b>. Any contribution has to be accepted by an admin to become public. <i>Rewards have been deactivated as they were meant for the early
		phase of tags to get it running.</i>
		<br><br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 13.10.2024</p>
	<div class="new1">
		<b>Account management</b><br><br>
		• Longer sessions.<br>
		• Sign in with Google account.<br>
		• Option to delete all account related data.<br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Bugfix 01.05.2024</p>
	<div class="new1">
		Longer sessions were not working properly, so this feature is reverted. If there are still any issues, such as log-outs, try to delete the cookies.<br><br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 30.04.2024</p>
	<div class="new1">
		<b>Improved search, ratings and board views</b><br><br>
		<b>• Boards can be rotated.</b><br>
		<b>• Broader rank system:</b> While the last update enabled ranks from 21k to 9d, this update scales the existing problems into the new range.<br>
		<b>• Improved rating calculation:</b> The Tsumego rating calculation added an activity value that is meant to have a higher variance
		with fresh problems.<br>
		<b>• Improved similar problem search:</b> The search has been improved by storing solution types in the database.
		It always finds many similar problems in any search now and is very fast.<br>
		<div class="text-center">
			<img src="/img/example-search.png" alt="Similar problem search example" title="Similar problem search example" width="350"><br>
			Example: <a href="/tsumegos/duplicatesearch/2189">Life & Death - Elementary 745</a><br>
			<i>In this example, it finds 30 similar problems almost instantly.</i><br><br>
		</div>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 03.04.2024</p>
	<div class="new1">
		<b>Improvements of rank display and score mechanics</b><br><br>
		<b>• Broader rank system:</b> Player and Tsumego ranks range from 21k to 9d.<br>
		<b>• Collection difficulty:</b> Instead of a difficulty value from 1 to 9, the average kyu/dan rank is shown.<br>
		<b>• Mode-independent scores:</b> Problems in rating- and time mode give also xp and they change the problem's status to solved on succeeding.
		This means that rating and level are affected in any mode.<br>
		<b>• Progress bar switch:</b> The progress bar can display any type and it can be switched on the problem pages by
		selecting the lower left corner.<br><br>
		<div class="text-center">
			<img src="/img/thumbs/1.png" alt="Progress bar example" title="Progress bar example" width="140">
			<br>
		</div>
	</div>
</div>

<div class="news-article">
	<p class="title4">17.03.2024</p>
	<div class="new1">
		<b>Prizes</b><br><br>
		<b>Madec</b>, <b>Kimok</b> and <b>YuriyStepanovich</b> win a premium account as they have the highest non-premium ratings.<br><br>
	</div>
</div>

<div class="news-article">
	<p class="title4">10.03.2024</p>
	<div class="new1">
		<b>Prizes</b><br><br>
		<b>GoTalk</b>, <b>Futsal</b> and <b>Imaim</b> win a premium account. One more time we upgrade 3 accounts on 17.03.2024.<br><br>
	</div>
</div>

<div class="news-article">
	<p class="title4">03.03.2024</p>
	<div class="new1">
		<b>Prizes</b><br><br>
		<b>Rippa</b>, <b>au61413080900</b> and <b>franp9am</b> win a premium account as they have the highest non-premium ratings. On
		10.03.2024 we give away 3 more.<br><br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 25.02.2024</p>
	<div class="new1">
		<b>Overall rating</b><br><br>
		With this update, Tsumego attempts in any mode affect the user rating.<br>
		• <?php echo $modeLink($levelModeUrl, 'mode-link--level', 'Level mode', false); ?> problems affect level and user rating.<br>
		• <?php echo $modeLink('/ratingMode', 'mode-link--rating', 'Rating mode', true); ?> is the same as before, but all formulas for user and tsumego rating calculation have been improved.<br>
		• <?php echo $modeLink('/timeMode/overview', 'mode-link--time', 'Time mode', true); ?> also affects the user rating.<br>
		<br>

		<b>Rewards:</b> Every Sunday, the 3 highest rated users that have no premium account get a premium upgrade. Next prize giving: 03.03.24. To give everyone a fresh start, all user ratings have been reset.<br><br>

		<b>Profile page</b><br><br>
		The <?php echo $loggedIn ? '<a href="/users/view/' . Auth::getUserID() . '">profile page</a>' : 'profile page'; ?> contains more data and is organized in line and bar charts.
		<br>
		<br>
		<div class="text-center">
			<img src="/img/profile-display-example.png" alt="Profile page example" title="Profile page example" style="width: 65%">
			<br>
		</div>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 01.02.2024</p>
	<div class="new1">
		<b>Explanation for the duplicate update on 01.02.2024</b><br><br>
		We decided to merge the duplicate problems on the website. This means, that when you solve a problem and it has duplicates,
		it is also solved in other collections. When you had a collection complete and there were unsolved problems again on
		1. February, it means that duplicates were merged and you need to solve it again for all occurrences of that board position.<br><br>
		<div class="text-center">
			<img src="/img/duplicate-explanation.jpg" alt="Duplicate merge explanation" title="Duplicate merge explanation" style="width: 70%">
			<br>
		</div>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 11.01.2024</p>
	<div class="new1">
		<b>Similar problem search</b><br><br>
		This update contains a search function that can be used on the problem pages. It searches for problems that are similar to
		the currently visited problem. A parameter can be set for the maximum difference in stones that are placed on the board.<br><br>
		<div class="text-center">
			<img src="/img/similar-problems-example1.png" alt="Similar problem search, the settings" title="Similar problem search, the settings" style="width: 50%">
			<img src="/img/similar-problems-example2.png" alt="Similar problem search, the results" title="Similar problem search, the results" style="width: 70%">
			<br>
		</div>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 28.12.2023</p>
	<div class="new1">
		<b>Board previews and more achievements</b><br><br>
		It is now possible to see previews of the problems. A preview is shown when you move your mouse over a problem.
		There are also 23 new <a href="/achievements">achievements.</a><br><br>
		<div class="text-center">
			<img src="/img/boardPreviewExample.png" alt="Board preview example" title="Board preview example">
			<br>
		</div>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 23.11.2023</p>
	<div class="new1">
		<br>
		<div class="text-center">
			<a class="menuIcons2" id="darkButton2" onclick="darkAndLight();" title="Light or dark theme" aria-label="Switch between light and dark theme"><span class="theme-icon"></span></a>
			<h1 class="darkButton2">Dark Theme</h1>
			<a class="menuIcons2" id="darkButton3" onclick="darkAndLight();" aria-hidden="true" tabindex="-1"><span class="theme-icon"></span></a>
		</div>
		<br>
		<br>
		<br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 06.11.2023</p>
	<div class="new1">
		<b>More achievements</b><br><br>
		The achievements have been stocked up to 91 and there is now an achievement highscore.<br><br>
		<table style="width: 90%">
			<tr>
				<td><a href="/achievements">View Achievements</a></td>
				<td><a href="/users/achievements">Achievement Highscore</a></td>
			</tr>
		</table>
		<br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 27.10.2023</p>
	<div class="new1">
		<b>Achievements!</b><br><br>
		With today's update, you can hunt for achievements. Achievements give additional XP. There will be a user ranking
		and possibly other rewards. This update contains the first 46 achievements of many more.
		<br><br>
		<a href="/achievements">View Achievements</a>
		<br><br>
		<div class="text-center"><img src="/img/achievementExample.png" alt="Achievements example" style="width: 90%"></div>
		<br>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 14.10.2023</p>
	<div class="new1">
		<b>Improved comments</b><br><br>
		• <b>Link position in comments</b>: It is now possible to link board positions by clicking "Link current position".
		<br>
		<div class="text-center"><img src="/img/commentPositionExample1.jpg" alt="Linking a position in a comment" style="width: 90%"></div>
		<br>
		The position can be accessed by selecting the icon in the comment.
		<br>
		<div class="text-center"><img src="/img/commentPositionExample2.png" alt="Opening a linked position" style="width: 50%"></div>
		<br>
		• <b>Dynamic coordinates</b>: The coordinates written in the comments adjust according to the visible board area.
		<br>
		<div class="text-center"><img src="/img/dynCommentsExample.png" alt="Coordinates adjusting to the visible board area" style="width: 90%"></div>
		<br>
		Example: <a href="/tsumegos/play/25881">1000 Weiqi problems - 58</a>
	</div>
</div>

<div class="news-article">
	<p class="title4">Update 20.07.2023</p>
	<div class="new1">
		<b>New board viewer: BesoGo</b><br><br>
		We changed the technology that is used to display the problems from jGoBoard to BesoGo. There are various advantages, such as
		a tree display in the review, changing views without refresh, editing and saving problems on the website and more. It also opens
		possibilities to merge recurring board positions and to make a better result distinction. Instead of correct and incorrect,
		a future update is going to include more details.
		<br><br>
		<div class="text-center"><img src="/img/besoGoExample.png" alt="BesoGo board viewer" style="width: 60%"></div>
		<br>
		It comes with a separate editor which was the starting point to make this possible. <a href="https://kovarex.github.io/besogo/testing.html" target="_blank">Editor</a>
		<br><br>
	</div>
</div>
