<?php

App::uses('AppController', 'Controller');

/**
 * TsumegoImagesController
 *
 * Generates PNG images of tsumego puzzles for Open Graph social sharing.
 */
class TsumegoImagesController extends AppController
{
	/**
	 * Generate puzzle image for Open Graph/social media sharing
	 *
	 * Renders a Go board with the puzzle's initial position as PNG image.
	 * Uses PHP GD for direct PNG generation without file storage.
	 *
	 * @param int $tsumegoId Puzzle ID
	 * @return CakeResponse PNG image with HTTP caching headers
	 */
	/**
	 * Generate a PNG image for a tsumego puzzle for Open Graph sharing
	 *
	 * @param int|null $setConnectionId The SetConnection ID (same as /id route)
	 * @return CakeResponse PNG image with caching headers
	 */
	public function tsumegoImage($setConnectionId = null)
	{
		if (!$setConnectionId || !is_numeric($setConnectionId))
			throw new NotFoundException('Invalid set connection ID');

		// Load set connection to get the tsumego ID
		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionId);
		if (!$setConnection)
			throw new NotFoundException('Set connection not found');

		$tsumegoId = $setConnection['SetConnection']['tsumego_id'];

		// Load puzzle data
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		if (!$tsumego)
			throw new NotFoundException('Puzzle not found');

		// Load set to get title
		$setId = $setConnection['SetConnection']['set_id'];
		$set = ClassRegistry::init('Set')->findById($setId);
		$setTitle = $set['Set']['title'] ?? 'Tsumego';

		// Load SGF data - SGF table has tsumego_id foreign key
		$sgf = ClassRegistry::init('Sgf')->find('first', [
			'conditions' => ['tsumego_id' => $tsumegoId],
			'order' => ['id' => 'ASC'] // Get first SGF if multiple exist
		]);
		if (!$sgf || empty($sgf['Sgf']['sgf']))
			throw new NotFoundException('SGF not found');

		// Generate PNG image
		$pngData = $this->_generatePuzzleImage(
			$sgf['Sgf']['sgf'],
			$setTitle,
			$tsumego['Tsumego']['description'] ?? ''
		);

		// Serve with caching
		$this->response->type('png');
		$this->response->body($pngData);
		$this->response->cache('+1 year'); // Long cache since puzzles rarely change
		$this->response->etag(md5($sgf['Sgf']['sgf'] . $tsumegoId)); // ETag for validation

		// Use the tsumego modified timestamp for Last-Modified header
		if (!empty($tsumego['Tsumego']['modified']))
			$this->response->modified($tsumego['Tsumego']['modified']);

		return $this->response;
	}

	/**
	 * Generate puzzle image using PHP GD
	 *
	 * @param string $sgfString SGF content
	 * @param string $setTitle Set/collection title (e.g., "Life & Death - Intermediate")
	 * @param string $description Problem description (e.g., "[b] to live.")
	 * @return string PNG image data
	 */
	private function _generatePuzzleImage($sgfString, $setTitle, $description)
	{
		// Parse SGF and extract stone positions FIRST
		$stones = $this->_parseSgfStones($sgfString);
		if (empty($stones))
			throw new InternalErrorException('No stones found in SGF');

		// Calculate bounding box of stones
		$minX = $minY = 18;
		$maxX = $maxY = 0;
		foreach ($stones as $stone)
		{
			$minX = min($minX, $stone['x']);
			$maxX = max($maxX, $stone['x']);
			$minY = min($minY, $stone['y']);
			$maxY = max($maxY, $stone['y']);
		}

		// Add padding (2 intersections on each side, but don't go outside board)
		$padding = 2;
		$minX = max(0, $minX - $padding);
		$minY = max(0, $minY - $padding);
		$maxX = min(18, $maxX + $padding);
		$maxY = min(18, $maxY + $padding);

		$cropWidth = $maxX - $minX + 1;  // Number of intersections
		$cropHeight = $maxY - $minY + 1;

		// Determine if board should be rotated (portrait -> landscape)
		$rotate = $cropHeight > $cropWidth;

		// If rotating, swap width/height for calculations
		if ($rotate)
		{
			$temp = $cropWidth;
			$cropWidth = $cropHeight;
			$cropHeight = $temp;
		}

		// Image dimensions - Open Graph standard (1.91:1 aspect ratio)
		$width = 1200;
		$height = 630;

		// Calculate cell size to fill image (leave small margins)
		$margin = 40;
		$availableWidth = $width - (2 * $margin);
		$availableHeight = $height - (2 * $margin);

		// Scale to fill the image, respecting board aspect ratio
		$boardAspectRatio = $cropWidth / $cropHeight;
		$imageAspectRatio = $availableWidth / $availableHeight;

		if ($boardAspectRatio > $imageAspectRatio)
		{
			// Board is wider - fit to width
			$cellSize = $availableWidth / ($cropWidth - 1);
		}
		else
		{
			// Board is taller or square - fit to height
			$cellSize = $availableHeight / ($cropHeight - 1);
		}

		// Calculate board dimensions
		$boardPixelsWidth = ($cropWidth - 1) * $cellSize;
		$boardPixelsHeight = ($cropHeight - 1) * $cellSize;

		// Center the board
		$boardX = ($width - $boardPixelsWidth) / 2;
		$boardY = ($height - $boardPixelsHeight) / 2;

		// Create image
		$img = imagecreatetruecolor($width, $height);

		// Define colors
		$bgColor = imagecolorallocate($img, 220, 179, 92); // Wood/tan background
		$boardColor = imagecolorallocate($img, 210, 170, 85); // Slightly darker for board
		$lineColor = imagecolorallocate($img, 0, 0, 0); // Black lines
		$blackStone = imagecolorallocate($img, 20, 20, 20); // Almost black
		$whiteStone = imagecolorallocate($img, 250, 250, 250); // Almost white
		$textColor = imagecolorallocate($img, 50, 50, 50); // Dark gray text

		// Fill background
		imagefill($img, 0, 0, $bgColor);

		// Draw board background
		imagefilledrectangle($img, $boardX, $boardY, $boardX + ($cropWidth - 1) * $cellSize, $boardY + ($cropHeight - 1) * $cellSize, $boardColor);

		// Draw grid lines
		imagesetthickness($img, 1);
		for ($i = 0; $i < $cropWidth; $i++)
		{
			$pos = $boardX + $i * $cellSize;
			// Vertical line
			imageline($img, $pos, $boardY, $pos, $boardY + ($cropHeight - 1) * $cellSize, $lineColor);
		}
		for ($i = 0; $i < $cropHeight; $i++)
		{
			$pos = $boardY + $i * $cellSize;
			// Horizontal line
			imageline($img, $boardX, $pos, $boardX + ($cropWidth - 1) * $cellSize, $pos, $lineColor);
		}

		// Draw star points (hoshi) - only if they're in the cropped area
		$starPoints = $this->_getStarPoints(19);
		foreach ($starPoints as $point)
			if ($point[0] >= $minX && $point[0] <= $maxX && $point[1] >= $minY && $point[1] <= $maxY)
			{
				if ($rotate)
				{
					// Swap coordinates for rotated board
					$x = $boardX + ($point[1] - $minY) * $cellSize;
					$y = $boardY + ($point[0] - $minX) * $cellSize;
				}
				else
				{
					$x = $boardX + ($point[0] - $minX) * $cellSize;
					$y = $boardY + ($point[1] - $minY) * $cellSize;
				}
				$starSize = (int) ($cellSize * 0.2);
				imagefilledellipse($img, (int) $x, (int) $y, $starSize, $starSize, $lineColor);
			}

		// Draw stones (adjusted for cropped coordinates and rotation)
		$stoneRadius = $cellSize * 0.45; // Stones slightly smaller than cells
		foreach ($stones as $stone)
		{
			if ($rotate)
			{
				// Swap coordinates for rotated board
				$x = $boardX + ($stone['y'] - $minY) * $cellSize;
				$y = $boardY + ($stone['x'] - $minX) * $cellSize;
			}
			else
			{
				$x = $boardX + ($stone['x'] - $minX) * $cellSize;
				$y = $boardY + ($stone['y'] - $minY) * $cellSize;
			}

			if ($stone['color'] === 'B')
			{
				// Black stone with slight gradient effect
				$diameter = (int) ($stoneRadius * 2);
				imagefilledellipse($img, (int) $x, (int) $y, $diameter, $diameter, $blackStone);
			}
			else
			{
				// White stone with black border
				$diameter = (int) ($stoneRadius * 2);
				imagefilledellipse($img, (int) $x, (int) $y, $diameter, $diameter, $whiteStone);
				imageellipse($img, (int) $x, (int) $y, $diameter, $diameter, $lineColor);
			}
		}

		// Convert to PNG and return
		ob_start();
		imagepng($img, null, 6); // Compression level 6 (balance speed/size)
		$pngData = ob_get_clean();
		imagedestroy($img);

		return $pngData;
	}

	/**
	 * Get star point coordinates for given board size
	 *
	 * @param int $size Board size (9, 13, or 19)
	 * @return array Array of [x, y] coordinates
	 */
	private function _getStarPoints($size)
	{
		if ($size === 19)
		{
			return [
				[3, 3], [9, 3], [15, 3],
				[3, 9], [9, 9], [15, 9],
				[3, 15], [9, 15], [15, 15]
			];
		}
		elseif ($size === 13)
		{
			return [
				[3, 3], [9, 3],
				[6, 6],
				[3, 9], [9, 9]
			];
		}
		elseif ($size === 9)
		{
			return [
				[2, 2], [6, 2],
				[4, 4],
				[2, 6], [6, 6]
			];
		}

		return [];
	}

	/**
	 * Parse SGF string to extract stone positions
	 *
	 * Extracts AB (add black), AW (add white) tags from SGF.
	 * Handles both single format AB[fa] and compact format AB[fa][bb][fc].
	 * Returns initial position suitable for tsumego problems.
	 *
	 * @param string $sgf SGF string
	 * @return array Array of ['x' => int, 'y' => int, 'color' => 'B'|'W']
	 */
	private function _parseSgfStones($sgf)
	{
		$stones = [];

		// Match AB[coords][coords]... (add black stones) - compact format
		// Find AB tag, then capture all following [xx] coordinates
		if (preg_match('/AB((?:\[[a-s]{2}\])+)/', $sgf, $match))
		{
			// Extract all [xx] coordinates from the captured group
			preg_match_all('/\[([a-s]{2})\]/', $match[1], $coords);
			foreach ($coords[1] as $coord)
				$stones[] = array_merge($this->_sgfCoordToXY($coord), ['color' => 'B']);
		}

		// Match AW[coords][coords]... (add white stones) - compact format
		if (preg_match('/AW((?:\[[a-s]{2}\])+)/', $sgf, $match))
		{
			preg_match_all('/\[([a-s]{2})\]/', $match[1], $coords);
			foreach ($coords[1] as $coord)
				$stones[] = array_merge($this->_sgfCoordToXY($coord), ['color' => 'W']);
		}

		return $stones;
	}

	/**
	 * Convert SGF coordinate (e.g., "aa", "pd") to x,y array coordinates
	 *
	 * SGF uses lowercase letters: a=0, b=1, ..., s=18
	 *
	 * @param string $coord Two-letter SGF coordinate
	 * @return array ['x' => int, 'y' => int]
	 */
	private function _sgfCoordToXY($coord)
	{
		if (strlen($coord) !== 2)
			return ['x' => 0, 'y' => 0];

		$x = ord($coord[0]) - ord('a');
		$y = ord($coord[1]) - ord('a');

		return ['x' => $x, 'y' => $y];
	}
}
