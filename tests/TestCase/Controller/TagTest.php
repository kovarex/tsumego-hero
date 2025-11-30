<?php

class TagTest extends ControllerTestCase
{
	public function testAddTagConnection()
	{
		foreach ([false, true] as $isAdmin)
		{
			$context = new ContextPreparator([
				'user' => ['admin' => $isAdmin],
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
			$this->assertSame($tagConnection['TagConnection']['approved'], $isAdmin ? 1 : 0);
			$this->assertNotNull($browser->getCssSelect("#tag-list #snapback")); // tag was added to the list
		}
	}
}
