<?php
/**
 * Compatibility redirect for the old profile URL.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

redirect('user/settings.php');