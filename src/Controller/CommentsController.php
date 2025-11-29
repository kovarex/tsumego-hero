<?php

App::uses('SgfParser', 'Utility');

class CommentsController extends AppController
{
	/**
	 * @return void
	 */
	public function index()
	{
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Set');
		$this->loadModel('User');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->Session->write('title', 'Tsumego Hero - Discuss');
		$this->Session->write('page', 'discuss');
		$c = [];
		$setsWithPremium = [];
		$index = 0;
		$counter = 0;
		$reverseOrder = false;
		$lastEntry = true;
		$firstPage = false;
		$paramcommentid = 0;
		$paramdirection = 0;
		$paramindex = 0;
		$paramyourcommentid = 0;
		$paramyourdirection = 0;
		$paramyourindex = 0;
		$unresolvedSet = 'true';
		if (!isset($this->params['url']['unresolved']))
		{
			$unresolved = 'false';
			$unresolvedSet = 'false';
		}
		else
			$unresolved = $this->params['url']['unresolved'];
		if (!isset($this->params['url']['filter']))
			$filter1 = 'true';
		else
			$filter1 = $this->params['url']['filter'];

		$hasPremium = Auth::hasPremium();
		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]);
		if (!$swp)
			$swp = [];
		foreach ($swp as $item)
			$setsWithPremium[] = $item['Set']['id'];

		if ($filter1 == 'true')
		{
			$userTsumegos = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'OR' => [
						['status' => 'S'],
						['status' => 'C'],
						['status' => 'W'],
					],
				],
			]);
			if (!$userTsumegos)
				$userTsumegos = [];
		}
		else
		{
			$userTsumegos = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
				],
			]);
			if (!$userTsumegos)
				$userTsumegos = [];
		}

		$keyList = [];
		$keyListStatus = [];
		$userTsumegosCount = count($userTsumegos);
		for ($i = 0; $i < $userTsumegosCount; $i++)
		{
			$keyList[$i] = $userTsumegos[$i]['TsumegoStatus']['tsumego_id'];
			$keyListStatus[$i] = $userTsumegos[$i]['TsumegoStatus']['status'];
		}

		$comments = [];
		if (!isset($this->params['url']['comment-id']))
		{
			$comments = ClassRegistry::init('TsumegoComment')->find('all', [
				'limit' => 500,
				'order' => 'created DESC',
				'conditions' => ['deleted' => false]]) ?: [];
			$firstPage = true;
		}
		else
		{
			$paramcommentid = $this->params['url']['comment-id'];
			$paramdirection = $this->params['url']['direction'];
			$paramindex = $this->params['url']['index'];
			$comments = ClassRegistry::init('TsumegoComment')->find('all', [
				'limit' => 500,
				'order' => 'created ' . ($this->params['url']['direction'] == 'next' ? 'DESC' : 'ASC'),
				'conditions' => [
					'id <' => $this->params['url']['comment-id'],
					'deleted' => false]]) ?: [];
			if ($this->params['url']['direction'] == 'prev')
				$reverseOrder = true;
			$index = $this->params['url']['index'];
		}
		if ($filter1 == 'true')
		{
			$commentsBuffer = [];
			$commentsCount = count($comments);
			for ($i = 0; $i < $commentsCount; $i++)
			{
				$t = $this->Tsumego->findById($comments[$i]['TsumegoComment']['tsumego_id']);
				$premiumLock = false;
				if (!$hasPremium)
					if (in_array($t['Tsumego']['set_id'], $setsWithPremium))
						$premiumLock = true;
				if ($t != null && !$premiumLock)
					if (in_array($t['Tsumego']['id'], $keyList))
						if ($counter < 11)
						{
							if (!in_array($t['Tsumego']['id'], $keyList))
								$solved = 0;
							elseif ($keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'S'
									|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'C'
									|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'W'
							)
								$solved = 1;
							else
								$solved = 0;
							$u = $this->User->findById($comments[$i]['TsumegoComment']['user_id']);
							if ($comments[$i]['TsumegoComment']['set_id'] == null)
								$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
							else
								$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $comments[$i]['TsumegoComment']['set_id']]]);

							if ($scT && isset($scT['SetConnection']['set_id']))
							{
								$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
								$s = $this->Set->findById($t['Tsumego']['set_id']);
								if ($s && isset($s['Set']['title']))
								{
									$counter++;
									$comments[$i]['TsumegoComment']['counter'] = $counter + $index;
									$comments[$i]['TsumegoComment']['user_name'] = $this->checkPicture($u['User']);
									$comments[$i]['TsumegoComment']['set'] = $s['Set']['title'];
									$comments[$i]['TsumegoComment']['set2'] = $s['Set']['title2'];
									$comments[$i]['TsumegoComment']['num'] = $scT['SetConnection']['num'];

									if (!in_array($t['Tsumego']['id'], $keyList))
										$comments[$i]['TsumegoComment']['user_tsumego'] = 'N';
									else
										$comments[$i]['TsumegoComment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
									$comments[$i]['TsumegoComment']['solved'] = $solved;
									if ($comments[$i]['TsumegoComment']['admin_id'] != null)
									{
										$au = $this->User->findById($comments[$i]['TsumegoComment']['admin_id']);
										if ($au && isset($au['User']['name']))
										{
											if ($au['User']['name'] == 'Morty')
												$au['User']['name'] = 'Admin';
											$comments[$i]['TsumegoComment']['admin_name'] = $au['User']['name'];
										}
									}

									$date = new DateTime($comments[$i]['TsumegoComment']['created']);
									$month = date('F', strtotime($comments[$i]['TsumegoComment']['created']));
									$tday = $date->format('d. ');
									$tyear = $date->format('Y');
									$tClock = $date->format('H:i');
									if ($tday[0] == 0)
										$tday = substr($tday, -3);
									$comments[$i]['TsumegoComment']['created'] = $tday . $month . ' ' . $tyear . '<br>' . $tClock;
									array_push($c, $comments[$i]);
								}
							}
						}
			}
		}
		else
		{
			$commentsBuffer = [];
			$commentsCount = count($comments);
			for ($i = 0; $i < $commentsCount; $i++)
				if ($counter < 11)
				{
					$t = $this->Tsumego->findById($comments[$i]['TsumegoComment']['tsumego_id']);
					$premiumLock = false;
					if (!$hasPremium)
						if (in_array($t['Tsumego']['set_id'], $setsWithPremium))
							$premiumLock = true;
					if (!$premiumLock)
					{
						if (!in_array($t['Tsumego']['id'], $keyList))
							$solved = 0;
						elseif ($keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'S'
							|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'C'
							|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'W')
								$solved = 1;
						else
							$solved = 0;
						$u = $this->User->findById($comments[$i]['TsumegoComment']['user_id']);
						if ($comments[$i]['TsumegoComment']['set_id'] == null)
							$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
						else
							$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $comments[$i]['TsumegoComment']['set_id']]]);

						if ($scT && isset($scT['SetConnection']['set_id']))
						{
							$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
							$s = $this->Set->findById($t['Tsumego']['set_id']);
							if ($s && isset($s['Set']['title']))
							{
								$counter++;
								$comments[$i]['TsumegoComment']['counter'] = $counter + $index;
								$comments[$i]['TsumegoComment']['user_name'] = $this->checkPicture($u['User']);
								$comments[$i]['TsumegoComment']['set'] = $s['Set']['title'];
								$comments[$i]['TsumegoComment']['set2'] = $s['Set']['title2'];
								$comments[$i]['TsumegoComment']['num'] = $scT['SetConnection']['num'];
								if (!in_array($t['Tsumego']['id'], $keyList))
									$comments[$i]['TsumegoComment']['user_tsumego'] = 'N';
								else
									$comments[$i]['TsumegoComment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
								$comments[$i]['TsumegoComment']['solved'] = $solved;
								if ($comments[$i]['TsumegoComment']['admin_id'] != null)
								{
									$au = $this->User->findById($comments[$i]['TsumegoComment']['admin_id']);
									if ($au && isset($au['User']['name']))
									{
										if ($au['User']['name'] == 'Morty')
											$au['User']['name'] = 'Admin';
										$comments[$i]['TsumegoComment']['admin_name'] = $au['User']['name'];
									}
								}
								$date = new DateTime($comments[$i]['TsumegoComment']['created']);
								$month = date('F', strtotime($comments[$i]['TsumegoComment']['created']));
								$tday = $date->format('d. ');
								$tyear = $date->format('Y');
								$tClock = $date->format('H:i');
								if ($tday[0] == 0)
									$tday = substr($tday, -3);
								$comments[$i]['TsumegoComment']['created'] = $tday . $month . ' ' . $tyear . '<br>' . $tClock;
								array_push($c, $comments[$i]);
							}
						}
					}
				}
		}

		if ($reverseOrder)
		{
			$c = array_reverse($c);
			$counter = 0;
			$cCount = count($c);
			for ($i = 0; $i < $cCount; $i++)
			{
				$counter++;
				$c[$i]['TsumegoComment']['counter'] = $counter + $index;
			}
		}
		///////////////////////////////////
		$yourc = [];
		$yourindex = 0;
		$yourcounter = 0;
		$yourreverseOrder = false;
		$yourlastEntry = true;
		$yourfirstPage = false;
		$yourComments = [];
		if (!isset($this->params['url']['your-comment-id']))
		{
			$yourComments = ClassRegistry::init('TsumegoComment')->find('all', [
				'limit' => 500,
				'order' => 'created DESC',
				'conditions' => ['user_id' => Auth::getUserID(), 'deleted' => false]]) ?: [];
			$yourfirstPage = true;
		}
		else
		{
			$paramyourcommentid = $this->params['url']['your-comment-id'];
			$paramyourdirection = $this->params['url']['your-direction'];
			$paramyourindex = $this->params['url']['your-index'];
			if ($this->params['url']['your-direction'] == 'next')
				$yourComments = ClassRegistry::init('TsumegoComment')->find('all', [
					'limit' => 500,
					'order' => 'created DESC',
					'conditions' => [
						'Comment.id <' => $this->params['url']['your-comment-id'],
						'user_id' => Auth::getUserID()]]) ?: [];
			elseif (($this->params['url']['your-direction'] == 'prev'))
			{
				$yourComments = $this->Comment->find('all', [
					'limit' => 500,
					'order' => 'created ASC',
					'conditions' => [
						'Comment.id >' => $this->params['url']['your-comment-id'],
						'user_id' => Auth::getUserID()]]) ?: [];
				$yourreverseOrder = true;
			}
			$yourindex = $this->params['url']['your-index'];
		}

		$yourCommentsCount = count($yourComments);
		for ($i = 0; $i < $yourCommentsCount; $i++)
		{
			if ($yourcounter < 11)
			{
				$t = $this->Tsumego->findById($yourComments[$i]['TsumegoComment']['tsumego_id']);
				if (!isset($t['Tsumego']['id']))
					$t['Tsumego']['id'] = 0;
				if (in_array($t['Tsumego']['id'], $keyList))
				{
					$u = $this->User->findById($yourComments[$i]['TsumegoComment']['user_id']);
					if ($yourComments[$i]['TsumegoComment']['set_id'] == null)
						$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
					else
						$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $yourComments[$i]['TsumegoComment']['set_id']]]);

					if ($scT && isset($scT['SetConnection']['set_id']))
					{
						$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
						$s = $this->Set->findById($t['Tsumego']['set_id']);
						if ($s && isset($s['Set']['title']))
						{
							$yourcounter++;
							$yourComments[$i]['TsumegoComment']['counter'] = $yourcounter + $yourindex;
							$yourComments[$i]['TsumegoComment']['user_name'] = $this->checkPicture($u['User']);
							$yourComments[$i]['TsumegoComment']['set'] = $s['Set']['title'];
							$yourComments[$i]['TsumegoComment']['set2'] = $s['Set']['title2'];
							$yourComments[$i]['TsumegoComment']['num'] = $scT['SetConnection']['num'];
							$yourComments[$i]['TsumegoComment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
							if ($yourComments[$i]['TsumegoComment']['admin_id'] != null)
							{
								$au = $this->User->findById($yourComments[$i]['TsumegoComment']['admin_id']);
								if ($au && isset($au['User']['name']))
								{
									if ($au['User']['name'] == 'Morty')
										$au['User']['name'] = 'Admin';
									$yourComments[$i]['TsumegoComment']['admin_name'] = $au['User']['name'];
								}
							}
							$date = new DateTime($yourComments[$i]['TsumegoComment']['created']);
							$month = date('F', strtotime($yourComments[$i]['TsumegoComment']['created']));
							$tday = $date->format('d. ');
							$tyear = $date->format('Y');
							$tClock = $date->format('H:i');
							if ($tday[0] == 0)
								$tday = substr($tday, -3);
							$yourComments[$i]['TsumegoComment']['created'] = $tday . $month . ' ' . $tyear . '<br>' . $tClock;

							array_push($yourc, $yourComments[$i]);
						}
					}
				}
			}
			if (strpos($yourComments[$i]['TsumegoComment']['message'], '<a href="/files/ul1/') === false)
				$yourComments[$i]['TsumegoComment']['message'] = htmlspecialchars($yourComments[$i]['TsumegoComment']['message']);
		}
		if ($yourreverseOrder)
		{
			$yourc = array_reverse($yourc);
			$yourcounter = 0;
			$yourcCount = count($yourc);
			for ($i = 0; $i < $yourcCount; $i++)
			{
				$yourcounter++;
				$yourc[$i]['TsumegoComment']['counter'] = $yourcounter + $yourindex;
			}
		}

		$yourComments2 = [];
		$yourCommentsCount2 = count($yourComments);
		for ($i = 0; $i < $yourCommentsCount2; $i++)
			if ($counter < 10)
			{
				$u = $this->User->findById($yourComments[$i]['TsumegoComment']['user_id']);
				$t = $this->Tsumego->findById($yourComments[$i]['TsumegoComment']['tsumego_id']);
				if ($yourComments[$i]['TsumegoComment']['set_id'] == null)
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
				else
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $yourComments[$i]['TsumegoComment']['set_id']]]);

				if ($scT && isset($scT['SetConnection']['set_id']))
				{
					$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$s = $this->Set->findById($t['Tsumego']['set_id']);
					if ($s && isset($s['Set']['title']))
					{
						$counter++;
						$yourComments[$i]['TsumegoComment']['counter'] = $counter;
						$yourComments[$i]['TsumegoComment']['user_name'] = $this->checkPicture($u['User']);
						$yourComments[$i]['TsumegoComment']['set'] = $s['Set']['title'];
						$yourComments[$i]['TsumegoComment']['set2'] = $s['Set']['title2'];
						$yourComments[$i]['TsumegoComment']['num'] = $scT['SetConnection']['num'];
						array_push($yourComments2, $yourComments[$i]);
					}
				}
			}

		if (isset($this->params['url']['filter']))
			$this->set('filter1', $filter1);
		if ($filter1 == 'falsex')
			$this->set('filter1', 'false');
		if ($unresolvedSet == 'true')
		{
			$this->set('unresolved', $unresolved);
			$this->set('comments3', count(ClassRegistry::init('TsumegoComment')->find('all', ['order' => 'created DESC', 'conditions' => ['deleted' => false]]) ?: []));
		}

		$currentPositionPlaceholder = '<img src="/img/positionIcon1.png" class="positionIcon1" style="cursor:context-menu;">';

		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		$tooltipSgfs2 = [];
		$tooltipInfo2 = [];
		$tooltipBoardSize2 = [];
		$cCount2 = count($c);
		for ($i = 0; $i < $cCount2; $i++)
		{
			if (strpos($c[$i]['TsumegoComment']['message'], '<a href="/files/ul1/') === false)
				$c[$i]['TsumegoComment']['message'] = htmlspecialchars($c[$i]['TsumegoComment']['message']);
			$c[$i]['TsumegoComment']['message'] = str_replace('[current position]', $currentPositionPlaceholder, $c[$i]['TsumegoComment']['message']);

			$tBuffer = $this->Tsumego->findById($c[$i]['TsumegoComment']['tsumego_id']);
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'id DESC', 'conditions' => ['tsumego_id' => $tBuffer['Tsumego']['id']]]);
			if (count($tts) > 0)
			{
				$tResult = SgfParser::process($tts[0]['Sgf']['sgf']);
				array_push($tooltipSgfs, $tResult->board);
				array_push($tooltipInfo, $tResult->info);
				array_push($tooltipBoardSize, $tResult->size);
			}
		}
		$yourcCount2 = count($yourc);
		for ($i = 0; $i < $yourcCount2; $i++)
		{
			$yourc[$i]['TsumegoComment']['message'] = str_replace('[current position]', $currentPositionPlaceholder, $yourc[$i]['TsumegoComment']['message']);

			$tBuffer = $this->Tsumego->findById($yourc[$i]['TsumegoComment']['tsumego_id']);
			$tts2 = $this->Sgf->find('all', ['limit' => 1, 'order' => 'id DESC', 'conditions' => ['tsumego_id' => $tBuffer['Tsumego']['id']]]);
			if (count($tts2) > 0)
			{
				$tResult2 = SgfParser::process($tts2[0]['Sgf']['sgf']);
				$tooltipSgfs2[$i] = $tResult2->board;
				$tooltipInfo2[$i] = $tResult2->info;
				array_push($tooltipBoardSize2, $tResult2->size);
			}
		}
		$admins = $this->User->find('all', ['conditions' => ['isAdmin' => 1]]);

		if (Auth::isAdmin())
		{
			$uc = ClassRegistry::init('TsumegoComment')->find('all', ['conditions' => ['user_id' => 0, 'tsumego_id' => 0]]) ?: [];
			foreach ($uc as $item)
				$this->Comment->delete($item['TsumegoComment']['id']);
		}

		$this->set('admins', $admins);
		$this->set('paramindex', $paramindex);
		$this->set('paramdirection', $paramdirection);
		$this->set('paramcommentid', $paramcommentid);
		$this->set('paramyourindex', $paramyourindex);
		$this->set('paramyourdirection', $paramyourdirection);
		$this->set('paramyourcommentid', $paramyourcommentid);
		$this->set('index', $index);
		$this->set('yourindex', $yourindex);
		$this->set('comments', $c);
		$this->set('yourComments', $yourc);
		$this->set('userTsumegos', $keyList);
		$this->set('firstPage', $firstPage);
		$this->set('yourfirstPage', $yourfirstPage);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipSgfs2', $tooltipSgfs2);
		$this->set('tooltipInfo2', $tooltipInfo2);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('tooltipBoardSize2', $tooltipBoardSize2);
	}
}
