<br><br>
<table>
<tr>
		<th>
		Date
		</th>
		<th>
		Nr.
		</th>
		<th>
		Game
		</th>
		<th>
		Players
		</th>
	</tr>
    <?php 
	$isSame = false;
	$sameCounter = 1;
	$previous = '';
	for ($i = 0; $i < count($polls); $i++) { 
	?>
    <tr>
		<td>
          <?php
				$createDate = new DateTime($polls[$i]['Poll']['created']);
				$strip = $createDate->format('d.m.y');
                echo $strip;
            ?>
        </td>
		<td>
          <?php
				if($previous == $posts[$i]['Post']['title']){
					$sameCounter++;
				}else{
					$sameCounter = 1;
				}
				$previous = $posts[$i]['Post']['title'];
				echo $this->Html->link(
                'Problem '.$sameCounter,
                array('action' => 'view', $polls[$i]['Poll']['id'])
            );
            ?>
        </td>
        <td>
          <?php
				echo $posts[$i]['Post']['title'];
            ?>
        </td>
		<td>
          <?php
				echo $posts[$i]['Post']['b'].' ('.$posts[$i]['Post']['bRank'].') vs '.$posts[$i]['Post']['w'].' ('.$posts[$i]['Post']['wRank'].')';
            ?>
        </td>
    </tr>
    <?php } ?>
</table>
<br><br>
<p><?php //echo $this->Html->link('Add Solve', array('action' => 'add')); 
/*
echo '<br><br><pre>';
print_r($polls);
echo '</pre><br><br><pre>';
print_r($posts);
echo '</pre>';
*/
?></p>




