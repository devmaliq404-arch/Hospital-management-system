<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Hospital information ----
    if ($action === 'hospital') {
        $fields = [
            'hospital_name'    => mb_substr(trim($_POST['hospital_name'] ?? ''), 0, 120),
            'hospital_phone'   => mb_substr(trim($_POST['hospital_phone'] ?? ''), 0, 30),
            'hospital_email'   => mb_substr(trim($_POST['hospital_email'] ?? ''), 0, 120),
            'hospital_address' => mb_substr(trim($_POST['hospital_address'] ?? ''), 0, 255),
        ];
        if ($fields['hospital_name'] === '') {
            flash('Hospital name cannot be empty.', 'error');
        } elseif ($fields['hospital_email'] !== '' && !filter_var($fields['hospital_email'], FILTER_VALIDATE_EMAIL)) {
            flash('Please enter a valid hospital email.', 'error');
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            foreach ($fields as $k => $v) {
                $stmt->bind_param("ss", $k, $v);
                $stmt->execute();
            }
            $stmt->close();
            flash('Hospital information saved.');
        }
    }

    // ---- Profile name ----
    elseif ($action === 'profile' && $userId > 0) {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2) {
            flash('Please enter your name.', 'error');
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $userId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['name'] = $name;
            flash('Profile updated.');
        }
    }

    // ---- Change password ----
    elseif ($action === 'password' && $userId > 0) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($hash);
        $found = $stmt->fetch();
        $stmt->close();

        if (!$found || !password_verify($current, $hash)) {
            flash('Your current password is incorrect.', 'error');
        } elseif (strlen($new) < 8) {
            flash('The new password must be at least 8 characters.', 'error');
        } elseif ($new !== $confirm) {
            flash('The new passwords do not match.', 'error');
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $stmt->close();
            flash('Password changed successfully.');
        }
    }

    redirect('settings.php');
}

$hospital = [
    'name'    => get_setting($conn, 'hospital_name', 'HMS Hospital'),
    'phone'   => get_setting($conn, 'hospital_phone'),
    'email'   => get_setting($conn, 'hospital_email'),
    'address' => get_setting($conn, 'hospital_address'),
];

$me = null;
if ($userId > 0) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$pageTitle = 'Settings';
$active    = 'settings';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Settings</h1>
        <p>Hospital details and your account.</p>
    </div>
</div>

<div class="stack">

<div class="panel">
    <h2>Hospital information</h2>
    <p style="color:var(--muted);margin-top:-6px">Shown on printed invoices.</p>
    <form method="post">
        <input type="hidden" name="action" value="hospital">
        <div class="form-grid">
            <div class="field full">
                <label for="hospital_name">Hospital name *</label>
                <input type="text" id="hospital_name" name="hospital_name" value="<?php echo e($hospital['name']); ?>" required>
            </div>
            <div class="field">
                <label for="hospital_phone">Phone</label>
                <input type="text" id="hospital_phone" name="hospital_phone" value="<?php echo e($hospital['phone']); ?>">
            </div>
            <div class="field">
                <label for="hospital_email">Email</label>
                <input type="email" id="hospital_email" name="hospital_email" value="<?php echo e($hospital['email']); ?>">
            </div>
            <div class="field full">
                <label for="hospital_address">Address</label>
                <input type="text" id="hospital_address" name="hospital_address" value="<?php echo e($hospital['address']); ?>">
            </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Save hospital info</button></div>
    </form>
</div>

<?php if ($me): ?>
<div class="two">
    <div class="panel">
        <h2>My profile</h2>
        <form method="post">
            <input type="hidden" name="action" value="profile">
            <div class="form-grid" style="grid-template-columns:1fr">
                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?php echo e($me['name']); ?>" required>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="text" value="<?php echo e($me['email']); ?>" disabled>
                </div>
            </div>
            <div class="form-actions"><button class="btn" type="submit">Save profile</button></div>
        </form>
    </div>

    <div class="panel">
        <h2>Change password</h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="password">
            <div class="form-grid" style="grid-template-columns:1fr">
                <div class="field">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="new_password">New password (8+ characters)</label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                </div>
            </div>
            <div class="form-actions"><button class="btn" type="submit">Change password</button></div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="panel">
    <h2>My account</h2>
    <p style="color:var(--muted)">Sign in to change your profile and password. <a href="login.php" style="color:var(--brand);font-weight:600">Go to login</a></p>
</div>
<?php endif; ?>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
