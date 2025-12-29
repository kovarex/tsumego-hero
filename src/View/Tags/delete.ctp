<?php if(!Auth::isLoggedIn() || !Auth::isAdmin())
		echo '<script type="text/javascript">window.location.href = "/";</script>'; ?>
<?php if(isset($del)) echo '<script type="text/javascript">window.location.href = "/users/adminstats";</script>'; ?>
<div align="center">
	<h1>Delete Tag: <?php echo $tn['Tag']['name']; ?></h1>

  <?php echo $this->Form->create('Tag'); ?>

  <table>
    <tr>
      <td><label for="TagNameName">Type tag id for deletion:</label></td>
      <td><input name="data[TagName][delete]" placeholder="Tag id" maxlength="50" type="text" id="TagNameName"></td>
    </tr>
  </table>
  <br>
  <?php echo $this->Form->end('Delete'); ?>
	<br>
	<a class="new-button-default" href="/tag_names/view/<?php echo $tn['Tag']['id']; ?>">Back</a>

</div>
