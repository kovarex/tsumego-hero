<?php

namespace App\Log;

use Cake\Log\Formatter\AbstractFormatter;

//A line of the log files as Cake 2 writes it, "2026-10-29 02:08:26 Warning: the message", so the lines
//of Cake 5 and of Cake 2, which logs its own errors into the same files, read alike.
class LogLineFormatter extends AbstractFormatter
{
	public function format($level, string $message, array $context = []): string
	{
		return date('Y-m-d H:i:s') . ' ' . ucfirst((string) $level) . ': ' . $message;
	}
}
