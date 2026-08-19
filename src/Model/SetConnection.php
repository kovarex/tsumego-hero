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
	 * Ordering that picks a representative set for display: public before
	 * private before deleted, official (unowned) collections before user-owned
	 * sets, then curated order ascending (NULLs last), then earliest set, then
	 * earliest connection. A tsumego may belong to many sets; this is a
	 * display preference, not an identity.
	 */
	public static function displayOrderSql(string $setAlias, string $connectionAlias): string
	{
		return "$setAlias.public DESC, ($setAlias.user_id IS NULL) DESC, ($setAlias.order IS NULL) ASC, $setAlias.order ASC, $setAlias.id ASC, $connectionAlias.id ASC";
	}

	/**
	 * Find a representative set connection for a tsumego, for display only.
	 */
	public function findDisplaySetConnection(int $tsumegoId): ?array
	{
		return $this->find('first', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $tsumegoId],
			'order' => self::displayOrderSql('S', 'SetConnection'),
		]);
	}
}
