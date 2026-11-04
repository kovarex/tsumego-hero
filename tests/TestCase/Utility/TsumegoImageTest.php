<?php

use App\Utility\Constants;
use App\Utility\TsumegoImage;
use Cake\Http\Exception\InternalErrorException;

class TsumegoImageTest extends AppTestCase
{
	public function testThePictureHasTheOpenGraphSize(): void
	{
		$image = imagecreatefromstring(TsumegoImage::png('(;SZ[19]AB[dd][ed]AW[dc][ec];B[cd])'));
		$this->assertSame(Constants::$OG_IMAGE_WIDTH, imagesx($image));
		$this->assertSame(Constants::$OG_IMAGE_HEIGHT, imagesy($image));
	}

	//A problem taller than wide is turned, so it is drawn as the same problem mirrored along the diagonal
	public function testAPortraitProblemIsDrawnTurned(): void
	{
		$portrait = TsumegoImage::png('(;SZ[19]AB[aa][ab][ac][ad][ae][af]AW[ba][bb][bc][bd][be][bf][bg])');
		$landscape = TsumegoImage::png('(;SZ[19]AB[aa][ba][ca][da][ea][fa]AW[ab][bb][cb][db][eb][fb][gb])');
		$this->assertSame(md5($landscape), md5($portrait));
	}

	//Editors wrap long lists of points, the picture is the same
	public function testWrappedSetupStonesAreDrawn(): void
	{
		$this->assertSame(md5(TsumegoImage::png('(;SZ[19]AB[dd][ed]AW[dc][ec])')), md5(TsumegoImage::png("(;SZ[19]AB[dd]\n[ed]AW[dc] [ec])")));
	}

	public function testAProblemWithoutStonesHasNoPicture(): void
	{
		$this->expectException(InternalErrorException::class);
		TsumegoImage::png('(;SZ[19];B[dd])');
	}
}
