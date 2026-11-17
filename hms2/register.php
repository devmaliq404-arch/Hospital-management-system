
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account - Iconic Care Hospital  Management System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="login-page">

<div class="login-layout">

    <div class="login-art">
        <a class="brand brand-light" href="register.php">
            <span class="brand-mark">✚</span>
            <span>Iconic Care Hospital <small>HOSPITAL SYSTEM</small></span>
        </a>

        <div class="art-copy">
            <span class="pill-light">GET STARTED</span>
            <h1>Your workspace,<br>ready when<br><em>you are.</em></h1>
            <p>Create a demo account and explore patient records,
                doctors, appointments, and billing in this project.</p>
        </div>

        <div class="art-foot">My hms</div>
    </div>

    <div class="login-panel">
        <div class="login-form">

            <div class="mobile-brand">✚ Hospital management system</div>

            <span class="eyebrow">NEW ACCOUNT</span>
            <h2>Create your account</h2>
            <p class="muted">Enter your details below to get started.</p>

            <?php if (isset($_GET['error'])): ?>
                <p style="color:red;">
                    <?= htmlspecialchars($_GET['error']) ?>
                </p>
            <?php endif; ?>

            <form method="post" action="register2.php">

                <label for="name">Full name</label>
                <input id="name" type="text" name="name"
                       placeholder="Your full name" required>

                <label for="email">Email address</label>
                <input id="email" type="email" name="email"
                       autocomplete="email"
                       placeholder="someone@example.com" required>

                <label for="password">Password (at least 12 characters)</label>
                <input id="password" type="password" name="password"
                       autocomplete="new-password"
                       placeholder="Create a strong password"
                       required>

                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" type="password"
                       name="confirm_password"
                       autocomplete="new-password"
                       placeholder="Enter the password again"
                        required>

                <button class="btn btn-primary btn-full"
                        type="submit" name="login-page">
                    Create account <span>-></span>
                </button>

            </form>

            <div class="login-note">
                Already have an account?
                <a class="text-link" href="login.php">Sign in</a>
            </div>

            <div class="login-security">
                ▣ &nbsp; Demo project account registration
            </div>

        </div>
    </div>

</div>

</body>
</html>
