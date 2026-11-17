<?php
session_start();

require_once __DIR__ . '/connect.php'; // must create $conn (mysqli)

// Page to open after a successful login (change to your real filename)
$redirectAfterLogin = 'homepage.php';

// Already logged in? Skip the form

$error = '';
// Process the form only when it is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signIn'])) {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, password FROM users WHERE email = ?"
        );

        if (!$stmt) {
            $error = "Something went wrong. Please try again.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];

                header("Location: $redirectAfterLogin");
                exit();
            } else {
                $error = "Incorrect email or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | HMS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="login-page">

<div class="login-layout">

    <div class="login-art">

        <a class="brand brand-light" href="login.php">
            <span class="brand-mark">✚</span>
            <span>Iconic Care Hospital<small>HOSPITAL SYSTEM</small></span>
        </a>

        <div class="art-copy">
            <span class="pill-light">CARE, CONNECTED</span>

            <h1>
                Better care<br>
                starts with<br>
                <em>better systems.</em>
            </h1>

            <p>
                A clear view of your hospital operations,
                from patient registration to follow-up care.
            </p>
        </div>

        <div class="art-foot">
            A calmer way to manage care.
        </div>

    </div>

    <div class="login-panel">
        <div class="login-form">

            <div class="mobile-brand">✚ Hms</div>

            <span class="eyebrow">WELCOME BACK</span>
            <h2>Sign in to your workspace</h2>

            <p class="muted">
                Enter your account details to continue.
            </p>

            <?php if ($error !== ''): ?>
                <p style="color: red;">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <form method="post" action="login.php">

                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="username"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    required
                >

                <button
                    class="btn btn-primary btn-full"
                    type="submit"
                    name="signIn"
                >
                    Sign in <span>→</span>
                </button>

            </form>

            <div class="login-note">
                New here?
                <a class="text-link" href="register.php">
                    Create an account
                </a>
            </div>

            <div class="login-security">
                ▣ &nbsp; Secure account access
            </div>

        </div>
    </div>

</div>

</body>
</html>