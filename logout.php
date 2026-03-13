<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
	if (defined('SESSION_NAME')) {
		session_name(SESSION_NAME);
	}
	session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();

	// Expire the currently active session cookie.
	setcookie(
		session_name(),
		'',
		time() - 42000,
		$params['path'],
		$params['domain'],
		$params['secure'],
		$params['httponly']
	);

	// Expire potential legacy/default cookie as well.
	setcookie('PHPSESSID', '', time() - 42000, '/');
	setcookie('JASSNET_SESSION', '', time() - 42000, '/');
}

session_destroy();

$redirect = defined('APP_URL') ? APP_URL . '/index.php' : 'index.php';
header('Location: ' . $redirect);
exit();