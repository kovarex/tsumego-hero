<?php

use App\Utility\BoardSelector;
use Cake\ORM\TableRegistry;

//The pictures whose names the code puts together (a board's texture and stones, a hero power with its
//hovered and unusable look, a rank of the time mode, an achievement, the quote of the day) are in
//webroot/. A page shows only some of them, so the smoke test, which checks the pictures of each page,
//can't tell whether the others are there.
class ImageNamesTest extends AppTestCase
{
	private static function assertPicture(string $path): void
	{
		self::assertFileExists(WWW_ROOT . ltrim($path, '/'), "$path is missing");
	}

	public function testEveryBoardHasItsTextureAndStones(): void
	{
		foreach ([...BoardSelector::$boards, BoardSelector::$GOLDEN_BOARD] as $board)
		{
			$css = '/besogo/css/board-' . $board['texture'] . '.css';
			self::assertPicture($css);
			preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/', file_get_contents(WWW_ROOT . ltrim($css, '/')), $urls);
			$this->assertNotEmpty($urls[1], "$css names no texture");
			foreach ($urls[1] as $url)
				self::assertPicture('/besogo/css/' . $url);
			self::assertPicture('/besogo/img/' . $board['black']);
			self::assertPicture('/besogo/img/' . $board['white']);
		}
	}

	//besogo.realStone picks one of black0 to black3 and white0 to white10 when the board names no stones
	public function testTheDefaultStonesAreThere(): void
	{
		for ($i = 0; $i < 4; $i++)
			self::assertPicture("/besogo/img/black$i.png");
		for ($i = 0; $i < 11; $i++)
			self::assertPicture("/besogo/img/white$i.png");
	}

	//webroot/js/util.js enableHeroPower and disableHeroPower, src/Utility/HeroPowers.php
	public function testEveryHeroPowerHasItsPictures(): void
	{
		foreach ([1, 2, 3, 4, 6] as $number)
			foreach (['', 'h', 'x'] as $suffix)
				self::assertPicture("/img/hp$number$suffix.png");
		self::assertPicture('/img/hp5.png');
	}

	//templates/TimeMode/overview.php, templates/TimeMode/result.php, webroot/js/TimeModePages.js
	public function testEveryTimeModeRankAndCategoryHasItsPictures(): void
	{
		$ranks = [];
		for ($kyu = 15; $kyu >= 1; $kyu--)
			$ranks[] = $kyu . 'k';
		for ($dan = 1; $dan <= 5; $dan++)
			$ranks[] = $dan . 'd';
		foreach ($ranks as $rank)
			foreach (['', 'hover', 'inactive'] as $suffix)
				self::assertPicture("/img/rankButton$rank$suffix.png");
		foreach ([1, 2, 3] as $category)
			foreach (['', 'inactive', 'hover'] as $state)
				self::assertPicture("/img/timeMode$category{$state}2.png");
	}

	//The achievement list, its page (ac000i before it is earned) and the alert of a new one
	public function testEveryAchievementHasItsPicture(): void
	{
		$achievements = TableRegistry::getTableLocator()->get('Achievement')->find()->all();
		$this->assertNotEmpty($achievements);
		foreach ($achievements as $achievement)
			self::assertPicture('/img/' . $achievement->image . '.png');
		self::assertPicture('/img/ac000i.png');
	}

	//src/Utility/DailyCron.php deduceQuoteToUse, templates/Sites/index.php
	public function testEveryQuoteOfTheDayHasItsPictures(): void
	{
		for ($i = 1; $i < 13; $i++)
			foreach (['', 'e', 'u'] as $suffix)
				self::assertPicture(sprintf('/img/new_startpage/q%02d%s.png', $i, $suffix));
	}

	//src/Utility/Play.php (the hearts of the play page), src/Model/Table/UserTable.php (the account type)
	public function testTheHeartsAndAccountTypesAreThere(): void
	{
		foreach (['heart1', 'heart1small', 'heart2', 'heart2small', 'premium1', 'premium2'] as $name)
			self::assertPicture("/img/$name.png");
	}

	//The buttons that turn and flip the board, webroot/besogo/js/toolPanel.js
	public function testTheBoardOrientationButtonsAreThere(): void
	{
		foreach (['T', 'L', 'TL', 'R', 'TR', 'B', 'BL'] as $corner)
			self::assertPicture("/img/boardOrientation$corner.png");
		self::assertPicture('/img/boardSpinClockwise.png');
		self::assertPicture('/img/boardSpinCounterClockwise.png');
	}

	//webroot/js/previewBoard.js draws a board of 13 lines or fewer on its picture
	public function testThePreviewBoardsAreThere(): void
	{
		foreach (['theBoard.png', 'theBoard2.png', 'theBoard4x4.png', 'theBoard5x5.png', 'theBoard9x9.png', 'theBoard13x13.png'] as $name)
			self::assertPicture("/img/$name");
	}
}
