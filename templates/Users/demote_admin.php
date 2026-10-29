<?php
/**
 * @var View $this
 * @var array $admin
 * @var string $status
 * @var bool $redirect
 */
?>
<div align="center">
<p class="title">
	Remove admin status
	<br> 
</p>

<br><br>

<?php
	if($admin['external_id']==null){
		echo 'Confirm demotion by entering your password.<br><br>';
		echo '<form action="/users/demote_admin" id="UserDemoteAdminForm" method="post" accept-charset="utf-8"><div style="display:none;"><input type="hidden" name="_method" value="POST"/></div>';
		echo '<div class="input password"><label for="UserDemote"></label><input name="demote" value="" placeholder="password" type="password" id="UserDemote"/></div>';
		echo '<br>';
	}else{
		echo 'Submit your demotion request.<br><br>';
		echo '<form action="/users/demote_admin" id="UserDemoteAdminForm" method="post" accept-charset="utf-8"><div style="display:none;"><input type="hidden" name="_method" value="POST"/></div>';
		echo '<input type="hidden" name="demote" value="o474w544a4n4q5g4y2" id="UserDemote"/>';
	}
	
	echo '<div class="submit"><input type="submit" value="Submit"/></div></form>';
	echo '<br>';
	echo $status;
?>
</div>
<?php
	if($redirect){
		echo '<script type="text/javascript">window.location.href = "/users/view/'.$admin['id'].'";</script>';
	}
?>