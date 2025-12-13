<?php
 require_once 'config/constants.php';
require_once 'config/auth.php';

$auth = new Auth();
$auth->logout();
?>