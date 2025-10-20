

<br><br>
<table class="curl-table">
	<tr class="x">
		<td>id</td>
		<td>user-id</td>
		<td>tsumego-id</td>
		<td>response</td>
		<td>type</td>
		<td>url</td>
		<td>created</td>
	</tr>
    <?php foreach ($curls as $curl): ?>
    <tr>
        <td>
            <?php echo $curl['Curl']['id']; ?>
        </td>
		<td>
            <?php echo $curl['Curl']['user_id']; ?>
        </td>
		<td>
            <?php echo $curl['Curl']['tsumego_id']; ?>
        </td>
		<td>
            <?php echo $curl['Curl']['response']; ?>
        </td>
		<td>
            <?php echo $curl['Curl']['type']; ?>
        </td>
		<td>
            <?php echo $curl['Curl']['url']; ?>
        </td>
		<td>
            <?php echo $curl['Curl']['created']; ?>
        </td>
		
		
    </tr>
    <?php endforeach; ?>
</table>



