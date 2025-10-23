<?php

class SolvesController extends AppController {

	public $helpers = ['Html', 'Form'];

	/**
	 * @return void
	 */
	public function index() {
		$this->set('solves', $this->Solve->find('all'));
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null) {
		if (!$id) {
			throw new NotFoundException(__('Invalid puzzle'));
		}
		$solve = $this->Solve->findById($id);
		if (!$solve) {
			throw new NotFoundException(__('Invalid puzzle'));
		}
		$this->set('solve', $solve);
	}

	public function add() {
		if ($this->request['data'] != null) {
			$this->Solve->create();
			if ($this->Solve->save($this->request->data)) {
				$this->Flash->success(__('Your puzzle has been saved.'));

				return $this->redirect(['action' => 'index']);
			}

			$this->Flash->error(__('Unable to add your puzzle.'));
		} else {
			$this->Flash->error(__('This is no puzzle.'));
		}
	}

}
