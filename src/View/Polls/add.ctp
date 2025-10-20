<h1>Add Puzzle</h1>
<?php
echo $this->Form->create('Poll');
echo $this->Form->input('img');
echo $this->Form->input('description', array('rows' => '3'));
echo $this->Form->input('answers');
echo $this->Form->input('correct1');
echo $this->Form->input('correct2');
echo $this->Form->input('correct3');
echo $this->Form->input('correct4');
echo $this->Form->input('comment1', array('rows' => '2'));
echo $this->Form->input('comment2', array('rows' => '2'));
echo $this->Form->input('comment3', array('rows' => '2'));
echo $this->Form->input('comment4', array('rows' => '2'));
echo $this->Form->text('post_id');
echo $this->Form->end('Save Poll');
?>