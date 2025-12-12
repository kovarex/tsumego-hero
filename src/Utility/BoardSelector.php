<?php

class BoardSelector
{
	// Note: this data is duplicated in BoardSelector.js
	// the reason for this duplication is, that on client, I can cache this js file and don't have to send it every refresh
	// While on backend, I still need to have access to it, to properly setup the board directly without relying on js reload.
	public static array $boards
		= [
			['name' => 'Pine', 'index' => 1, 'texture' => 'texture1', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Ash', 'index' => 2, 'texture' => 'texture2', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Maple', 'index' => 3, 'texture' => 'texture3', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Shin Kaya', 'index' => 4, 'texture' => 'texture4', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Birch', 'index' => 5, 'texture' => 'texture5', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Wenge', 'index' => 6, 'texture' => 'texture6', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Walnut', 'index' => 7, 'texture' => 'texture7', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Mahogany', 'index' => 8, 'texture' => 'texture8', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Blackwood', 'index' => 9, 'texture' => 'texture9', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Marble 1', 'index' => 10, 'texture' => 'texture10', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Marble 2', 'index' => 11, 'texture' => 'texture11', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Marble 3', 'index' => 12, 'texture' => 'texture12', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Tibet Spruce', 'index' => 13, 'texture' => 'texture13', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Marble 4', 'index' => 14, 'texture' => 'texture14', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Marble 5', 'index' => 15, 'texture' => 'texture15', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Quarry 1', 'index' => 16, 'texture' => 'texture16', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Flowers', 'index' => 17, 'texture' => 'texture17', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Nova', 'index' => 18, 'texture' => 'texture18', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Spring', 'index' => 19, 'texture' => 'texture19', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Moon', 'index' => 20, 'texture' => 'texture20', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Apex', 'index' => 33, 'texture' => 'texture33', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Gold 1', 'index' => 21, 'texture' => 'texture21', 'black' => 'black.png', 'white' => 'whiteKo.png'],
			['name' => 'Amber', 'index' => 22, 'texture' => 'texture22', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Marble 6', 'index' => 34, 'texture' => 'texture34', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Marble 7', 'index' => 35, 'texture' => 'texture35', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Marble 8', 'index' => 36, 'texture' => 'texture36', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Marble 9', 'index' => 37, 'texture' => 'texture37', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Marble 10', 'index' => 38, 'texture' => 'texture38', 'black' => 'black38.png', 'white' => 'white34.png'],
			['name' => 'Jade', 'index' => 39, 'texture' => 'texture39', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Quarry 2', 'index' => 40, 'texture' => 'texture40', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Black Bricks', 'index' => 41, 'texture' => 'texture41', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Wallpaper 1', 'index' => 42, 'texture' => 'texture42', 'black' => 'black34.png', 'white' => 'white42.png'],
			['name' => 'Wallpaper 2', 'index' => 43, 'texture' => 'texture43', 'black' => 'black34.png', 'white' => 'white42.png'],
			['name' => 'Gold & Gray', 'index' => 44, 'texture' => 'texture44', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Gold & Pink', 'index' => 45, 'texture' => 'texture45', 'black' => 'black34.png', 'white' => 'white42.png'],
			['name' => 'Veil', 'index' => 47, 'texture' => 'texture47', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Tiles', 'index' => 48, 'texture' => 'texture48', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Mars', 'index' => 49, 'texture' => 'texture49', 'black' => 'black.png', 'white' => 'white.png'],
			['name' => 'Pink Cloud', 'index' => 50, 'texture' => 'texture50', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Reptile', 'index' => 51, 'texture' => 'texture51', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Mezmerizing', 'index' => 52, 'texture' => 'texture52', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Magenta Sky', 'index' => 53, 'texture' => 'texture53', 'black' => 'black34.png', 'white' => 'white34.png'],
			['name' => 'Tsumego Hero', 'index' => 54, 'texture' => 'texture54', 'black' => 'black54.png', 'white' => 'white54.png'],
			['name' => 'Pretty', 'index' => 23, 'texture' => 'texture23', 'black' => 'black.png', 'white' => 'whiteFlower.png'],
			['name' => 'Hunting', 'index' => 24, 'texture' => 'texture24', 'black' => 'black24.png', 'white' => 'white24.png'],
			['name' => 'Haunted', 'index' => 25, 'texture' => 'texture25', 'black' => 'blackGhost.png', 'white' => 'white.png'],
			['name' => 'Carnage', 'index' => 26, 'texture' => 'texture26', 'black' => 'blackInvis.png', 'white' => 'whiteCarnage.png'],
			['name' => 'Blind Spot', 'index' => 27, 'texture' => 'texture27', 'black' => 'black27.png', 'white' => 'white27.png'],
			['name' => 'Giants', 'index' => 28, 'texture' => 'texture28', 'black' => 'blackGiant.png', 'white' => 'whiteKo.png'],
			['name' => 'Gems', 'index' => 29, 'texture' => 'texture29', 'black' => 'blackKo.png', 'white' => 'whiteKo.png'],
			['name' => 'Grandmaster', 'index' => 30, 'texture' => 'texture55', 'black' => 'blackGalaxy.png', 'white' => 'whiteGalaxy.png']
		];

	public static $GOLDEN_BOARD = ['name' => 'Golden', 'texture' => 'textureGolden', 'black' => 'black34.png', 'white' => 'white34.png'];

	public static function filterValidBits($boardsSelection)
	{
		$allowedBits = 0;
		// linear search, but we search once per refresh, so w/e
		foreach (self::$boards as $board)
			$allowedBits |= (1 << ($board['index'] - 1));
		return $boardsSelection & $allowedBits;
	}

	public static function getBoardInfo($index)
	{
		// linear search, but we search once per refresh, so w/e
		foreach (self::$boards as $board)
			if ($board['index'] == $index)
				return $board;
		return self::$boards[0];
	}

	public static function selectBoard($boardsBitmask, $goldenTsumego, $setPreference)
	{
		if (!is_null($setPreference))
			return self::getBoardInfo($setPreference);

		if ($goldenTsumego)
			return self::$GOLDEN_BOARD;
		$indexes = [];

		foreach (self::$boards as $board)
			if ($boardsBitmask & (1 << ($board['index'] - 1)))
				$indexes[] = $board['index'];

		if (empty($indexes))
			return self::getBoardInfo(1);

		return self::getBoardInfo($indexes[array_rand($indexes)]);
	}

	public static $DEFAULT_BOARDS_BITMASK = 0b11111111;
}
