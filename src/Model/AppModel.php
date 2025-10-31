<?php

/**
 * Application model for CakePHP.
 *
 * This file is application-wide model file. You can put all
 * application-wide model-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @package app.Model
 * @since CakePHP(tm) v 0.2.9
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */

/**
 * Application model for Cake.
 *
 * Add your application-wide methods in the class below, your models
 * will inherit them.
 */
class AppModel extends Model {
	/**
	 * Behaviors to attach to all models
	 *
	 * @var array
	 */
	public $actsAs = [
		'EmptyInteger',
	];

	public function __construct($id = false,$table = null,$ds = null)
	{
		parent::__construct($id,$table,$ds);
		$this->initialize();
	}

	/**
	 * @param string $associated
	 * @param array $options
	 * @return void
	 */
	public function belongsToMany($associated, array $options = []) {
		$this->_setAssoc('hasAndBelongsToMany', $associated, $options);
	}

	/**
	 * @param string $associated
	 * @param array $options
	 * @return void
	 */
	public function belongsTo($associated, array $options = []) {
		$this->_setAssoc('belongsTo', $associated, $options);
	}

	/**
	 * @param string $associated
	 * @param array $options
	 * @return void
	 */
	public function hasOne($associated, array $options = []) {
		$this->_setAssoc('hasOne', $associated, $options);
	}

	/**
	 * @param string $associated
	 * @param array $options
	 * @return void
	 */
	public function hasMany($associated, array $options = []) {
		$this->_setAssoc('hasMany', $associated, $options);
	}

	public $recursive = -1;

}
