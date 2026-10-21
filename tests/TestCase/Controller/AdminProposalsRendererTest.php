<?php

/**
 * Tests that admin proposal lists link to the representative (official/public)
 * set, not to a user's private "Favorites" set connection.
 *
 * A tsumego may belong to an official public set and a user's private
 * favorites set. The SGF proposals and tag-connection proposals lists must
 * each pick the official set for the displayed link, and must not duplicate
 * rows.
 */
class AdminProposalsRendererTest extends TestCaseWithAuth
{
	public function testProposalLinksToOfficialSetNotFavorite(): void
	{
		$solvedSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
		$proposalSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]AB[dd];B[aa];W[ab];B[ba]C[+])';

		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				// Official, public, curated set: should be the displayed link.
				'sets' => [
					['name' => 'official', 'public' => 1, 'order' => 1, 'num' => 3],
					// The same user's private favorites set: must NOT be shown.
					['name' => 'favorites', 'public' => 0, 'user_id' => 'self', 'order' => 5, 'num' => 1],
				],
				'sgfs' => [
					['data' => $solvedSgf, 'accepted' => true],
					['data' => $proposalSgf, 'accepted' => false],
				],
			],
		]);
		$this->login('kovarex');

		$this->testAction('/users/adminstats', ['method' => 'get', 'return' => 'view']);

		// The proposal must link to the official set, not the private favorites.
		$officialConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$favoriteConnectionId = $context->tsumegos[0]['set-connections'][1]['id'];

		$this->assertTextContains('SGF Proposals (1)', $this->view);
		$this->assertStringContainsString('href="/' . $officialConnectionId . '"', $this->view);
		// The proposal row must not link to the private favorites set connection.
		$this->assertStringNotContainsString('href="/' . $favoriteConnectionId . '"', $this->view);
	}

	public function testTagConnectionProposalLinksToOfficialSetNotFavorite(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				// Official, public set: should be the displayed link.
				'sets' => [
					['name' => 'official', 'public' => 1, 'order' => 1, 'num' => 3],
					// The same user's private favorites set: must NOT be shown.
					['name' => 'favorites', 'public' => 0, 'user_id' => 'self', 'order' => 5, 'num' => 1],
				],
				// A pending (approved=0) tag connection proposal.
				'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]],
			],
		]);
		$this->login('kovarex');

		$this->testAction('/users/adminstats', ['method' => 'get', 'return' => 'view']);

		$officialConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$favoriteConnectionId = $context->tsumegos[0]['set-connections'][1]['id'];

		$this->assertTextContains('New Tags (1)', $this->view);
		// The tag proposal must link to the official set, not the private favorites.
		$this->assertStringContainsString('href="/' . $officialConnectionId . '"', $this->view);
		$this->assertStringNotContainsString('href="/' . $favoriteConnectionId . '"', $this->view);
	}
}
