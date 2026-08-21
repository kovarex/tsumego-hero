<?php
/**
 * Shared navigation for user profile pages.
 * Usage: $this->element('user_subnav', ['userID' => 123, 'activeTab' => 'profile'])
 *
 * Tabs: profile, solveHistory, contributions, achievements
 *
 * @var View $this
 * @var int $userID
 * @var string $activeTab
 */

$tabs = [
	'profile' => ['url' => '/users/view/' . $userID, 'label' => 'Profile'],
	'solveHistory' => ['url' => '/users/solveHistory/' . $userID, 'label' => 'Solve History'],
	'contributions' => ['url' => '/tags/user/' . $userID, 'label' => 'Contributions'],
	'achievements' => ['url' => '/achievements/user/' . $userID, 'label' => 'Achievements'],
];
?>
<div class="subnav">
<?php foreach ($tabs as $key => $tab): ?>
	<a href="<?php echo $tab['url']; ?>" class="subnav__link<?php echo $key === $activeTab ? ' subnav__link--active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php endforeach; ?>
</div>
