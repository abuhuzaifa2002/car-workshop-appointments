<?php
// ---- Edit these for your server ----
const DB_HOST = 'localhost';
const DB_NAME = 'car_workshop';
const DB_USER = 'root';
const DB_PASS = '';
const ADMIN_PASSWORD = 'admin123';   // change before submitting
const MAX_PER_MECHANIC = 4;

session_start();
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Mechanics with free places on a date. */
function mechanics_with_slots(PDO $pdo, string $date): array {
  $st = $pdo->prepare(
    'SELECT m.id, m.name, m.max_cars, COUNT(a.id) AS booked
     FROM mechanics m LEFT JOIN appointments a ON a.mechanic_id = m.id AND a.appt_date = ?
     GROUP BY m.id ORDER BY m.name');
  $st->execute([$date]);
  $rows = $st->fetchAll();
  foreach ($rows as &$r) $r['free'] = max(0, $r['max_cars'] - $r['booked']);
  return $rows;
}

/** Has this client (phone or car license) already booked on this date? $ignoreId skips one row (for admin edits). */
function client_has_booking(PDO $pdo, string $date, string $phone, string $license, int $ignoreId = 0): bool {
  $st = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE appt_date = ? AND (phone = ? OR car_license = ?) AND id <> ?');
  $st->execute([$date, $phone, $license, $ignoreId]);
  return $st->fetchColumn() > 0;
}

function valid_date(string $d): bool {
  $o = DateTime::createFromFormat('Y-m-d', $d);
  return $o && $o->format('Y-m-d') === $d;
}
