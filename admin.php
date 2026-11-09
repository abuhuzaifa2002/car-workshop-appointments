<?php
require 'db.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (isset($_POST['password'])) {
  if (hash_equals(ADMIN_PASSWORD, $_POST['password'])) { $_SESSION['admin'] = true; header('Location: admin.php'); exit; }
  $loginErr = 'Wrong password.';
}
$isAdmin = !empty($_SESSION['admin']);
$msg = ''; $err = '';

if ($isAdmin && ($_POST['action'] ?? '') === 'update') {
  $id = (int)$_POST['id']; $date = trim($_POST['appt_date'] ?? ''); $mid = (int)($_POST['mechanic_id'] ?? 0);
  try {
    $pdo->beginTransaction();
    $a = $pdo->prepare('SELECT * FROM appointments WHERE id = ? FOR UPDATE'); $a->execute([$id]); $appt = $a->fetch();
    $m = $pdo->prepare('SELECT * FROM mechanics WHERE id = ? FOR UPDATE'); $m->execute([$mid]); $mech = $m->fetch();
    if (!$appt || !$mech || !valid_date($date)) {
      $err = 'Choose a valid date and mechanic.';
    } elseif (client_has_booking($pdo, $date, $appt['phone'], $appt['car_license'], $id)) {
      $err = 'This client already has another appointment on '.h($date).'.';
    } else {
      $c = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE mechanic_id = ? AND appt_date = ? AND id <> ?');
      $c->execute([$mid, $date, $id]);
      if ($c->fetchColumn() >= $mech['max_cars']) {
        $err = h($mech['name']).' is fully booked on '.h($date).'.';
      } else {
        $pdo->prepare('UPDATE appointments SET appt_date = ?, mechanic_id = ? WHERE id = ?')->execute([$date, $mid, $id]);
        $msg = 'Appointment #'.$id.' updated.';
      }
    }
    $pdo->commit();
  } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = 'Update failed. Try again.'; }
}

if ($isAdmin) {
  $filter = $_GET['date'] ?? '';
  $sql = 'SELECT a.*, m.name AS mechanic FROM appointments a JOIN mechanics m ON m.id = a.mechanic_id';
  $args = [];
  if (valid_date($filter)) { $sql .= ' WHERE a.appt_date = ?'; $args[] = $filter; }
  $sql .= ' ORDER BY a.appt_date, m.name, a.id';
  $st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
  $mechs = $pdo->query('SELECT id, name FROM mechanics ORDER BY name')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin: appointments</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top"><div class="wrap">
  <strong class="brand">Bay Four Auto Workshop</strong>
  <nav><a href="index.php">Booking page</a><?php if ($isAdmin): ?><a href="?logout=1">Log out</a><?php endif; ?></nav>
</div></header>
<main class="wrap">
<?php if (!$isAdmin): ?>
  <h1>Admin sign in</h1>
  <form method="post" class="narrow">
    <?php if (!empty($loginErr)) echo '<p class="err" role="alert">'.h($loginErr).'</p>'; ?>
    <label>Password<input type="password" name="password" required autofocus></label>
    <button type="submit">Sign in</button>
  </form>
<?php else: ?>
  <h1>Appointments</h1>
  <?php if ($msg) echo '<p class="ok" role="status">'.h($msg).'</p>'; ?>
  <?php if ($err) echo '<p class="err" role="alert">'.$err.'</p>'; ?>

  <form method="get" class="filter">
    <label>Show date<input type="date" name="date" value="<?= h($filter) ?>"></label>
    <button type="submit">Filter</button>
    <a href="admin.php">Show all</a>
  </form>

  <div class="scroll">
  <table>
    <thead><tr><th>#</th><th>Client</th><th>Phone</th><th>Car registration</th><th>Date</th><th>Mechanic</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
          <td><?= $r['id'] ?></td>
          <td><?= h($r['client_name']) ?></td>
          <td><?= h($r['phone']) ?></td>
          <td><?= h($r['car_license']) ?></td>
          <td><input form="f<?= $r['id'] ?>" type="date" name="appt_date" value="<?= h($r['appt_date']) ?>" required></td>
          <td><select form="f<?= $r['id'] ?>" name="mechanic_id">
            <?php foreach ($mechs as $m): ?>
              <option value="<?= $m['id'] ?>" <?= $m['id']==$r['mechanic_id']?'selected':'' ?>><?= h($m['name']) ?></option>
            <?php endforeach; ?>
          </select></td>
          <td><form id="f<?= $r['id'] ?>" method="post"></form><input form="f<?= $r['id'] ?>" type="hidden" name="id" value="<?= $r['id'] ?>"><input form="f<?= $r['id'] ?>" type="hidden" name="action" value="update"><button form="f<?= $r['id'] ?>" type="submit">Save</button></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7">No appointments yet for this view.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <p class="hint">Saving checks the new mechanic has a free place and the client has no other booking that day.</p>
<?php endif; ?>
</main>
</body>
</html>
