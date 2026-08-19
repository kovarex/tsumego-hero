<?php

class SetConnection extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'set_connection';
		parent::__construct($id, $table, $ds);
	}

	public $belongsTo = [
		'Tsumego',
		'Set',
	];

	/**
	 * Find the "primary" set connection for a tsumego — the one in the
	 * lowest-order set. Official sets have low order values, user-created
	 * sets and Favorites have high ones, so ORDER BY set.order ASC picks
	 * the most meaningful collection first.
	 */
	public function findPrimaryForTsumego(int $tsumegoId): ?array
	{
		return $this->find('first', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $tsumegoId],
			'order' => ['S.order ASC', 'SetConnection.id ASC'],
		]);
	}
}
