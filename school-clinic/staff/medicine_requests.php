<?php
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

set_flash('info', 'Medicine requests are recorded through Medicine Dispensing.');
redirect('staff/dispense.php');
