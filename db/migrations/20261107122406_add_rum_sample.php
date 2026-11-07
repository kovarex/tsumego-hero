<?php

use Phinx\Migration\AbstractMigration;

//What the players' browsers measured (webroot/js/PlayTiming.js, RumController), for the timings of
//the play page before and after a change: bin/cake rum_report. The daily cron keeps 60 days.
class AddRumSample extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `rum_sample` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `metric` varchar(32) NOT NULL COMMENT 'What was measured, see RumController::METRICS.',
  `milliseconds` int(10) UNSIGNED NOT NULL,
  `server_milliseconds` int(10) UNSIGNED DEFAULT NULL COMMENT 'The part of it the server took, from its Server-Timing header, NULL when unknown.',
  `mode` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'The mode of the play page (Constants::\$LEVEL_MODE and the others), NULL when unknown.',
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `metric_created` (`metric`, `created`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
	}

	public function down(): void
	{
		$this->execute('DROP TABLE `rum_sample`');
	}
}
