<?php
/**
 * Staff — Legacy stock adjustment page redirected to the unified inventory workflow.
 */
require_once __DIR__ . '/../config/db.php';
require_login(['staff']);

set_flash('info', 'The clinic now uses the unified Inventory Operations page for stock receiving and adjustments.');
redirect('staff/inventory.php');
