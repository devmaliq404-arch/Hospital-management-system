
<?php
session_start();

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['login-page'])) {
    header("Location: register.php");
    exit();
}

// Get form values
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Check required fields
if ($name === '' || $email === '' ||
    $password === '' || $confirmPassword === '') {
    die("Please fill in all required fields.");
}

// Check email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Please enter a valid email address.");
}



// Check password confirmation
if ($password !== $confirmPassword) {
    die("Passwords do not match.");
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Check whether email already exists
$stmt = $conn->prepare(
    "SELECT id FROM users WHERE email = ?"
);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    die("This email address is already registered.");
}

$stmt->close();

// Save new user
$stmt = $conn->prepare(
    "INSERT INTO users (name, email, password)
     VALUES (?, ?, ?)"
);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("sss", $name, $email, $passwordHash);

if ($stmt->execute()) {

    // Save the new user's session
    session_regenerate_id(true);

    $_SESSION['user_id'] = $stmt->insert_id;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;

    $stmt->close();
    $conn->close();

    header("Location: homepage.php");
    exit();

} else {
    echo "Registration failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
