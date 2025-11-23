<?php
namespace tsumego\sso\auth\provider;

use phpbb\auth\provider\provider_interface;
use phpbb\request\request_interface;
use phpbb\db\driver\driver_interface;
use phpbb\user;

class mycustomauth implements provider_interface
{
	protected $request;
	protected $db;
	protected $config;
	protected $user;

	/** phpBB injects required services */
	public function __construct(
		request_interface $request,
		driver_interface $db,
		\phpbb\config\config $config,
		user $user
	)
	{
		$this->request = $request;
		$this->db      = $db;
		$this->config  = $config;
		$this->user    = $user;
	}

	public function init()
	{
		return false;
	}

	/** phpBB will call this automatically on every page load */
	public function autologin()
	{
		if (!isset($_SESSION['loggedInUserID'])) {
			return false; // not logged into site
		}

		$external_id = (int) $_SESSION['loggedInUserID'];

		// Fetch from your custom table "user"
		$sql = 'SELECT id, name, email
                FROM user
                WHERE id = ' . $external_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
			return false; // external user not found

		return $this->get_or_create_bb_user($row);
	}

	public function login($username, $password)
	{
		// You can ignore phpBB’s login box — force login through your website
		return array(
			'status'    => LOGIN_ERROR,
			'error_msg' => 'LOGIN_ERROR_EXTERNAL_AUTH',
			'user_row'  => array(),
		);
	}

	public function validate_session($user)
	{
		return isset($_SESSION['loggedInUserID']);
	}

	/** Creates or loads the phpBB user */
	protected function get_or_create_bb_user($ext)
	{
		// Try find matching phpBB user by email
		$sql = 'SELECT * FROM ' . USERS_TABLE . '
        WHERE external_id = ' . (int) $ext['id'];
		$result = $this->db->sql_query($sql);
		$user_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($user_row)
			return $user_row; // existing phpBB account

		// Otherwise create a new phpBB user
		include_once(__DIR__ . '/../../../../includes/functions_user.php');

		$user_row = array(
			'username'         => $ext['name'],
			'username_clean'   => strtolower($ext['name']),
			'user_email'       => $ext['email'],
			'group_id'         => 2, // REGISTERED
			'user_type'        => USER_NORMAL,
			'user_ip'          => $this->user->ip,
			'user_regdate'     => time(),
			'user_passchg'     => time(),
			'user_password'    => 'unused', // random, unused
		);

		$phpbb_id = user_add($user_row);

		// Fetch fully populated row
		$sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $phpbb_id;
		$result = $this->db->sql_query($sql);
		$newrow = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $newrow;
	}
}
