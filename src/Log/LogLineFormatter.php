<?php

namespace App\Log;

use Cake\Log\Formatter\AbstractFormatter;

//A line of the log files, "2026-10-29 02:08:26 Warning: the message", the format the logs have had
//since Cake 2, so the old lines and the new read alike.
class LogLineFormatter extends AbstractFormatter
{
	public function format($level, string $message, array $context = []): string
	{
		return date('Y-m-d H:i:s') . ' ' . ucfirst((string) $level) . ': ' . $message;
	}
}
