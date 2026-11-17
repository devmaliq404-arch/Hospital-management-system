<?php
session_start();
require_once __DIR__ . '/connect.php'; // creates $conn (mysqli)

// Load patients and doctors for the dropdowns
$patients = $conn->query("SELECT id, name, patient_code FROM patients ORDER BY name ASC")
                 ->fetch_all(MYSQLI_ASSOC);
$doctors  = $conn->query("SELECT id, name, specialty FROM doctors WHERE status = 'Active' OR status IS NULL ORDER BY name ASC")
                 ->fetch_all(MYSQLI_ASSOC);

// Time slots: 8:00 AM to 5:00 PM every 30 minutes, stored like "09:30 AM"
$slots = [];
for ($t = strtotime('08:00'); $t <= strtotime('17:00'); $t += 1800) {
    $slots[] = date('h:i A', $t);
}

$errors = [];
$old = ['patient_id' => '', 'doctor_id' => '', 'appt_date' => date('Y-m-d'), 'appt_time' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    $patientId = (int)$old['patient_id'];
    $doctorId  = (int)$old['doctor_id'];

    // Look up the real names from the database (don't trust the form)
    $patient = null; $doctor = null;
    foreach ($patients as $p) { if ((int)$p['id'] === $patientId) $patient = $p; }
    foreach ($doctors  as $d) { if ((int)$d['id'] === $doctorId)  $doctor  = $d; }

    if (!$patient) $errors[] = 'Please select a patient.';
    if (!$doctor)  $errors[] = 'Please select a doctor.';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['appt_date']) || $old['appt_date'] < date('Y-m-d')) {
        $errors[] = 'Please choose today or a future date.';
    }
    if (!in_array($old['appt_time'], $slots, true)) {
        $errors[] = 'Please choose a time slot.';
    }

    // Prevent double booking the same doctor at the same time
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE doctor_name = ? AND appt_date = ? AND appt_time = ? AND status <> 'Cancelled'"
        );
        $stmt->bind_param("sss", $doctor['name'], $old['appt_date'], $old['appt_time']);
        $stmt->execute();
        $stmt->bind_result($clash);
        $stmt->fetch();
        $stmt->close();

        if ($clash > 0) {
            $errors[] = $doctor['name'] . ' already has an appointment at ' . $old['appt_time']
                      . ' on ' . date('j M Y', strtotime($old['appt_date'])) . '. Please pick another time.';
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO appointments (patient_name, doctor_name, department, appt_date, appt_time, status)
             VALUES (?, ?, ?, ?, ?, 'Scheduled')"
        );
        $stmt->bind_param(
            "sssss",
            $patient['name'], $doctor['name'], $doctor['specialty'],
            $old['appt_date'], $old['appt_time']
        );

        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['flash'] = 'Appointment booked for ' . $patient['name'] . ' with ' . $doctor['name'] . '.';
            header("Location: appointments.php?date=" . urlencode($old['appt_date']));
            exit;
        }
        $stmt->close();
        $errors[] = 'Could not save the appointment. Please try again.';
    }
}

$pageTitle = 'Book appointment';
$active    = 'appointments';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Book appointment</h1>
        <p>Choose a patient, a doctor and a time.</p>
    </div>
    <a href="appointments.php" class="btn btn-ghost">Back to appointments</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash error">
        <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($patients) || empty($doctors)): ?>
    <div class="panel">
        <?php if (empty($patients)): ?>
            <p>There are no patients yet. <a href="add_patient.php" style="color:var(--brand);font-weight:600">Register a patient</a> first.</p>
        <?php endif; ?>
        <?php if (empty($doctors)): ?>
            <p>There are no active doctors yet. Add doctors to the <code>doctors</code> table first.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="panel">
    <form method="post" action="book_appointment.php">
        <div class="form-grid">
            <div class="field full">
                <label for="patient_id">Patient *</label>
                <select id="patient_id" name="patient_id" required>
                    <option value="">Select patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$old['patient_id'] === (int)$p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name'] . ($p['patient_code'] ? ' (' . $p['patient_code'] . ')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field full">
                <label for="doctor_id">Doctor *</label>
                <select id="doctor_id" name="doctor_id" required>
                    <option value="">Select doctor</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo (int)$d['id']; ?>" <?php echo (int)$old['doctor_id'] === (int)$d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['name'] . ' - ' . $d['specialty']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="appt_date">Date *</label>
                <input type="date" id="appt_date" name="appt_date" required
                       min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo htmlspecialchars($old['appt_date']); ?>">
            </div>

            <div class="field">
                <label for="appt_time">Time *</label>
                <select id="appt_time" name="appt_time" required>
                    <option value="">Select time</option>
                    <?php foreach ($slots as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $old['appt_time'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Book appointment</button>
            <a href="appointments.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
