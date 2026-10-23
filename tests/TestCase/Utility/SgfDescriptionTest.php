<?php

use App\Utility\SgfDescription;

class SgfDescriptionTest extends CakeTestCase
{
	public function testShortDescriptionIsShownWhole()
	{
		$this->assertSame('Added D16 &amp; E16', SgfDescription::render("Added D16\n& E16"));
		$this->assertSame('', SgfDescription::render(null));
	}

	public function testLongDescriptionIsShortenedWithTheWholeTextInTheTooltip()
	{
		$description = str_repeat('word ', 30) . "\n<end>";
		$rendered = SgfDescription::render($description);
		$this->assertStringStartsWith('<span title="' . h($description) . '">word word', $rendered);
		$this->assertStringEndsWith('word…</span>', $rendered);
		$this->assertStringNotContainsString('<end>', $rendered);
	}
}
