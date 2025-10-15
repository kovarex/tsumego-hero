<br><br>
<table>
    <?php foreach ($solves as $solve): ?>
    <tr>
        <td>
            <?php
			echo $this->Html->link(
                    $solve['Solve']['id'],
                    array('action' => 'view', $solve['Solve']['id'])
                );
			?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>



