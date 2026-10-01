<?php
/**
 * Staff — unified medicine and equipment history.
 * This page is intentionally retained as a redirect to the single dispensing workflow.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

set_flash('info', 'The clinic now uses the combined Dispensing & History screen for medicine and equipment activity.');
redirect('staff/dispense.php?history_status=all');
