<?php
session_start();
require_once __DIR__ . '/connect.php'; // creates $conn (mysqli)

$errors = [];
$old = [
    'name' => '', 'age' => '', 'gender' => '', 'phone' => '',
    'email' => '', 'blood_group' => '', 'address' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    $validGenders = ['Male', 'Female', 'Other'];
    $validBlood   = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    if ($old['name'] === '' || mb_strlen($old['name']) < 2) {
        $errors[] = 'Please enter the patient\'s full name.';
    }
    if (!ctype_digit($old['age']) || (int)$old['age'] > 130) {
        $errors[] = 'Please enter a valid age (0 to 130).';
    }
    if (!in_array($old['gender'], $validGenders, true)) {
        $errors[] = 'Please select a gender.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $old['phone'])) {
        $errors[] = 'Phone number can only contain digits, spaces, + and -.';
    }
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array($old['blood_group'], $validBlood, true)) {
        $errors[] = 'Invalid blood group selected.';
    }

    if (empty($errors)) {
        $age = (int)$old['age'];

        $stmt = $conn->prepare(
            "INSERT INTO patients (name, age, gender, phone, email, blood_group, address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sisssss",
            $old['name'], $age, $old['gender'], $old['phone'],
            $old['email'], $old['blood_group'], $old['address']
        );

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();

            // Give the patient an ID like PT-00007
            $code = 'PT-' . str_pad($newId, 5, '0', STR_PAD_LEFT);
            $up = $conn->prepare("UPDATE patients SET patient_code = ? WHERE id = ?");
            $up->bind_param("si", $code, $newId);
            $up->execute();
            $up->close();

            $_SESSION['flash'] = 'Patient registered successfully (' . $code . ').';
            header("Location: patients.php");
            exit;
        } else {
            $errors[] = 'Could not save the patient. Please try again.';
            $stmt->close();
        }
    }
}

$pageTitle = 'Register patient';
$active    = 'patients';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Register patient</h1>
        <p>Fill in the details to create a new patient record.</p>
    </div>
    <a href="patients.php" class="btn btn-ghost">Back to patients</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash error">
        <?php foreach ($errors as $e): ?>
            <div><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="panel">
    <form method="post" action="add_patient.php">
        <div class="form-grid">
            <div class="field full">
                <label for="name">Full name *</label>
                <input type="text" id="name" name="name" required
                       value="<?php echo htmlspecialchars($old['name']); ?>">
            </div>

            <div class="field">
                <label for="age">Age *</label>
                <input type="number" id="age" name="age" min="0" max="130" required
                       value="<?php echo htmlspecialchars($old['age']); ?>">
            </div>

            <div class="field">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" required>
                    <option value="">Select</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                        <option value="<?php echo $g; ?>" <?php echo $old['gender'] === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone"
                       value="<?php echo htmlspecialchars($old['phone']); ?>">
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?php echo htmlspecialchars($old['email']); ?>">
            </div>

            <div class="field">
                <label for="blood_group">Blood group</label>
                <select id="blood_group" name="blood_group">
                    <option value="">Unknown</option>
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $b): ?>
                        <option value="<?php echo $b; ?>" <?php echo $old['blood_group'] === $b ? 'selected' : ''; ?>><?php echo $b; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field full">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($old['address']); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Save patient</button>
            <a href="patients.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
