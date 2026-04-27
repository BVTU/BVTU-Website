<?php
require_once 'auth.php';
logoutMember();
header('Location: ../index.html');
exit;
