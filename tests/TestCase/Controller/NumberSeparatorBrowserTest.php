<?php

use App\Utility\Util;

//Numbers of five digits and more read 12 345, the thousands apart by a narrow no-break space, never a comma;
//four digits stay plain. Once as the server writes them, once as the account bar's script does.
#[RetryOnBrowserFailure]
class NumberSeparatorBrowserTest extends IntegrationTestCase
{
	private const GROUPED = '/^\d{1,3}(\x{202F}\d{3})+$/u';

	public function testTheLevelHighscoreGroupsTheXpWithANarrowSpace(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'Veteran', 'level' => 100, 'xp' => 12345]]]);
		$browser = Browser::instance();
		$browser->get('users/highscore');
		$row = $browser->find('.data-table tr:nth-child(2)');
		$this->assertStringContainsString('DN_Veteran', $row->getText());
		$xp = $browser->find('.data-table tr:nth-child(2) td:nth-last-child(2)')->getText();
		$this->assertMatchesRegularExpression(self::GROUPED, $xp);
		$this->assertStringNotContainsString(',', $row->getText());
		$browser->assertNoJsErrors();
	}

	public function testTheAccountBarGroupsTheXpOnHover(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'Veteran', 'level' => 100, 'xp' => 12345]]);
		$browser = Browser::instance();
		$browser->setCookie('showInAccountWidget', 'level');
		$browser->get('users/highscore');
		$browser->hover($browser->find('#account-bar-wrapper'));
		$browser->waitUntilJs('document.getElementById("account-bar-xp").textContent.includes("/")');
		[$xp, $next] = explode('/', $browser->find('#account-bar-xp')->getText());
		// the page load grants the level achievements and their XP, so the bar shows the XP the row has after it
		$stored = (int) Util::query('SELECT xp FROM user WHERE id = ?', [$context->user['id']])[0]['xp'];
		$this->assertGreaterThanOrEqual(10000, $stored);
		$this->assertSame(Util::formatNumber($stored), $xp);
		$this->assertMatchesRegularExpression(self::GROUPED, $xp);
		$this->assertMatchesRegularExpression(self::GROUPED, $next);
		$browser->assertNoJsErrors();
	}
}
