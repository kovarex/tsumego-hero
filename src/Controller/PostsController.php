<?php

class PostsController extends AppController {

	public $helpers = ['Html', 'Form'];

	/**
	 * @return void
	 */
	public function index() {
		$this->loadModel('Poll');
		$posts = $this->Post->find('all', ['order' => ['Post.created DESC']]);
		if (!$posts) {
			$posts = [];
		}
		$pollsInPosts = [];
		$patternsInPosts = [];

		$postsCount = count($posts);
		for ($i = 0; $i < $postsCount; $i++) {
			$polls = $this->Poll->find('all', ['conditions' => ['Poll.post_id' => $posts[$i]['Post']['id']]]);
			if (!$polls) {
				$polls = [];
			}
			$numPolls = count($polls);
			$pollsInPosts[$i] = $numPolls;
			$countPatterns = 0;
			if ($posts[$i]['Post']['sgf2'] != null) { $countPatterns++;
			}
			if ($posts[$i]['Post']['sgf3'] != null) { $countPatterns++;
			}
			if ($posts[$i]['Post']['sgf4'] != null) { $countPatterns++;
			}
			if ($posts[$i]['Post']['sgf5'] != null) { $countPatterns++;
			}
			$patternsInPosts[$i] = $countPatterns;
		}

		$this->set('pollsInPosts', $pollsInPosts);
		$this->set('patternsInPosts', $patternsInPosts);
		$this->set('posts', $posts);
	}

	/**
	 * @return void
	 */
	public function view($id = null) {
		$post = $this->Post->findById($id);
		$this->set('post', $post);

		$this->loadModel('Poll');
		$polls = $this->Poll->find('all', ['conditions' => ['Poll.post_id' => $id]]);
		if (!$polls) {
			$polls = [];
		}
		$this->set('polls', $polls);
	}

	public function add() {
		if ($this->request->is('post')) {
			$this->Post->create();
			if ($this->Post->save($this->request->data)) {
				$this->Flash->success(__('Your post has been saved.'));

				return $this->redirect(['action' => 'index']);
			}
			$this->Flash->error(__('Unable to add your post.'));
		}
	}

	public function edit($id = null) {
		if (!$id) {
			throw new NotFoundException(__('Invalid post'));
		}

		$post = $this->Post->findById($id);
		if (!$post) {
			throw new NotFoundException(__('Invalid post'));
		}

		if ($this->request->is(['post', 'put'])) {
			$this->Post->id = $id;
			if ($this->Post->save($this->request->data)) {
				$this->Flash->success(__('Your post has been updated.'));

				return $this->redirect(['action' => 'index']);
			}
			$this->Flash->error(__('Unable to update your post.'));
		}

		if (!$this->request->data) {
			$this->request->data = $post;
		}
	}

	public function delete($id) {
		if ($this->request->is('get')) {
			throw new MethodNotAllowedException();
		}

		if ($this->Post->delete($id)) {
			$this->Flash->success(
				__('The post with id: %s has been deleted.', h($id)),
			);
		} else {
			$this->Flash->error(
				__('The post with id: %s could not be deleted.', h($id)),
			);
		}

		return $this->redirect(['action' => 'index']);
	}

	/**
	 * @return void
	 */
	public function patterns() {
		$posts = $this->Post->find('all', ['order' => ['Post.created DESC']]);
		if (!$posts) {
			$posts = [];
		}
		$patternsInPosts = [];
		foreach ($posts as $post) {
			if ($post['Post']['sgf2'] != null) { $patternsInPosts[] = $post['Post']['sgf2'];
			}
			if ($post['Post']['sgf3'] != null) { $patternsInPosts[] = $post['Post']['sgf3'];
			}
			if ($post['Post']['sgf4'] != null) { $patternsInPosts[] = $post['Post']['sgf4'];
			}
			if ($post['Post']['sgf5'] != null) { $patternsInPosts[] = $post['Post']['sgf5'];
			}
		}
		$this->set('patternsInPosts', $patternsInPosts);
	}

}
