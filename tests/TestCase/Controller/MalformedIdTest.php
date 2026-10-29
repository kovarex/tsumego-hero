<?php

//A part of an address that stands for an id but is no whole number names nothing: 404, not 500.
class MalformedIdTest extends IntegrationTestCase
{
	private const GET_ADDRESSES = [
		'/users/view/abc',
		'/users/view/1abc',
		'/achievements/view/1abc',
		'/achievements/view/1/abc',
		'/sgfs/view/abc',
		'/sgf/fetch/abc',
		'/sgf/info/1.5',
		'/avatars/picture/abc',
		'/avatars/boardOfUser/abc',
		'/time_mode/result/abc',
		'/tsumegos/setupSgfStep2/abc/b',
	];

	private const POST_ADDRESSES = [
		'/sets/removeTsumego/abc',
		'/sets/reorderTsumego/abc',
		'/sets/resetProgress/abc/1',
		'/users/acceptSGFProposal/abc',
		'/users/rejectSGFProposal/abc',
		'/users/reopenSGFProposal/abc',
	];

	public function testAMalformedIdIsNotFound(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);

		foreach (self::GET_ADDRESSES as $address)
		{
			$this->get($address);
			$this->assertResponseCode(404, $address);
		}
		foreach (self::POST_ADDRESSES as $address)
		{
			$this->post($address);
			$this->assertResponseCode(404, $address);
		}
	}

	public function testAWellFormedIdStillOpensThePage(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true]]);

		$this->get('/users/view/' . $context->user['id']);

		$this->assertResponseCode(200);
	}
}
