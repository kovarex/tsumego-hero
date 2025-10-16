<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<?php 
$comment1 = $poll['Poll']['comment1'];
$comment2 = $poll['Poll']['comment2'];
$comment3 = $poll['Poll']['comment3'];
$comment4 = $poll['Poll']['comment4'];
if($poll['Poll']['correct1']!=0){
	$selectACorrect = "Good Move";
	$selectAColor = "green";
}else{
	$selectACorrect = "Bad Move";
	$selectAColor = "red";
}
if($poll['Poll']['correct2']!=0){
	$selectBCorrect = "Good Move";
	$selectBColor = "green";
}else{
	$selectBCorrect = "Bad Move";
	$selectBColor = "red";
}
if($poll['Poll']['correct3']!=0){
	$selectCCorrect = "Good Move";
	$selectCColor = "green";
}else{
	$selectCCorrect = "Bad Move";
	$selectCColor = "red";
}
if($poll['Poll']['correct4']!=0){
	$selectDCorrect = "Good Move";
	$selectDColor = "green";
}else{
	$selectDCorrect = "Bad Move";
	$selectDColor = "red";
}
$string = "
<script>
function selectA() {
    document.getElementById('ans').style.cssText = 'visibility:visible;color:".$selectAColor.";';
	document.getElementById('ans').innerHTML = \"".$selectACorrect."<br>".$comment1."\";
}
function selectB() {
    document.getElementById('ans').style.cssText = 'visibility:visible;color:".$selectBColor.";';
	document.getElementById('ans').innerHTML = \"".$selectBCorrect."<br>".$comment2."\";
}";

if($poll['Poll']['answers'] >= 3){
$string.= "
function selectC() {
    document.getElementById('ans').style.cssText = 'visibility:visible;color:".$selectCColor.";';
	document.getElementById('ans').innerHTML = \"".$selectCCorrect."<br>".$comment3."\";
}
";
}
if($poll['Poll']['answers'] == 4){
$string.= "
function selectD() {
    document.getElementById('ans').style.cssText = 'visibility:visible;color:".$selectDColor.";';
	document.getElementById('ans').innerHTML = \"".$selectDCorrect."<br>".$comment4."\";
}
";
}
$string.= "</script>";
echo $string; 
?>

<div align="center">





<?php 
$length = count($related);
$back = '';
$forward = '';

for ($i = 0; $i < $length; $i++) {
	if($related[$i]['Poll']['id'] == $poll['Poll']['id']){
		$forward = $i;
		if($i == 0){
			$back = $this->Html->link('Back', array('controller' => 'Posts', 'action' => 'view', $poll['Poll']['post_id']));
			if($length>1){
				$forward = $this->Html->link('Forward', array('action' => 'view', $related[$i+1]['Poll']['id']));
			}else{
				$forward = $this->Html->link('Forward', array('controller' => 'Posts', 'action' => 'view', $poll['Poll']['post_id']));
			}
		}else if($i==$length-1){
			$back = $this->Html->link('Back', array('action' => 'view', $related[$i-1]['Poll']['id']));
			$forward = $this->Html->link('Forward', array('controller' => 'Posts', 'action' => 'view', $poll['Poll']['post_id']));
		}else{
			$back = $this->Html->link('Back', array('action' => 'view', $related[$i-1]['Poll']['id']));
			$forward = $this->Html->link('Forward', array('action' => 'view', $related[$i+1]['Poll']['id']));
		}
	}
}
?>

<table>
<tr>
<td> 
<?php echo '&nbsp;&nbsp;&nbsp;'.$back; ?>
</td>
<td>
<?php echo '<img src="/blog/files/images/'.$poll['Poll']['img'].'.png" width="550" height="550">'; ?>
</td>
<td>
<?php echo $forward; ?>
</td>
</tr>
</table>





<br>
<br>
<b><?php 
$des = $poll['Poll']['description'];
if($des==""){
	$des = "Find the best move.";
}
if($des=="b"){
	$des = "Black to play.";
}
if($des=="w"){
	$des = "White to play.";
}
echo $des; 
?></b><br>
<br>
<?php
echo '
	<input type="radio" name="q1" value="a" onclick="selectA()">A
	<input type="radio" name="q1" value="b" onclick="selectB()">B
';
if($poll['Poll']['answers'] >= 3){
	echo '
		<input type="radio" name="q1" value="c" onclick="selectC()">C
	';
}
if($poll['Poll']['answers'] == 4){
	echo '
		<input type="radio" name="q1" value="d" onclick="selectD()">D
	';
}
?>
<br><br>
<div style="visibility:hidden" id="ans">Hidden Answer</div>
<br>
</div>

