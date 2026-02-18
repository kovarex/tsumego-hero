<?php

App::uses('AppController', 'Controller');
App::uses('Constants', 'Utility');

/**
 * TsumegoImagesController
 *
 * Generates PNG images of tsumego puzzles for Open Graph social sharing.
 */
class TsumegoImagesController extends AppController
{
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

		// Single query: join SetConnection → Tsumego → Sgf + Set, fetch only needed fields
		$data = ClassRegistry::init('SetConnection')->query(
			'SELECT s.title AS set_title, t.description, t.created, sgf.sgf
			FROM set_connection sc
			JOIN tsumego t ON t.id = sc.tsumego_id
			JOIN `set` s ON s.id = sc.set_id
			JOIN sgf ON sgf.tsumego_id = t.id AND sgf.accepted = 1
			WHERE sc.id = ?
			ORDER BY sgf.id DESC
			LIMIT 1',
			[(int) $setConnectionId]
		);

		if (empty($data))
			throw new NotFoundException('Puzzle not found');

		$row = $data[0];
		$sgfString = $row['sgf']['sgf'];
		$setTitle = $row['s']['set_title'] ?? 'Tsumego';
		$description = $row['t']['description'] ?? '';

		if (empty($sgfString))
			throw new NotFoundException('SGF not found');

		$imageData = $this->_generatePuzzleImage($sgfString, $setTitle, $description);

		$this->response->type('png');
		$this->response->body($imageData);
		$this->response->etag(md5(Constants::$TSUMEGO_IMAGE_VERSION . $sgfString));
		$modified = !empty($row['t']['created']) ? $row['t']['created'] : '-1 day';
		$this->response->cache($modified, '+1 year');

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
		// Parse board size from SGF (default 19)
		$boardSize = 19;
		if (preg_match('/SZ\[(\d+)\]/', $sgfString, $szMatch))
			$boardSize = (int) $szMatch[1];

		// Parse SGF and extract stone positions
		$stones = $this->_parseSgfStones($sgfString);
		if (empty($stones))
			throw new InternalErrorException('No stones found in SGF');

		// Calculate bounding box of stones
		$minX = $minY = $boardSize - 1;
		$maxX = $maxY = 0;
		foreach ($stones as $stone)
		{
			$minX = min($minX, $stone['x']);
			$maxX = max($maxX, $stone['x']);
			$minY = min($minY, $stone['y']);
			$maxY = max($maxY, $stone['y']);
		}

		// Normalize to bottom-right corner for consistent display
		// (like besogo's corner normalization, but always targeting bottom-right)
		$centerX = ($minX + $maxX) / 2.0;
		$centerY = ($minY + $maxY) / 2.0;
		$hFlip = $centerX < ($boardSize - 1) / 2.0; // stones closer to left → flip right
		$vFlip = $centerY < ($boardSize - 1) / 2.0; // stones closer to top → flip down
		if ($hFlip || $vFlip)
		{
			foreach ($stones as &$stone)
			{
				if ($hFlip)
					$stone['x'] = $boardSize - 1 - $stone['x'];
				if ($vFlip)
					$stone['y'] = $boardSize - 1 - $stone['y'];
			}
			unset($stone);
			// Recalculate bounding box after flip
			$minX = $minY = $boardSize - 1;
			$maxX = $maxY = 0;
			foreach ($stones as $stone)
			{
				$minX = min($minX, $stone['x']);
				$maxX = max($maxX, $stone['x']);
				$minY = min($minY, $stone['y']);
				$maxY = max($maxY, $stone['y']);
			}
		}

		// Add padding (2 intersections on each side, but don't go outside board)
		$padding = 2;
		$minX = max(0, $minX - $padding);
		$minY = max(0, $minY - $padding);
		$maxX = min($boardSize - 1, $maxX + $padding);
		$maxY = min($boardSize - 1, $maxY + $padding);

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

		// Calculate cell size — account for stone overhang and coordinate labels
		// Stones extend 0.45*cellSize beyond grid + shadow adds ~0.1*cellSize more
		$stoneOverhang = 0.55; // safety margin for stone radius + shadow
		// Labels need extra space: right (row numbers) and bottom (column letters)
		$labelSpace = 1.0; // ~1 cellSize worth of space for labels
		$effectiveCropWidth = ($cropWidth - 1) + 2 * $stoneOverhang + $labelSpace;
		$effectiveCropHeight = ($cropHeight - 1) + 2 * $stoneOverhang + $labelSpace;

		$margin = 20;
		$availableWidth = $width - (2 * $margin);
		$availableHeight = $height - (2 * $margin);

		$cellSize = min(
			$availableWidth / $effectiveCropWidth,
			$availableHeight / $effectiveCropHeight
		);

		// Calculate board dimensions
		$boardPixelsWidth = ($cropWidth - 1) * $cellSize;
		$boardPixelsHeight = ($cropHeight - 1) * $cellSize;

		// Offset board center: shift LEFT for right labels, shift UP for bottom labels
		$labelPixels = $cellSize * $labelSpace * 0.5;
		$boardX = ($width - $boardPixelsWidth) / 2 - $labelPixels;
		$boardY = ($height - $boardPixelsHeight) / 2 - $labelPixels;

		// Create image
		$img = imagecreatetruecolor($width, $height);
		imageantialias($img, true);

		// Define colors
		$bgColor = imagecolorallocate($img, 220, 179, 92); // Wood/tan background
		$boardColor = imagecolorallocate($img, 210, 170, 85); // Slightly darker for board
		$lineColor = imagecolorallocate($img, 0, 0, 0); // Black lines
		$borderColor = imagecolorallocate($img, 60, 40, 10); // Dark brown board frame

		// Fill background
		imagefill($img, 0, 0, $bgColor);

		// Draw board background with subtle frame
		$frameWidth = 4;
		imagefilledrectangle(
			$img,
			(int) ($boardX - $frameWidth),
			(int) ($boardY - $frameWidth),
			(int) ($boardX + ($cropWidth - 1) * $cellSize + $frameWidth),
			(int) ($boardY + ($cropHeight - 1) * $cellSize + $frameWidth),
			$borderColor
		);
		imagefilledrectangle(
			$img,
			(int) ($boardX - $frameWidth + 2),
			(int) ($boardY - $frameWidth + 2),
			(int) ($boardX + ($cropWidth - 1) * $cellSize + $frameWidth - 2),
			(int) ($boardY + ($cropHeight - 1) * $cellSize + $frameWidth - 2),
			$boardColor
		);

		// Draw grid lines - thicker at board edges
		$isLeftEdge = ($rotate ? $minY : $minX) === 0;
		$isRightEdge = ($rotate ? $maxY : $maxX) === $boardSize - 1;
		$isTopEdge = ($rotate ? $minX : $minY) === 0;
		$isBottomEdge = ($rotate ? $maxX : $maxY) === $boardSize - 1;

		// Interior grid lines (thin)
		imagesetthickness($img, 1);
		for ($i = 0; $i < $cropWidth; $i++)
		{
			$pos = $boardX + $i * $cellSize;
			imageline($img, (int) $pos, (int) $boardY, (int) $pos, (int) ($boardY + ($cropHeight - 1) * $cellSize), $lineColor);
		}
		for ($i = 0; $i < $cropHeight; $i++)
		{
			$pos = $boardY + $i * $cellSize;
			imageline($img, (int) $boardX, (int) $pos, (int) ($boardX + ($cropWidth - 1) * $cellSize), (int) $pos, $lineColor);
		}

		// Thicker edge lines where crop touches actual board edge
		imagesetthickness($img, 3);
		if ($isLeftEdge)
			imageline($img, (int) $boardX, (int) $boardY, (int) $boardX, (int) ($boardY + ($cropHeight - 1) * $cellSize), $lineColor);
		if ($isRightEdge)
			imageline($img, (int) ($boardX + ($cropWidth - 1) * $cellSize), (int) $boardY, (int) ($boardX + ($cropWidth - 1) * $cellSize), (int) ($boardY + ($cropHeight - 1) * $cellSize), $lineColor);
		if ($isTopEdge)
			imageline($img, (int) $boardX, (int) $boardY, (int) ($boardX + ($cropWidth - 1) * $cellSize), (int) $boardY, $lineColor);
		if ($isBottomEdge)
			imageline($img, (int) $boardX, (int) ($boardY + ($cropHeight - 1) * $cellSize), (int) ($boardX + ($cropWidth - 1) * $cellSize), (int) ($boardY + ($cropHeight - 1) * $cellSize), $lineColor);
		imagesetthickness($img, 1);

		// Draw star points (hoshi) - only if they're in the cropped area
		$starPoints = $this->_getStarPoints($boardSize);
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

		// Draw coordinate labels BEFORE stones (so stones naturally cover labels at edges)
		$labelColor = imagecolorallocate($img, 140, 115, 60); // Warm brown, visible on wood
		$fontPath = dirname(__DIR__, 2) . '/resources/fonts/NimbusSans-Bold.otf';
		$labelFontSize = max(10, $cellSize * 0.45); // ~45% of cell size - large and readable
		$colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];
		$labelGap = $frameWidth + $cellSize * 0.55; // Clear stone overhang at board edges

		// Column letters along bottom
		for ($i = 0; $i < $cropWidth; $i++)
		{
			$col = $rotate ? ($minY + $i) : ($minX + $i);
			if ($col < 0 || $col >= count($colLetters))
				continue;
			$letter = $colLetters[$col];
			$x = $boardX + $i * $cellSize;
			$bbox = imagettfbbox($labelFontSize, 0, $fontPath, $letter);
			$textWidth = $bbox[2] - $bbox[0];
			$textHeight = $bbox[1] - $bbox[7];
			imagettftext($img, $labelFontSize, 0,
				(int) ($x - $textWidth / 2),
				(int) ($boardY + ($cropHeight - 1) * $cellSize + $labelGap + $textHeight),
				$labelColor, $fontPath, $letter);
		}

		// Row numbers along right side (Go convention: row 1 is at bottom of board)
		for ($i = 0; $i < $cropHeight; $i++)
		{
			$row = $rotate ? ($minX + $i) : ($minY + $i);
			$rowNum = $boardSize - $row;
			$label = (string) $rowNum;
			$y = $boardY + $i * $cellSize;
			$bbox = imagettfbbox($labelFontSize, 0, $fontPath, $label);
			$textWidth = $bbox[2] - $bbox[0];
			$textHeight = $bbox[1] - $bbox[7];
			imagettftext($img, $labelFontSize, 0,
				(int) ($boardX + ($cropWidth - 1) * $cellSize + $labelGap),
				(int) ($y + $textHeight / 2),
				$labelColor, $fontPath, $label);
		}

		// Draw stones with 3D gradient effect and shadows
		$stoneRadius = $cellSize * 0.45;
		foreach ($stones as $stone)
		{
			if ($rotate)
			{
				$x = $boardX + ($stone['y'] - $minY) * $cellSize;
				$y = $boardY + ($stone['x'] - $minX) * $cellSize;
			}
			else
			{
				$x = $boardX + ($stone['x'] - $minX) * $cellSize;
				$y = $boardY + ($stone['y'] - $minY) * $cellSize;
			}

			$this->_drawStone($img, (int) $x, (int) $y, $stoneRadius, $stone['color'] === 'B');
		}

		// Convert to PNG and return
		ob_start();
		imagepng($img, null, 6); // Compression level 6 (balance speed/size)
		$imageData = ob_get_clean();
		imagedestroy($img);

		return $imageData;
	}

	/**
	 * Draw a single stone with 3D radial gradient and shadow
	 *
	 * Creates a realistic-looking stone using concentric circles that
	 * interpolate from base color at edges to highlight color at an
	 * offset point, simulating light reflection.
	 *
	 * @param \GdImage $img GD image resource
	 * @param int $cx Center X coordinate
	 * @param int $cy Center Y coordinate
	 * @param float $radius Stone radius in pixels
	 * @param bool $isBlack True for black stone, false for white
	 */
	private function _drawStone(\GdImage $img, int $cx, int $cy, float $radius, bool $isBlack)
	{
		// Shadow (slightly offset, darkened board color)
		$shadowOffset = max(2, (int) ($radius * 0.08));
		$shadowDiameter = (int) ($radius * 2.05);
		$shadowColor = imagecolorallocate($img, 160, 130, 60);
		imagefilledellipse($img, $cx + $shadowOffset, $cy + $shadowOffset, $shadowDiameter, $shadowDiameter, $shadowColor);

		// Gradient parameters
		if ($isBlack)
		{
			$baseR = 10;
			$baseG = 10;
			$baseB = 10;
			$highlightR = 100;
			$highlightG = 100;
			$highlightB = 100;
		}
		else
		{
			$baseR = 195;
			$baseG = 195;
			$baseB = 200;
			$highlightR = 255;
			$highlightG = 255;
			$highlightB = 255;
		}

		// Draw concentric circles from outside to inside
		$steps = max(8, (int) ($radius * 0.6));
		$highlightOffsetX = -$radius * 0.3;
		$highlightOffsetY = -$radius * 0.35;

		for ($i = $steps; $i >= 0; $i--)
		{
			$ratio = ($steps - $i) / $steps; // 0 at edge, 1 at highlight center

			// Position: interpolate from stone center toward highlight
			$x = $cx + $highlightOffsetX * $ratio;
			$y = $cy + $highlightOffsetY * $ratio;

			// Color: interpolate from base to highlight
			$r = (int) ($baseR + ($highlightR - $baseR) * $ratio);
			$g = (int) ($baseG + ($highlightG - $baseG) * $ratio);
			$b = (int) ($baseB + ($highlightB - $baseB) * $ratio);

			$color = imagecolorallocate($img, $r, $g, $b);
			$currentDiameter = (int) ($radius * 2.0 * ($i + 1) / ($steps + 1));
			imagefilledellipse($img, (int) $x, (int) $y, $currentDiameter, $currentDiameter, $color);
		}

		// White stone needs a subtle outline
		if (!$isBlack)
		{
			$outlineColor = imagecolorallocate($img, 100, 100, 100);
			imageellipse($img, $cx, $cy, (int) ($radius * 2), (int) ($radius * 2), $outlineColor);
		}
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
