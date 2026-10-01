<?php
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

set_flash('info', 'Equipment services are processed by clinic staff in person.');
redirect('user/dashboard.php');
