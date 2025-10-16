<?php if(!isset($_SESSION['loggedInUser']['User']['id']) || $_SESSION['loggedInUser']['User']['isAdmin']==0)
		echo '<script type="text/javascript">window.location.href = "/";</script>'; ?>
<?php if(isset($del)) echo '<script type="text/javascript">window.location.href = "/users/adminstats";</script>'; ?>
<div align="center">
	<h1>Delete Tag: <?php echo $tn['TagName']['name']; ?></h1>

  <?php echo $this->Form->create('TagName'); ?> 
  
  <table>
    <tr>
      <td><label for="TagNameName">Type tag id for deletion:</label></td>
      <td><input name="data[TagName][delete]" placeholder="Tag id" maxlength="50" type="text" id="TagNameName"></td>
    </tr>
  </table>
  <br>
  <?php echo $this->Form->end('Delete'); ?>
	<br>
	<a class="new-button-default" href="/tag_names/view/<?php echo $tn['TagName']['id']; ?>">Back</a>

</div>