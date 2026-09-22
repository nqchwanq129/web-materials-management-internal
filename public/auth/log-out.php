<?php
chdir(dirname(__DIR__));
session_start();
session_destroy();
header('Location: ../auth/sign-in.php');
exit;
?> 