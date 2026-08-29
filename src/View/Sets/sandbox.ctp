<?php

/**
 * @var View $this
 * @var array $admins
 * @var string $lightDark
 * @var int $overallCounter
 * @var array $setsNew
 */
?>
	<div align="center" class="display1" style="padding-top:10px;">
	<div id="sandbox">
	<h4>Admin Panel</h4>
		<div align="left">
		Collections that you find here are not yet published and need to be checked for improvement.
		While solving them, please look for misplays and bugs.<br><br>
		<?php
		if(Auth::isAdmin())
		{
			?>
			There are 4 tasks for admins: Accept activities, answer comments, modify problems and create new problems.
			Here is a compact guide how to do it (1 page): <a class="historyLink2" href="/files/Admin-Guide.pdf" target="_blank">Admin-Guide.pdf</a>
			<br><br>
			And here is the detailed older version (9 pages): <a class="historyLink2" href="/files/Admin-Guide-Details.pdf" target="_blank">Admin-Guide-Details.pdf</a>
			<br><br>
			<table width="100%">
			<tr>
			<td><a href="/sets/create?sandbox=1">Create Set</a></td>
			</tr>
			</table>

		<?php } ?>
		</div>
	</div>
	<div align="center" class="set-index display1">
	<?php
	for($i = 0; $i < count($setsNew); $i++)
		echo $this->element('set_card', ['set' => $setsNew[$i]]);
?>
	</div>
	<div>
	<?php
echo 'The sandbox contains ' . $overallCounter . ' problems.';
?>
	</div>
	<br><br>
	<div class="accessList">
	Admins:
	<?php
	for($i = 0; $i < count($admins); $i++)
	{
		echo h($admins[$i]);
		if($i < count($admins) - 1) echo ', ';
	}
?>
	<br><br>
	</div>
	</div>



