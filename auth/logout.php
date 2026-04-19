<?php
/**
 * Cloud Sekolah - Logout
 */
session_start();
session_unset();
session_destroy();
header('Location: ../guest/index.php');
exit;
