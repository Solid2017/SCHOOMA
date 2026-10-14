<?php
require_once __DIR__ . '/auth.php';
logoutUser();
redirect(ADMIN_URL . '/login.php');
