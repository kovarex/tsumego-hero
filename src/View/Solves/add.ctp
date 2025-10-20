<h1>Add Puzzle</h1>
<?php
echo $this->Form->create('Solve');
echo $this->Form->input('img');
echo $this->Form->input('description');
echo $this->Form->input('correct1');
echo $this->Form->input('correct2');
echo $this->Form->input('correct3');
echo $this->Form->input('answer1');
echo $this->Form->input('answer2');
echo $this->Form->input('answer3');
echo $this->Form->input('answer4');
echo $this->Form->input('comment1');
echo $this->Form->input('comment2');
echo $this->Form->input('comment3');
echo $this->Form->input('comment4');
echo $this->Form->end('Save Solve');
?>