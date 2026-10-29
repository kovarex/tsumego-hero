<?php

/**
 * The form an owner changes the role of a user with (see RolesController::change).
 *
 * Usage: $this->element('role_form', ['user' => $userRow])
 *
 * @var View $this
 * @var array $user Row with the id and user_role_id
 */

use App\Model\Table\UserTable;

$userID = (int) $user['id'];
?>
<form method="post" action="/roles/change/<?php echo $userID; ?>" class="role-form">
	<select name="user_role_id" id="role-select-<?php echo $userID; ?>" aria-label="Role">
		<?php foreach (UserTable::ROLE_NAMES as $roleID => $roleName): ?>
			<option value="<?php echo $roleID; ?>"<?php echo $roleID === (int) $user['user_role_id'] ? ' selected' : ''; ?>><?php echo $roleName; ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" class="btn btn--small" id="role-submit-<?php echo $userID; ?>">Change role</button>
</form>
