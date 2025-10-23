<?php

class PollsController extends AppController {
	public $helpers = ['Html', 'Form'];

	/**
	 * @return void
	 */
	public function index() {
		$this->loadModel('Post');
		$polls = $this->Poll->find('all');
		if (!$polls) {
			$polls = [];
		}
		$posts = [];

		foreach ($polls as $i => $poll) {
			$posts[$i] = $this->Post->findById($poll['Poll']['post_id']);
		}

		$this->set('posts', $posts);
		$this->set('polls', $polls);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null) {
		$poll = $this->Poll->findById($id);
		$polls = $this->Poll->find('all');
		if (!$polls) {
			$polls = [];
		}
		$post = [];
		$samePost = 0;
		foreach ($polls as $pollItem) {
			if ($pollItem['Poll']['post_id'] == $poll['Poll']['post_id']) {
				$post[$samePost] = $pollItem;
				$samePost++;
			}
		}
		$this->set('related', $post);
		$this->set('poll', $poll);
		$this->set('polls', $polls);
	}

	public function add() {
		if ($this->request['data'] != null) {
			$this->Poll->create();
			if ($this->Poll->save($this->request->data)) {
				$this->Flash->success(__('Your puzzle has been saved.'));

				return $this->redirect(['action' => 'index']);
			}

			$this->Flash->error(__('Unable to add your puzzle.'));
		}
	}

}
