<div align="center">
<br><br><b>
<?php echo 'Patterns found: '.count($patternsInPosts); ?>
</b><br><br><br>
<?php 

for ($i = 0; $i < count($patternsInPosts); $i++) {
echo '<div class="eidogo-player-auto" style="width: 600px; height: 600px">';
$file = 'C:\xampp\htdocs\blog\app\webroot\files\\'.$patternsInPosts[$i].'.sgf';
echo file_get_contents($file); 
echo '</div>';

}
?>

<br><br>

</div>
