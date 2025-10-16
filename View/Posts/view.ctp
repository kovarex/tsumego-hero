
<div align="center">
<b><?php 
$createDate = new DateTime($post['Post']['created']);
echo $createDate->format('d.m.y');;
?></b><br>
<b><?php echo h($post['Post']['title']); ?></b><br><br>
<table>
<tr><td>
<?php echo $post['Post']['b'].' ('.$post['Post']['bRank'].') vs '.$post['Post']['w'].' ('.$post['Post']['wRank'].')'; ?>
</td></tr>
<tr><td>
<?php echo 'Result: '.$post['Post']['Result']; ?>
</td></tr>
<tr><td>
<?php echo 'Server: '.$post['Post']['Server']; ?>
</td></tr>
<tr><td>
Reviewed by: 
<?php if($post['Post']['Reviewed by']=="In-Seong Hwang"){
	echo '<a target="_blank" href="http://www.europeangodatabase.eu/EGD/Player_Card.php?&key=14201286">'.h($post['Post']['Reviewed by']);
}else{
	echo h($post['Post']['Reviewed by']);
}
?>
</td></tr>
</table>

<br>

<div class="eidogo-player-auto" style="width: 600px; height: 600px">
<?php 
$file = 'C:\xampp\htdocs\blog\app\webroot\files\\'.$post['Post']['sgf1'].'.sgf';
echo file_get_contents($file); 
?>
</div>

<?php 
if($post['Post']['sgf2']){
	echo '<b>Pattern Choices</b><br><br>';
	echo '<div class="eidogo-player-auto" style="width: 600px; height: 600px">';
	$file = 'C:\xampp\htdocs\blog\app\webroot\files\\'.$post['Post']['sgf2'].'.sgf';
	echo file_get_contents($file); 
	echo '</div>';
}
?>



<?php  
if(count($polls) > 0){
echo '<b>Puzzles</b><br><br>';
echo $this->Html->link('Solve All', array('controller' => 'Polls', 'action' => 'view', $polls[0]['Poll']['id']));
echo '<br><br>';
}
for ($i = 0; $i < count($polls); $i++) {
	echo '<img src="/blog/files/images/'.$polls[$i]['Poll']['img'].'.png" width="400" height="400">&nbsp;';
}
?>
<br><br>
</div>
<pre>
<?php  
//echo print_r($polls); 
?>
</pre>
