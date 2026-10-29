<?php

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.View.Layouts
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * 
 * @var View $this
 * @var string $message
 * @var string $pageTitle
 * @var bool $pause
 * @var string $url
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $pageTitle; ?></title>

<?php
if (\Cake\Core\Configure::read('debug') == 0):
	echo sprintf('<meta http-equiv="Refresh" content="%s;url=%s" />', $pause, $url);
endif;
?>
<style><!--
BODY { font-family:sans-serif }
P { text-align:center; font-size:1.1em; font-weight:bold }
A { color:#444; text-decoration:none }
A:HOVER { text-decoration: underline; color:#44E }
--></style>
</head>
<body>
<p>
	<a href="<?php echo h($url); ?>"><?php echo h($message); ?></a>
</p>
</body>
</html>
