<?php

class SetTest extends CakeTestCase
{
	//Set::url() builds the address itself instead of asking the router, which would try the routes
	//one by one for every card of the collections page. It has to give the address the router gives.
	public function testUrlIsTheAddressTheRouterGives(): void
	{
		$ids = [5, '5', 1234567, '5k', '10k-5k', '1d', 'favorites', 'life and death', 'Ko & Seki', 'semeai+', 'ünïcode', "it's", 'a/b', 'delete', 'index'];
		foreach ($ids as $id)
			foreach ([1, 2, 17] as $partition)
				foreach ([false, true] as $full)
				{
					$url = ['controller' => 'sets', 'action' => 'view', 'id' => $id];
					if ($partition > 1)
						$url['partition'] = $partition;
					$this->assertSame(Router::url($url, $full), Set::url($id, $partition, $full), 'id ' . $id . ', page ' . $partition . ($full ? ', full' : ''));
				}
	}
}
