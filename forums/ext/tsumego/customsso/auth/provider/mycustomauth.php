<?php

namespace tsumego\customsso\auth\provider;

use phpbb\auth\provider\provider_interface;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbb\user;

class mycustomauth implements provider_interface
{
	protected $request;
	protected $db;
	protected $config;
	protected $user;

	public function __construct(
		request_interface $request,
		driver_interface $db,
		\phpbb\config\config $config,
		user $user
	) {
		$this->request = $request;
		$this->db      = $db;
		$this->config  = $config;
		$this->user    = $user;
	}

	public function get_login_data()
	{
		return array();
	}

	public function link_account(array $user)
	{
		return array('status' => LOGIN_SUCCESS);
	}

	public function unlink_account(array $user)
	{
		return array('status' => LOGIN_SUCCESS);
	}

	public function get_auth_link_data($user_id = 0)
	{
		return array();
	}

	public function login_link_has_necessary_data(array $data)
	{
		return true;
	}

	public function init()
	{
		return false;
	}

	public function autologin()
	{
		if (!isset($_SESSION['loggedInUserID'])) {
			return false;
		}

		$external_id = (int) $_SESSION['loggedInUserID'];

		$sql = 'SELECT id, name, email
                FROM user
                WHERE id = ' . $external_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row) {
			return false;
		}

		return $this->get_or_create_bb_user($row);
	}

	public function login($username, $password)
	{
		return array(
			'status'    => 0,
			'error_msg' => 'LOGIN_ERROR_EXTERNAL_AUTH',
			'user_row'  => array(),
		);
	}

	public function logout($data, $new_session)
	{
		// Do nothing; logout is managed by your site
		return true;
	}

	public function validate_session($user)
	{
		return isset($_SESSION['loggedInUserID']);
	}

	public function acp()
	{
		return ['auth_method' => 'mycustomauth'];
	}

	public function get_acp_template($new_config)
	{
		return [
			'TEMPLATE_FILE' => 'auth_sso_body.html',
			'TEMPLATE_VARS' => [],
		];
	}

	protected function get_or_create_bb_user($ext)
	{
		$sql = 'SELECT * FROM ' . USERS_TABLE . '
                WHERE external_id = ' . (int) $ext['id'];
		$result = $this->db->sql_query($sql);
		$user_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($user_row) {
			return $user_row;
		}

		include_once(__DIR__ . '/../../../../includes/functions_user.php');

		$user_row = array(
			'username'         => $ext['name'],
			'username_clean'   => strtolower($ext['name']),
			'user_email'       => $ext['email'],
			'group_id'         => 2,
			'user_type'        => USER_NORMAL,
			'user_ip'          => $this->user->ip,
			'user_regdate'     => time(),
			'user_passchg'     => time(),
			'user_password'    => phpbb_hash(bin2hex(random_bytes(16))),
			'external_id'      => (int) $ext['id'],
		);

		$phpbb_id = user_add($user_row);

		$sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $phpbb_id;
		$result = $this->db->sql_query($sql);
		$newrow = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $newrow;
	}
}
