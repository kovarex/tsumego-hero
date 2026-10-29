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
 * @var \App\View\AppView $this
 */

$cakeDescription = __d('cake_dev', 'CakePHP: the rapid development php framework');
use App\Utility\CookieFlash;
use App\Utility\ViteManifest;
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>
		<?php echo $cakeDescription ?>:
		<?php echo $this->fetch('title'); ?>
	</title>
	<?php
		echo "<link href=\"/favicon.ico\" type=\"image/x-icon\" rel=\"icon\"/><link href=\"/favicon.ico\" type=\"image/x-icon\" rel=\"shortcut icon\"/>";

		echo ViteManifest::css('app-theme');

echo $this->fetch('css');
echo $this->fetch('script');
?>
</head>
<body>
	<div id="container">
		<div id="header">
			<h1><a href="http://cakephp.org"><?php echo h($cakeDescription); ?></a></h1>
		</div>
		<div id="content">

			<?php echo CookieFlash::render(); ?>

			<?php echo $this->fetch('content'); ?>
		</div>
		<div id="footer">
			<a href="http://www.cakephp.org/" target="_blank"><img src="/img/cake.power.gif" alt="<?php echo h($cakeDescription); ?>" border="0"/></a>
		</div>
	</div>
	<?php echo $this->element('sql_dump'); ?>
</body>
</html>
