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
class AppModel extends Model
{
	/**
	 * Behaviors to attach to all models
	 *
	 * @var array
	 */
	public $actsAs = [
		'EmptyInteger',
	];

	public $recursive = -1;

	/**
	 * Cake wraps every save in its own transaction, which first checks that the row exists and then
	 * updates it. Since MariaDB 11.6.2 (production runs 11.7) a request that changes the same row in
	 * between fails that update with 1020 "Record has changed since last read" (forum#35, forum#102),
	 * although the save only writes the fields it was given and nothing gets lost. So a save is
	 * no transaction of its own, while explicit transactions keep MariaDB's check.
	 */
	public function save($data = null, $validate = true, $fieldList = [])
	{
		$options = is_array($validate) ? $validate : ['validate' => $validate, 'fieldList' => $fieldList];
		return parent::save($data, $options + ['atomic' => false]);
	}

}
