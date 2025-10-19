<?php


class PollsController extends AppController {

    public $helpers = array('Html', 'Form');

    public function index() {
		$this->loadModel('Post');
		$polls = $this->Poll->find('all');
		if (!$polls) {
			$polls = [];
		}
        $posts = [];

		$pollsCount = count($polls);
		for ($i = 0; $i < $pollsCount; $i++) {
			$posts[$i] = $this->Post->findById($polls[$i]['Poll']['post_id']);
		}


		$this->set('posts', $posts);
		$this->set('polls', $polls);
    }

    public function view($id = null) {
        $poll = $this->Poll->findById($id);
		$polls = $this->Poll->find('all');
		if (!$polls) {
			$polls = [];
		}
		$post = [];
		$samePost = 0;
		$pollsCount = count($polls);
		for ($i = 0; $i < $pollsCount; $i++) {
			if($polls[$i]['Poll']['post_id'] == $poll['Poll']['post_id']){
				$post[$samePost] = $polls[$i];
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
                return $this->redirect(array('action' => 'index'));
				 $this->Flash->success(__('Your puzzle has been saved.'));
            }
            $this->Flash->error(__('Unable to add your puzzle.'));
        }
    }



}
