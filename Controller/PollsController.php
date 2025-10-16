<?php


class PollsController extends AppController {
	
    public $helpers = array('Html', 'Form');

    public function index() {
		$this->loadModel('Post');
		$polls = $this->Poll->find('all');
        $posts;
		
		
		
		for ($i = 0; $i < count($polls); $i++) {
			$posts[$i] = $this->Post->findById($polls[$i]['Poll']['post_id']);
		}


		$this->set('posts', $posts);
		$this->set('polls', $polls);		
    }

    public function view($id = null) {
        $poll = $this->Poll->findById($id);
		$polls = $this->Poll->find('all');
		$post;
		$samePost = 0;
		for ($i = 0; $i < count($polls); $i++) {
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


?>