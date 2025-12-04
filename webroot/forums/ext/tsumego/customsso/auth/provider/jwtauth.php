<?php

namespace tsumego\customsso\auth\provider;

use phpbb\auth\provider\provider_interface;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbb\user;

/**
 * JWT-based authentication provider for phpBB.
 * 
 * Uses the same JWT tokens as the main Tsumego Hero application
 */
class jwtauth implements provider_interface
{
	protected $request;
	protected $db;
	protected $config;
	protected $user;
	protected $phpbb_root_path;
	protected $php_ext;

	public function __construct(
		request_interface $request,
		driver_interface $db,
		\phpbb\config\config $config,
		user $user,
		string $phpbb_root_path,
		string $php_ext
	)
	{
		$this->request = $request;
		$this->db      = $db;
		$this->config  = $config;
		$this->user    = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function get_login_data()
	{
		return [
			'autologin'   => true,
			'password'    => false,
			'credentials' => [],
			'user_row'    => [],
		];
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
		return true;
	}

	public function autologin()
	{
		// Read JWT token from cookie
		$jwtToken = $this->request->variable('auth_token', '', true, request_interface::COOKIE);
		if (empty($jwtToken))
			return false;

		// Validate JWT token and get user ID
		$userId = $this->validateJwtToken($jwtToken);
		if (!$userId)
			return false;

		// Load user from main app database
		$sql = 'SELECT id, name, email
        FROM user
        WHERE id = ' . (int) $userId;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
			return false;

		return $this->get_or_create_bb_user($row);
	}

	public function login($username, $password)
	{
		// If user is already logged in via SSO, allow ACP reauth
		if ($this->user->data['user_id'] > 1)
		{
			return [
				'status'   => LOGIN_SUCCESS,
				'error_msg'=> false,
				'user_row' => $this->user->data,
			];
		}

		// Otherwise: fail (no normal password login)
		return [
			'status'    => LOGIN_ERROR_EXTERNAL_AUTH,
			'error_msg' => 'LOGIN_ERROR_EXTERNAL_AUTH',
			'user_row'  => [],
		];
	}

	public function logout($data, $new_session)
	{
		return true;
	}

	public function validate_session($user)
	{
		// If JWT token is missing but phpBB thinks user is logged in — force logout
		$jwtToken = $this->request->variable('auth_token', '', true, request_interface::COOKIE);

		// Skip anonymous (user_id 1)
		if ($user['user_id'] > 1 && empty($jwtToken))
			return false;  // tells phpBB the session is invalid → logs user out

		// Case 2: user is anonymous BUT JWT token exists → force new login cycle
		if ($user['user_id'] == ANONYMOUS && !empty($jwtToken))
			return false;  // phpBB destroys anonymous session, autologin() will run

		return true;  // session is valid
	}

	public function acp()
	{
		return ['auth_method' => 'jwtauth'];
	}

	public function get_acp_template($new_config)
	{
		return [
			'TEMPLATE_FILE' => '@tsumego_customsso/auth_sso_body.html',
			'TEMPLATE_VARS' => [],
		];
	}

	/**
	 * Validate JWT token and extract user ID.
	 * 
	 * This duplicates logic from src/Utility/JwtAuth.php
	 * because we can't easily include CakePHP classes in phpBB context.
	 * 
	 * @param string $token JWT token
	 * @return int|null User ID if valid, null otherwise
	 */
	protected function validateJwtToken(string $token): ?int
	{
		// Split JWT into parts
		$parts = explode('.', $token);
		if (count($parts) !== 3)
			return null;

		list($headerB64, $payloadB64, $signatureB64) = $parts;

		// Verify signature
		$secret = $this->getJwtSecret();
		$expectedSignature = $this->base64UrlEncode(
			hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
		);

		if (!hash_equals($expectedSignature, $signatureB64))
			return null;

		// Decode payload
		$payload = json_decode($this->base64UrlDecode($payloadB64), true);
		if (!$payload)
			return null;

		// Check expiration
		if (isset($payload['exp']) && $payload['exp'] < time())
			return null;

		// Extract user ID
		return isset($payload['sub']) ? (int) $payload['sub'] : null;
	}

	/**
	 * Get JWT secret from configuration.
	 * Parses CakePHP config file to extract Security.salt value.
	 */
	protected function getJwtSecret(): string
	{
		// Load CakePHP config file
		// From: webroot/forums/ext/tsumego/customsso/auth/provider/jwtauth.php
		// To:   config/core.php
		$configPath = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))) . '/config/core.php';
		
		if (!file_exists($configPath))
			throw new \Exception('Config file not found: ' . $configPath);

		$configContent = file_get_contents($configPath);
		
		// Extract Security.salt value using regex
		// Matches: Configure::write('Security.salt', 'VALUE');
		if (preg_match("/Configure::write\\('Security\\.salt',\\s*'([^']+)'/", $configContent, $matches))
			return $matches[1];

		// Also try Security.jwtSecret if it exists
		if (preg_match("/Configure::write\\('Security\\.jwtSecret',\\s*'([^']+)'/", $configContent, $matches))
			return $matches[1];

		throw new \Exception('No JWT secret configured in core.php');
	}

	/**
	 * Base64 URL-safe decode
	 */
	protected function base64UrlDecode(string $input): string
	{
		$remainder = strlen($input) % 4;
		if ($remainder)
			$input .= str_repeat('=', 4 - $remainder);

		return base64_decode(strtr($input, '-_', '+/'));
	}

	/**
	 * Base64 URL-safe encode
	 */
	protected function base64UrlEncode(string $input): string
	{
		return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
	}

	/**
	 * Get or create phpBB user from external user data
	 */
	protected function get_or_create_bb_user($ext)
	{
		$sql = 'SELECT * FROM ' . USERS_TABLE . '
                WHERE external_id = ' . (int) $ext['id'];
		$result = $this->db->sql_query($sql);
		$user_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($user_row)
			return $user_row;

		include_once($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);

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
