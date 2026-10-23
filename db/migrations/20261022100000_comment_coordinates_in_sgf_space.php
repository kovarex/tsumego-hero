<?php

use App\Utility\LegacyCommentConverter;
use App\Utility\SgfTree;
use Phinx\Migration\AbstractMigration;

/**
 * Comments now keep their coordinates (in the text and in the board position they
 * are anchored to) in the coordinates and colours of the stored SGF, and the board
 * converts them exactly to the orientation each viewer sees.
 *
 * The comments written until now are in the orientation their author happened to
 * see, which was never saved, so the board guessed it every time it showed them.
 * LegacyCommentConverter makes that guess once for each comment against the
 * current SGF of its problem, and the result is stored. The "[current position]"
 * markers are removed on the way, the position column alone marks the anchor.
 *
 * Every changed comment is copied to tsumego_comment_before_sgf_coordinates first,
 * so down() can put the original text and position back.
 */
class CommentCoordinatesInSgfSpace extends AbstractMigration
{
	/**
	 * Comments checked by hand (2026-10-22) that were written with the colours swapped,
	 * which only their words tell ("white can live with Ko" on a "Black to live."
	 * problem), so the converter can't see it. Each is matched by the md5 of its text,
	 * one edited since is left to the converter.
	 */
	private const COLORS_SWAPPED = [
		790 => '1258f359d7ec248857e8b9c95795573f',
		2113 => 'e31d59c8f2513a757c8be72115abf285',
		2249 => '19610b5b7ea77cbc0f1d6c2fa9bc6108',
		2507 => '475db379dd75c691d16182fdf3a8a65b',
		3021 => '4f7da5d36d264705cadc878f118b5588',
		3281 => '569433b730b3573987a875974a788786',
		3381 => 'd3bb2b72eaeb6b79ff06ac9ba1b5ba2f',
		9224 => '7db42b0d35c2d4a3a78041fb411a9177'];

	public function up(): void
	{
		$this->execute("
			CREATE TABLE tsumego_comment_before_sgf_coordinates (
				tsumego_comment_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
				message VARCHAR(2048) NOT NULL,
				position VARCHAR(300) NULL,
				CONSTRAINT tsumego_comment_before_sgf_coordinates_tsumego_comment_id
					FOREIGN KEY (tsumego_comment_id) REFERENCES tsumego_comment (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT='The text and position of comments before LegacyCommentConverter moved them to SGF coordinates'");

		$connection = $this->getAdapter()->getConnection();
		$backup = $connection->prepare('INSERT INTO tsumego_comment_before_sgf_coordinates (tsumego_comment_id, message, position) VALUES (?, ?, ?)');
		$update = $connection->prepare('UPDATE tsumego_comment SET message = ?, position = ? WHERE id = ?');
		$problem = $connection->prepare('
			SELECT t.description, (SELECT s.sgf FROM sgf s WHERE s.tsumego_id = t.id AND s.accepted = 1 ORDER BY s.id DESC LIMIT 1) AS sgf
			FROM tsumego t WHERE t.id = ?');

		$converters = [];
		foreach ($this->fetchAll('SELECT id, tsumego_id, message, position FROM tsumego_comment ORDER BY tsumego_id, id') as $comment)
		{
			$tsumegoID = (int) $comment['tsumego_id'];
			if (!array_key_exists($tsumegoID, $converters))
			{
				$problem->execute([$tsumegoID]);
				$current = $problem->fetch(PDO::FETCH_ASSOC) ?: ['sgf' => null, 'description' => null];
				$tree = $current['sgf'] ? SgfTree::parse($current['sgf']) : null;
				// comments come grouped by problem
				$converters = [$tsumegoID => $tree ? new LegacyCommentConverter($tree, $current['description']) : null];
			}

			$converter = $converters[$tsumegoID];
			if ($converter)
			{
				$conversion = $converter->convert($comment['message'], $comment['position'], $this->colorsSwappedByHand($comment));
				$message = $conversion->message;
				$position = $conversion->position;
			}
			else
			{
				// without an SGF there is nothing to orient against, only the marker goes
				$message = LegacyCommentConverter::withoutMarker($comment['message']);
				$position = $comment['position'];
			}

			// a label can grow by a digit ("A1" to "A19"); a text that would not fit keeps its coordinates
			if (mb_strlen($message) > 2048)
				$message = LegacyCommentConverter::withoutMarker($comment['message']);
			if ($message === $comment['message'] && $position === $comment['position'])
				continue;
			$backup->execute([$comment['id'], $comment['message'], $comment['position']]);
			$update->execute([$message, $position, $comment['id']]);
		}
	}

	/** True for a comment of COLORS_SWAPPED as it was checked, null leaves its colours to the converter. */
	private function colorsSwappedByHand(array $comment): ?bool
	{
		$checked = self::COLORS_SWAPPED[(int) $comment['id']] ?? null;
		if ($checked === null)
			return null;
		if (md5($comment['message']) === $checked)
			return true;
		$this->getOutput()?->writeln("Comment {$comment['id']} was edited since its colours were checked, the converter decides them.");
		return null;
	}

	public function down(): void
	{
		$this->execute('
			UPDATE tsumego_comment c
			JOIN tsumego_comment_before_sgf_coordinates b ON b.tsumego_comment_id = c.id
			SET c.message = b.message, c.position = b.position');
		$this->execute('DROP TABLE tsumego_comment_before_sgf_coordinates');
	}
}
