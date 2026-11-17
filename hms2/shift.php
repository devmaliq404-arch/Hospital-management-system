<?php
require __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string)($_POST['password'] ?? '');
    if ($email && $password !== '') {
        $stmt = $pdo->prepare('SELECT id,name,email,password_hash,role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]); $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; $_SESSION['user_name']=$user['name']; $_SESSION['user_role']=$user['role']; redirect('index.php');
        }
    }
    $error = 'Invalid email or password.';
}
?>