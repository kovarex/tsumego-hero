<?php

class BoardSelectorTest extends CakeTestCase
{
	public function testChangeSelection()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['boards_bitmask' => 2]]);
		$browser->get('/');
		$browser->clickId('check3');
		$this->assertTrue($browser->find('#newCheck2')->isSelected()); // board 1 is unselected
		$this->assertFalse($browser->find('#newCheck1')->isSelected()); // board 2 is selected
		$browser->clickId('newCheck2'); // unclick board 2
		$browser->clickId('newCheck1'); // select board 1
		$browser->get('/'); // refresh
		$this->assertSame(1, $context->reloadUser()['boards_bitmask']); // only board 1 is selected now
		$browser->clickId('check3');
		$this->assertFalse($browser->find('#newCheck2')->isSelected()); // board 2 is unselected
		$this->assertTrue($browser->find('#newCheck1')->isSelected()); // board 1 is selected
	}

	public function testUnselectAllInBoardSelection()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['boards_bitmask' => 3]]);
		$browser->get('/');
		$browser->clickId('check3');
		$this->assertTrue($browser->find('#newCheck2')->isSelected()); // board 1 is selected
		$this->assertTrue($browser->find('#newCheck1')->isSelected()); // board 2 is selected

		// we unselect all first
		$browser->clickId('boards-unselect-all');
		$this->assertFalse($browser->find('#newCheck2')->isSelected()); // board 1 is unselected
		$this->assertFalse($browser->find('#newCheck1')->isSelected()); // board 2 is unselected
		$browser->get('/');

		// refresh that the value is saved and displayed as unselected all
		$this->assertSame(0, $context->reloadUser()['boards_bitmask']); // only board 1 is selected now
		$browser->clickId('check3');
		$this->assertFalse($browser->find('#newCheck2')->isSelected()); // board 2 is unselected
		$this->assertFalse($browser->find('#newCheck1')->isSelected()); // board 1 is unselected

		// now we select all
		$browser->clickId('boards-unselect-all');
		$this->assertTrue($browser->find('#newCheck2')->isSelected()); // board 2 is unselected
		$this->assertTrue($browser->find('#newCheck1')->isSelected()); // board 1 is unselected

		// refresh that the value is saved and displayed as selected all
		$browser->get('/');
		// 17979210916167679 is bitmask of all the boards enabled
		$this->assertSame(17979210916167679, $context->reloadUser()['boards_bitmask']); // only board 1 is selected now
		$browser->clickId('check3');
		$this->assertTrue($browser->find('#newCheck2')->isSelected()); // board 2 is selected
		$this->assertTrue($browser->find('#newCheck1')->isSelected()); // board 1 is selected

	}

	public function testSelectionAffectsBoardShown()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
		'user' => ['boards_bitmask' => 2],
		'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => '1']]]]]);
		$expectedBoardSelection = BoardSelector::getBoardInfo(2);
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertSame($expectedBoardSelection['texture'], $browser->driver->executeScript('return window.besogo.theme;'));
		$this->assertSame($expectedBoardSelection['black'], $browser->driver->executeScript('return window.besogo.editor.BLACK_STONES;'));
		$this->assertSame($expectedBoardSelection['white'], $browser->driver->executeScript('return window.besogo.editor.WHITE_STONES;'));
	}

	public function testGoldenTsumegoThemeSelection()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
		'user' => ['boards_bitmask' => 2],
		'other-tsumegos' => [['status' => 'G', 'sets' => [['name' => 'set 1', 'num' => '1']]]]]);
		$expectedBoardSelection = BoardSelector::getBoardInfo(BoardSelector::$GOLDEN_TSUMEGO_INDEX);
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertSame($expectedBoardSelection['texture'], $browser->driver->executeScript('return window.besogo.theme;'));
		$this->assertSame($expectedBoardSelection['black'], $browser->driver->executeScript('return window.besogo.editor.BLACK_STONES;'));
		$this->assertSame($expectedBoardSelection['white'], $browser->driver->executeScript('return window.besogo.editor.WHITE_STONES;'));
	}

	public function testSetThemeSelection()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
		'user' => ['boards_bitmask' => 2],
		'other-tsumegos' => [['status' => 'G', 'sets' => [['name' => 'set 1', 'num' => '1', 'board_theme_index' => 50]]]]]);
		$expectedBoardSelection = BoardSelector::getBoardInfo(50);
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertSame($expectedBoardSelection['texture'], $browser->driver->executeScript('return window.besogo.theme;'));
		$this->assertSame($expectedBoardSelection['black'], $browser->driver->executeScript('return window.besogo.editor.BLACK_STONES;'));
		$this->assertSame($expectedBoardSelection['white'], $browser->driver->executeScript('return window.besogo.editor.WHITE_STONES;'));
	}
}
