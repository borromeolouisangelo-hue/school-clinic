<?php
$content = file_get_contents('C:\xampp\htdocs\school-clinic\user\dashboard.php');
$lines = explode("\n", $content);
for ($i = 75; $i <= 85; $i++) {
    echo ($i+1) . ': ' . $lines[$i] . "\n";
}