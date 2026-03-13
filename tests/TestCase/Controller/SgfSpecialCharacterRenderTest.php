<?php

/**
 * Tests that SGF data with special characters survives the round-trip
 * through json_encode → JavaScript → besogo/database without data loss or JS errors.
 */
class SgfSpecialCharacterRenderTest extends TestCaseWithAuth
{
	/**
	 * SGF with characters that would break raw PHP echo in JS context:
	 * newlines (LF, CRLF), double quotes, script-close tags, Unicode.
	 */
	private static function tortureSgf(): string
	{
		return "(;FF[4]GM[1]SZ[19]C[line1\nline2\r\nCRLF \"quotes\" </script> 日本語]AB[dd]AW[dc];B[cd];W[cc]C[+])";
	}

	/**
	 * Verify that SGF with special chars is correctly parsed by besogo on the play page.
	 * play.ctp embeds SGF via: options.sgf2 = json_encode(...) and new Blob([json_encode(...)]).
	 * Without json_encode, the JS string literal would break and besogo wouldn't initialize.
	 */
	public function testPlayPageParsesSgfWithSpecialChars()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => self::tortureSgf(),
				'set_order' => 1,
				'status' => 'S',
			],
		]);

		$browser->get('/' . $context->setConnections[0]['id']);

		// If json_encode was missing, JS would crash and besogo.editor wouldn't exist.
		// Read the comment from besogo's parsed game tree to verify data survived.
		$comment = $browser->driver->executeScript('return besogo.editor.getRoot().comment;');
		$this->assertStringContainsString('line1', $comment);
		$this->assertStringContainsString('line2', $comment);
		$this->assertStringContainsString('"quotes"', $comment);
		$this->assertStringContainsString('日本語', $comment);
		$this->assertStringContainsString("\n", $comment, 'Newlines should be preserved');
	}

	/**
	 * Verify that SGF versions page (sgfs/view) correctly embeds evil SGF in Blob download.
	 * view.ctp uses: new Blob([json_encode($sgf)]) for download buttons.
	 */
	public function testSgfVersionsPageBlobContainsCorrectSgf()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => self::tortureSgf(),
				'set_order' => 1,
				'status' => 'S',
			],
		]);

		$browser->get('/sgfs/view/' . $context->tsumegos[0]['id']);

		// Override saveAs to capture Blob, click download, read content back
		$blobContent = $browser->driver->executeAsyncScript("
			var callback = arguments[arguments.length - 1];
			var origSaveAs = window.saveAs;
			window.saveAs = function(blob) {
				var reader = new FileReader();
				reader.onload = function() { callback(reader.result); };
				reader.readAsText(blob);
			};
			document.querySelector('[id^=\"dl1-\"]').click();
		");

		$this->assertStringContainsString("\n", $blobContent, 'Newlines preserved in Blob');
		$this->assertStringContainsString('"quotes"', $blobContent, 'Quotes preserved in Blob');
		$this->assertStringContainsString('日本語', $blobContent, 'Unicode preserved in Blob');
	}

	/**
	 * Verify that user uploads page correctly embeds evil SGF in Blob download.
	 * uploads.ctp uses: new Blob([json_encode($sgf)]) for download buttons.
	 */
	public function testUserUploadsPageBlobContainsCorrectSgf()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => self::tortureSgf(),
				'set_order' => 1,
				'status' => 'S',
			],
		]);

		$browser->get('/users/uploads');

		$blobContent = $browser->driver->executeAsyncScript("
			var callback = arguments[arguments.length - 1];
			var origSaveAs = window.saveAs;
			window.saveAs = function(blob) {
				var reader = new FileReader();
				reader.onload = function() { callback(reader.result); };
				reader.readAsText(blob);
			};
			document.querySelector('[id^=\"dl1-\"]').click();
		");

		$this->assertStringContainsString("\n", $blobContent, 'Newlines preserved in Blob');
		$this->assertStringContainsString('"quotes"', $blobContent, 'Quotes preserved in Blob');
		$this->assertStringContainsString('日本語', $blobContent, 'Unicode preserved in Blob');
	}

	/**
	 * Upload SGF with special chars and verify it's preserved exactly in the database.
	 * The upload flow goes through setup_new_sgf.ctp which uses json_encode to embed
	 * the SGF in JavaScript for besogo parsing, then auto-submits to setupNewSgfStep2.
	 */
	public function testUploadPreservesEvilSgfInDatabase()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc]C[+])',
				'set_order' => 1,
				'status' => 'S',
			],
		]);

		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->clickId('show4');

		$browser->driver->executeScript("
			var content = arguments[0];
			var input = document.getElementById('admin-upload-button');
			var file = new File([content], 'test.sgf', {type: 'application/x-sgf'});
			var dt = new DataTransfer();
			dt.items.add(file);
			input.files = dt.files;
			input.dispatchEvent(new Event('change', { bubbles: true }));
		", [self::tortureSgf()]);
		$browser->clickId('admin-upload-submit');

		// Wait for setup_new_sgf.ctp to process and redirect back
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function () use ($browser) {
			return strpos($browser->driver->getCurrentURL(), '/sgf/upload/') === false;
		});

		// Verify the uploaded SGF was saved to the database
		$sgfs = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
			'order' => 'id DESC',
		]);
		$this->assertGreaterThanOrEqual(2, count($sgfs), 'Uploaded SGF should be saved');

		$savedSgf = $sgfs[0]['Sgf']['sgf'];
		$this->assertStringContainsString("\n", $savedSgf, 'Newlines should be preserved in DB');
		$this->assertStringContainsString('"quotes"', $savedSgf, 'Quotes should be preserved in DB');
		$this->assertStringContainsString('日本語', $savedSgf, 'Unicode should be preserved in DB');
	}
}
