<?php

/**
 * @var \App\View\AppView $this
 * @var array $users
 */
?>
<div class="roles">
	<h1 class="title4">Admins and owners</h1>
	<p>An admin edits the problems and collections and decides the proposals. An owner can do everything an admin can, and decides who is an admin or an owner. To give someone a role, use the role box on their profile.</p>

	<table class="data-table data-table--compact" id="roles-users">
		<tr><th>User</th><th>Role</th></tr>
		<?php foreach ($users as $user): ?>
			<tr>
				<td><a href="/users/view/<?php echo (int) $user['id']; ?>"><?php echo h($user['display_name']); ?></a></td>
				<td><?php echo $this->element('role_form', ['user' => $user]); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>
