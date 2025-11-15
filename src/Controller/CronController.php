<?php

class CronController extends AppController {
	/* Supposed to be ran daily to reset hearts and hero powers */
	public function daily($secret) {
		if ($secret != CRON_SECRET) {
			$this->response->statusCode(403);
			$this->response->body('Wrong cron secret.');
			return $this->response;
		}
		$query = 'UPDATE user SET';
		$query .= ' used_refinement=0';
		$query .= ',used_sprint=0';
		$query .= ',used_rejuvenation=0';
		$query .= ',used_potion=0';
		$query .= ',used_intuition=0';
		$query .= ',used_revelation=0';
		$query .= ',damage=0';
		ClassRegistry::init('User')->query($query);
	}
}
