<script src ="/js/previewBoard.js"></script>
<div class="imp">
<?php if(Auth::isAdmin()){ ?>
	<div class="admin-panel-main-page" style="top:10px;left:540px">
		<ul>
			<li><a class="adminLink2" href="/users/adminstats">Activities</a></li>
		</ul>
	</div>
	<?php } ?>
	<table class="co-table" width="100%">
	<tr>
	<td width="50%">
		<p class="title4">Comments</p>
		<div class="new1" width="100%" style="margin-bottom:15px;">
			<div align="center"><?php $allComments->render(); ?></div>
		</div>
	</td>
	<td width="50%">
		<p class="title4">Your Comments</p>
		<div width="100%"> <?php $yourComments->render(); ?> </div>
	</td>
	</tr>
	</table>
</div>
