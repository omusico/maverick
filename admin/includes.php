<?php

define('ROOT_DIR', '/home/zolika/roomcaptain_admin/');

require(ROOT_DIR . 'includes/config.php');
require(ROOT_DIR . '../includes/message.php');
require(ROOT_DIR . 'includes/frame.php');
require(ROOT_DIR . '../includes/image_upload.php');
require(ROOT_DIR . '../includes/language.php');
require(ROOT_DIR . '../includes/error_handler.php');
require(ROOT_DIR . '../includes/db_config.php');
require(ROOT_DIR . '../includes/db.php');
require(ROOT_DIR . '../includes/login.php');

set_error_handler('sessionErrorHandler');


session_start();

if(isset($_SESSION['login_hotel'])) {
	$configFile = ROOT_DIR . '../includes/config/' . $_SESSION['login_hotel'] . '.php';
	if(file_exists($configFile)) {
		require($configFile);
	}
}

require(ROOT_DIR . '../includes/exchange.php');


?>