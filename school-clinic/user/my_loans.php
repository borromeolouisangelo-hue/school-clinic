<?php
/**
 * User — My equipment loans.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

set_flash('info', 'Equipment history is now available in My Activity.');
redirect('user/my_requests.php');