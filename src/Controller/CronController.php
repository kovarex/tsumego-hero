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
		$query .= ',readingTrial=30';
		$query .= ',reuse2=0';
		$query .= ',reuse3=0';
		$query .= ',reuse4=0';
		ClassRegistry::init('User')->query($query);
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='V' where status='F'");
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='W' where status='X'");
		$this->response->statusCode(200);
		return $this->response;
	}
}
