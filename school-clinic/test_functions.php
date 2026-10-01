<?php
include "config/db.php";
require_once "includes/functions.php";
require_once "includes/auth.php";

$test = array();
$test["require_login"] = function_exists("require_login");
$test["is_logged_in"] = function_exists("is_logged_in");
$test["current_role"] = function_exists("current_role");
$test["dashboard_for"] = function_exists("dashboard_for");
$test["redirect"] = function_exists("redirect");
$test["e"] = function_exists("e");
$test["url"] = function_exists("url");
$test["current_user"] = function_exists("current_user");
$test["fmt_dt"] = function_exists("fmt_dt");
$test["get_setting"] = function_exists("get_setting");
$test["log_action"] = function_exists("log_action");
$test["record_failed_login"] = function_exists("record_failed_login");
$test["clear_failed_logins"] = function_exists("clear_failed_logins");
$test["regenerate_session"] = function_exists("regenerate_session");
$test["is_login_blocked"] = function_exists("is_login_blocked");

foreach ($test as $name => $exists) {
    echo ($exists ? "OK" : "MISSING") . " - " . $name . "\n";
}
?>