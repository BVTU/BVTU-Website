<?php
require_once 'auth.php';
logoutMember();
header('Location: ../index.php');
exit;
