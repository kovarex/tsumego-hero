<?php

class TagTest extends ControllerTestCase
{
	public function testAddTagConnection()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [['sets' => [['name' => 'set-1', 'num' => 1]]]],
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('open-add-tag-menu');
		$browser->clickId('open-more-tags');
		$browser->clickId('tag-snapback');
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$tagConnection = ClassRegistry::init('TagConnection')->find('first', []);
		$this->assertNotNull($tagConnection);
	}
}
