<?php
require_once 'config/init.php';

use App\Controllers\AuthController;

$controller = new AuthController();

// dispatch appropriate action based on request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->loginSubmit();
} else {
    $controller->login();
}
