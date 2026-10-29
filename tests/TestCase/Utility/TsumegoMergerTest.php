<?php

use App\Utility\TsumegoMerger;
use App\Utility\Util;

class TsumegoMergerTest extends IntegrationTestCase
{
	//The merge runs in a transaction: a problem of a collection that has the master too loses its
	//connection there, the deletion taking part in the transaction instead of starting its own.
	public function testMergingProblemsOfTheSameCollectionDropsTheConnectionOfTheMergedOne(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc];B[aa])'],
			['set_order' => 2, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc];B[aa])']]]);
		[$masterID, $slaveID] = array_column($context->tsumegos, 'id');

		$result = new TsumegoMerger($masterID, $slaveID)->execute();

		$this->assertSame(['message' => 'Tsumegos merged.', 'type' => 'success'], $result);
		$this->assertSame([$masterID], array_column(Util::query('SELECT tsumego_id FROM set_connection WHERE set_id = ?', [$context->tsumegos[0]['sets'][0]['id']]), 'tsumego_id'));
		$this->assertFalse($this->fetchTable('Tsumego')->exists(['id' => $slaveID]));
	}
}
