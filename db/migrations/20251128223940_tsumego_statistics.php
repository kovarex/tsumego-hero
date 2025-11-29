<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TsumegoStatistics extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `day_record` ADD COLUMN `tsumego_count` INT UNSIGNED NOT NULL");
		$this->execute("ALTER TABLE `day_record` CHANGE `user_id` `user_id` INT UNSIGNED NULL DEFAULT NULL");
		$this->execute("ALTER TABLE `day_record` CHANGE `visitedproblems` `visitedproblems` INT NOT NULL DEFAULT '0'");

		$days = $this->query("SELECT * from day_record ORDER BY date ASC")->fetchAll();
		$dates = [];
		foreach ($days as $day)
		{
			$dates[$day['date']] = [];
			$dates[$day['date']]['added'] = 0;
		}

		$addedAll = $this->query("SELECT
    DATE(tsumego.created) AS day,
    COUNT(DISTINCT tsumego.id) AS tsumego_count
FROM tsumego
JOIN set_connection ON set_connection.tsumego_id = tsumego.id
JOIN `set` ON set_connection.set_id = `set`.id
WHERE tsumego.deleted is null AND `set`.public = 1
GROUP BY DATE(tsumego.created)
ORDER BY day")->fetchAll();

		foreach ($addedAll as $added)
		{
			$dates[$added['day']] = [];
			$dates[$added['day']]['added'] = 0;
			$dates[$added['day']]['added'] += $added['tsumego_count'];
		}

		ksort($dates);

		$sum = 0;

		foreach ($dates as &$date)
		{
			$sum += $date['added'];
			$date['sum'] = $sum;
		}
		unset($date);

		foreach ($dates as $date => $data)
		{
			$existingDayRecord = $this->query("SELECT * from day_record where date = '" . $date . "'")->fetch();
			if ($existingDayRecord)
				$this->execute("UPDATE day_record set tsumego_count = " . $data['sum'] . " WHERE id = " . $existingDayRecord['id']);
			else
				$this->execute("INSERT INTO day_record (date, tsumego_count) VALUES('".$date . "', " . $data['sum'] . ")");
		}
    }
}
