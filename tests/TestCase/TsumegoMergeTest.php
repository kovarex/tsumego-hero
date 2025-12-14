<?php

class TsumegoMergeTest extends ControllerTestCase
{
	public function testMergeTwoTsumegos()
	{
		$browser = Browser::instance();
		$version1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$version2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[be]C[+])';
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-tsumegos' =>	[
				['status' => 'V', 'rating' => 850, 'sgf' => $version1, 'description' => '500 rating tsumego', 'sets' => [['name' => 'set 1', 'num' => '1']]],
				['status' => 'S', 'rating' => 100, 'sgf' => $version2, 'description' => '1000 rating tsumego', 'sets' => [['name' => 'set 2', 'num' => '1']]]
			]
		]);
		$browser->get('/users/duplicates');
	}
}
