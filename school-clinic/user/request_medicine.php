<?php
require_once __DIR__ . '/../config/db.php';
require_login(['user']);

set_flash('info', 'Please visit the clinic. Staff will enter your medicine request.');
redirect('user/dashboard.php');
