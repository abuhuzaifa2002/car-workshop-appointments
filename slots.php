<?php
// JSON endpoint: mechanics + free places for a date (called by index.php on date change)
require 'db.php';
header('Content-Type: application/json');
$date = $_GET['date'] ?? '';
if (!valid_date($date)) { echo '[]'; exit; }
echo json_encode(mechanics_with_slots($pdo, $date));
