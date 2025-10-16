<div>
<br><br>
<table>
	<tr>
		<th>
		Date
		</th>
		<th>
		Title
		</th>
		<th>
		Players
		</th>
		<th>
		Server
		</th>
		<th>
		Patterns
		</th>
		<th>
		Puzzles
		</th>
	</tr>
    <?php for ($i = 0; $i < count($posts); $i++) { ?>
    <tr>
        <td>
          <?php
				$createDate = new DateTime($posts[$i]['Post']['created']);
				$strip = $createDate->format('d.m.y');
				echo  $strip;
				/*
                echo $this->Html->link(
                    $strip,
                    array('action' => 'view', $post['Post']['id'])
                );				*/
            ?>
        </td>
        <td>
            <?php
			echo $this->Html->link(
                    $posts[$i]['Post']['title'],
                    array('action' => 'view', $posts[$i]['Post']['id'])
            );
			/*
                echo $this->Html->link(
                    'Edit', array('action' => 'edit', $posts[$i]['Post']['id'])
                );
            ?>
			 <?php
                echo $this->Form->postLink(
                    'Delete',
                    array('action' => 'delete', $posts[$i]['Post']['id']),
                    array('confirm' => 'Are you sure?')
                );            */
			?>
        </td>
		<td>
		<?php
		echo $posts[$i]['Post']['b'].' ('.$posts[$i]['Post']['bRank'].') vs '.$posts[$i]['Post']['w'].' ('.$posts[$i]['Post']['wRank'].')';
		?>
		</td>
		<td>
		<?php
		echo $posts[$i]['Post']['Server'];
		?>
		</td>
		<td>
		<?php
		echo $patternsInPosts[$i];
		?>
		</td>
		<td>
		<?php
		echo $pollsInPosts[$i];
		?>
		</td>
    </tr>
    <?php } ?>
</table>
<br><br>
<p><?php //echo $this->Html->link('Add Post', array('action' => 'add')); ?></p>
</div>


