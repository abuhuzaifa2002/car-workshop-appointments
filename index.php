<?php
require 'db.php';
$errors = []; $done = null;
$f = ['client_name'=>'','address'=>'','phone'=>'','car_license'=>'','car_engine'=>'','appt_date'=>'','mechanic_id'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');

  // Server-side validation
  if ($f['client_name'] === '') $errors[] = 'Enter your name.';
  if ($f['address'] === '') $errors[] = 'Enter your address.';
  if (!preg_match('/^\+?[0-9]{7,15}$/', $f['phone'])) $errors[] = 'Phone must contain only digits (7–15), optionally starting with +.';
  if ($f['car_license'] === '') $errors[] = 'Enter the car license number.';
  if (!preg_match('/^[0-9]+$/', $f['car_engine'])) $errors[] = 'Engine number must contain digits only.';
  if (!valid_date($f['appt_date'])) $errors[] = 'Choose a valid appointment date.';
  elseif ($f['appt_date'] < date('Y-m-d')) $errors[] = 'Appointment date cannot be in the past.';
  if (!ctype_digit($f['mechanic_id'])) $errors[] = 'Select a mechanic.';

  if (!$errors) {
    try {
      $pdo->beginTransaction();
      // Lock the mechanic row so two clients can't take the last slot at once
      $st = $pdo->prepare('SELECT id, name, max_cars FROM mechanics WHERE id = ? FOR UPDATE');
      $st->execute([$f['mechanic_id']]);
      $mech = $st->fetch();
      if (!$mech) {
        $errors[] = 'That mechanic does not exist.';
      } elseif (client_has_booking($pdo, $f['appt_date'], $f['phone'], $f['car_license'])) {
        $errors[] = 'You have already taken an appointment on '.h($f['appt_date']).'. Please choose a different date.';
      } else {
        $c = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE mechanic_id = ? AND appt_date = ?');
        $c->execute([$mech['id'], $f['appt_date']]);
        if ($c->fetchColumn() >= $mech['max_cars']) {
          $errors[] = h($mech['name']).' is fully booked on '.h($f['appt_date']).'. Please pick another mechanic or date.';
        } else {
          $ins = $pdo->prepare('INSERT INTO appointments (client_name,address,phone,car_license,car_engine,appt_date,mechanic_id) VALUES (?,?,?,?,?,?,?)');
          $ins->execute([$f['client_name'],$f['address'],$f['phone'],$f['car_license'],$f['car_engine'],$f['appt_date'],$mech['id']]);
          $done = ['id'=>$pdo->lastInsertId(),'mechanic'=>$mech['name'],'date'=>$f['appt_date']];
        }
      }
      $pdo->commit();
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Something went wrong while saving. Please try again.';
    }
  }
}
$selDate = valid_date($f['appt_date']) ? $f['appt_date'] : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Book a mechanic</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top"><div class="wrap">
  <strong class="brand">Bay Four Auto Workshop</strong>
  <nav><a href="#help">Help</a><a href="admin.php">Admin</a></nav>
</div></header>

<main class="wrap grid">
  <section>
    <h1>Book your mechanic</h1>
    <p class="lead">Pick the mechanic you trust with your car. Each mechanic takes <?= MAX_PER_MECHANIC ?> cars a day, and the list below shows what is still open.</p>

    <?php if ($done): ?>
      <div class="ok" role="status">
        <h2>Appointment confirmed</h2>
        <p>Reference <strong>#<?= h($done['id']) ?></strong>: <?= h($done['mechanic']) ?> on <?= h($done['date']) ?>. Bring your car and this reference.</p>
        <p><a href="index.php">Book another car</a></p>
      </div>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="err" role="alert"><ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
      <?php endif; ?>

      <form method="post" id="bookForm" novalidate>
        <label>Full name<input name="client_name" value="<?= h($f['client_name']) ?>" required></label>
        <label>Address<input name="address" value="<?= h($f['address']) ?>" required></label>
        <div class="row">
          <label>Phone<input name="phone" inputmode="tel" value="<?= h($f['phone']) ?>" placeholder="01700000000" required></label>
          <label>Appointment date<input type="date" name="appt_date" id="appt_date" min="<?= date('Y-m-d') ?>" value="<?= h($selDate) ?>" required></label>
        </div>
        <div class="row">
          <label>Car license number<input name="car_license" value="<?= h($f['car_license']) ?>" required></label>
          <label>Car engine number<input name="car_engine" inputmode="numeric" value="<?= h($f['car_engine']) ?>" required></label>
        </div>
        <fieldset>
          <legend>Choose a mechanic</legend>
          <div id="mechList" aria-live="polite"></div>
        </fieldset>
        <p id="clientErr" class="err" role="alert" hidden></p>
        <button type="submit">Book appointment</button>
      </form>
    <?php endif; ?>
  </section>

  <aside id="help">
    <h2>Help</h2>
    <ol>
      <li>Fill in your details. Phone and engine number take digits only.</li>
      <li>Choose a date. The mechanic list updates with free places for that day.</li>
      <li>Select a mechanic with places left and press <em>Book appointment</em>.</li>
      <li>One appointment per client per day. A full mechanic cannot be booked, so pick another or change the date.</li>
    </ol>
  </aside>
</main>

<script>
const list = document.getElementById('mechList');
const dateEl = document.getElementById('appt_date');
const keep = <?= json_encode($f['mechanic_id']) ?>;

async function loadMechanics() {
  const d = dateEl.value;
  if (!d) { list.textContent = 'Pick a date to see available mechanics.'; return; }
  list.textContent = 'Loading…';
  try {
    const rows = await (await fetch('slots.php?date=' + encodeURIComponent(d))).json();
    const cur = (document.querySelector('input[name=mechanic_id]:checked') || {}).value || keep;
    list.innerHTML = '';
    rows.forEach(m => {
      const full = m.free <= 0;
      const l = document.createElement('label');
      l.className = 'mech' + (full ? ' full' : '');
      l.innerHTML = '<input type="radio" name="mechanic_id" value="' + m.id + '"' +
        (full ? ' disabled' : '') + (String(m.id) === String(cur) && !full ? ' checked' : '') + '>' +
        '<span class="nm"></span><span class="free">' + (full ? 'Fully booked' : m.free + ' of ' + m.max_cars + ' places free') + '</span>';
      l.querySelector('.nm').textContent = m.name;
      list.appendChild(l);
    });
  } catch (e) { list.textContent = 'Could not load mechanics. Refresh the page.'; }
}
dateEl.addEventListener('change', loadMechanics);
loadMechanics();

// Client-side validation (server checks again)
document.getElementById('bookForm')?.addEventListener('submit', e => {
  const f = e.target, msg = [];
  if (!f.client_name.value.trim()) msg.push('Enter your name.');
  if (!f.address.value.trim()) msg.push('Enter your address.');
  if (!/^\+?[0-9]{7,15}$/.test(f.phone.value.trim())) msg.push('Phone must be digits only (7–15).');
  if (!f.car_license.value.trim()) msg.push('Enter the car license number.');
  if (!/^[0-9]+$/.test(f.car_engine.value.trim())) msg.push('Engine number must be digits only.');
  if (!f.appt_date.value) msg.push('Choose a date.');
  if (!f.querySelector('input[name=mechanic_id]:checked')) msg.push('Select a mechanic.');
  const box = document.getElementById('clientErr');
  box.hidden = !msg.length; box.innerHTML = msg.join('<br>');
  if (msg.length) e.preventDefault();
});
</script>
</body>
</html>
