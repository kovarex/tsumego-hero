<?php

use App\Utility\BoardTransformation;
use App\Utility\CommentCoordinates;

class CommentCoordinatesTest extends CakeTestCase
{
	public function testLabelsAndPoints()
	{
		$this->assertSame([1, 1], CommentCoordinates::labelToPoint('A19', 19));
		$this->assertSame([9, 19], CommentCoordinates::labelToPoint('J1', 19));
		$this->assertSame([19, 1], CommentCoordinates::labelToPoint('t19', 19));
		$this->assertNull(CommentCoordinates::labelToPoint('I5', 19));
		$this->assertNull(CommentCoordinates::labelToPoint('K5', 9));
		for ($x = 1; $x <= 19; $x++)
			$this->assertSame([$x, 4], CommentCoordinates::labelToPoint(CommentCoordinates::pointToLabel($x, 4, 19), 19));
	}

	public function testLabelsSkipLongerNumbers()
	{
		$this->assertSame(['D4', 'Q16', 'C3'], CommentCoordinates::labels('d4 then Q16, a123 and c3'));
	}

	public function testLabelsSkipWordsWithDigits()
	{
		$this->assertSame(['D4'], CommentCoordinates::labels('d4rkm4tter, R2D2 and mp3 say D4'));
	}

	public function testLabelsSkipLinks()
	{
		$this->assertSame(['Q16', 'D4'], CommentCoordinates::labels('Q16 (https://senseis.xmp.net/?path=%C3%A9/c3 or www.example.com/a1) D4'));
	}

	//Must match app/comments/commentCoordinates.ts, which does the same in the browser.
	public function testTransformTextMirrorsCoordinatesAndSwapsColors()
	{
		$mirror = new BoardTransformation(false, true, false, true);
		$this->assertSame(
			'white Q4 then b-A19, wJ18 and W kills at r3',
			CommentCoordinates::transformTextBy('black D4 then w-T19, bL18 and B kills at c3', $mirror, 19));
		$this->assertSame(
			'Plan b is bad; Black lives. B plays D16',
			CommentCoordinates::transformTextBy('Plan b is bad; White lives. W plays Q16', $mirror, 19));
		$this->assertSame('a123 R2D2 S1 w P2 WHITE', CommentCoordinates::transformTextBy('a123 R2D2 B1 b E2 BLACK', $mirror, 19));
		$this->assertSame('w/o reason, w r1', CommentCoordinates::transformTextBy('w/o reason, b c1', $mirror, 19));
	}

	public function testTransformTextKeepsLinks()
	{
		$this->assertSame(
			'Q4, see https://tsumego.com/files/ul1/c3.sgf?black%C3 at Q4',
			CommentCoordinates::transformTextBy('D4, see https://tsumego.com/files/ul1/c3.sgf?black%C3 at D4', new BoardTransformation(false, true, false, true), 19));
	}

	public function testTransformTextIsUndoneByTheInverse()
	{
		$text = 'B H19,w K18, b L18, after w J19 B kills with H18! Black is dead at a1.';
		foreach (BoardTransformation::all() as $transformation)
			$this->assertSame($text, CommentCoordinates::transformTextBy(
				CommentCoordinates::transformTextBy($text, $transformation, 19), $transformation->inverse(), 19));
	}

	public function testCoordinatesOutsideTheBoardStayAsWritten()
	{
		$this->assertSame('Q16 and J1', CommentCoordinates::transformTextBy('Q16 and A1', new BoardTransformation(false, true), 9));
	}

	public function testTransformPosition()
	{
		$this->assertSame(
			'4/4/3/3/-1/-1/2/0/sgf|3/3+4/4',
			CommentCoordinates::transformPosition('4/16/3/17/-1/-1/2/0/sgf|3/17+4/16', new BoardTransformation(true, true), 19));
		$this->assertNull(CommentCoordinates::transformPosition(null, new BoardTransformation(true), 19));
		$this->assertSame('1/1/-1/-1/-1/-1/1/0/sgf|1/1', CommentCoordinates::transformPosition('1/1/-1/-1/-1/-1/1/0/sgf|1/1', new BoardTransformation(false, false, false, true), 19));
	}
}
