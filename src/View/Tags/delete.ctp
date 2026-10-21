<?php

/**
 * @var View $this
 * @var array $tn
 */

?>
<?php if(isset($del)) echo '<script type="text/javascript">window.location.href = "/users/adminstats";</script>'; ?>
<div align="center">
	<h1>Delete Tag: <?php echo h($tn['Tag']['name']); ?></h1>

  <?php echo $this->Form->create('Tag'); ?>

  <div class="stack">
    <div class="form-field">
      <label class="form-field__label" for="TagNameName">Type tag id for deletion:</label>
      <input class="form-field__control" name="data[TagName][delete]" placeholder="Tag id" maxlength="50" type="text" id="TagNameName">
    </div>
  </div>
  <br>
  <?php echo $this->Form->end('Delete'); ?>
	<br>
	<a class="btn btn--neutral" href="/tags/view/<?php echo $tn['Tag']['id']; ?>">Back</a>

</div>
