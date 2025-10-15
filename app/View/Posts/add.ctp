<h1>Add Post</h1>
<?php
echo $this->Form->create('Post');
echo $this->Form->input('title');
echo $this->Form->input('b');
echo $this->Form->input('w');
echo $this->Form->input('bRank');
echo $this->Form->input('wRank');
echo $this->Form->input('Server');
echo $this->Form->input('Result');
echo $this->Form->input('Quality');
echo $this->Form->input('Missplays');
echo $this->Form->input('Bad Strategy');
echo $this->Form->input('Reviewed by');
echo $this->Form->input('sgf1');
echo $this->Form->input('sgf2');
echo $this->Form->input('sgf3');
echo $this->Form->input('sgf4');
echo $this->Form->input('sgf5');
echo $this->Form->input('img1');
echo $this->Form->input('img2');
echo $this->Form->input('img3');
echo $this->Form->input('img4');
echo $this->Form->input('img5');
echo $this->Form->input('img6');
echo $this->Form->input('img7');
echo $this->Form->input('img8');
echo $this->Form->input('img9');
echo $this->Form->input('img10');
echo $this->Form->end('Save Post');
?>