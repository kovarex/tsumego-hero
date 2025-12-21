<?php

App::uses('TsumegosController', 'Controller');

/**
 * TsumegoComment Model
 *
 * Represents a comment on a tsumego problem, optionally linked to a TsumegoIssue.
 *
 * Table: tsumego_comment
 * Columns:
 *   - id (INT, PK, AUTO_INCREMENT)
 *   - tsumego_id (INT, FK -> tsumego)
 *   - tsumego_issue_id (INT NULL, FK -> tsumego_issue) - NULL means standalone comment
 *   - message (VARCHAR(2048))
 *   - created (DATETIME)
 *   - user_id (INT, FK -> user)
 *   - position (VARCHAR(300) NULL) - board position for the comment
 *   - deleted (BOOL, default 0)
 */
class TsumegoComment extends AppModel
{
	public $useTable = 'tsumego_comment';
	public $actsAs = ['Containable'];

	public $belongsTo = [
		'Tsumego',
		'TsumegoIssue',
		'User',
	];

	public $validate = [
		'message' => [
			'notBlank' => ['rule' => 'notBlank', 'message' => 'Comment message is required'],
			'maxLength' => ['rule' => ['maxLength', 2048], 'message' => 'Comment is too long (maximum 2048 characters)']]];
}
