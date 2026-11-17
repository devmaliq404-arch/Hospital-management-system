<?php
session_start();
include 'connect.php';



if (isset($_POST['signUp'])) {

    $firstName = ($_POST['fName'] ?? '');
    $lastName  = ($_POST['lName'] ?? '');
    $email     = ($_POST['email'] ?? '');
    $petName = ($_POST['pet_name'] ?? '');
    $password  = $_POST['password'] ?? '';


    
    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        die("Please fill in all required fields.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Please enter a valid email address.");
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);



    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM login WHERE email = ?");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        echo "Email Address Already Exists!";

    } else {

        // Insert user
       $stmt = $conn->prepare(
    "INSERT INTO login
    (firstName, lastName, email, password, pet_name)
    VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "sssss",
    $firstName,
    $lastName,
    $email,
    $passwordHash,
    $petName
);


        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        
        $stmt->bind_param(
            "sssss",
            $firstName,
            $lastName,
            $email,
            $passwordHash,
            $petName
        );

        if ($stmt->execute()) {

            $_SESSION['email'] = $email;
            $_SESSION['name'] = $firstName . " " . $lastName;

            header("Location: homepage.php");
            exit();

        } else {

            echo "Error: " . $stmt->error;
        }
    }

    $stmt->close();
}




if (isset($_POST['signIn'])) {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Find user by email
    $stmt = $conn->prepare(
        "SELECT id, firstName, lastName, email, password 
         FROM login 
         WHERE email = ?"
    );

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $row = $result->fetch_assoc();

        // Verify hashed password
        if (password_verify($password, $row['password'])) {

            $_SESSION['email'] = $row['email'];
            $_SESSION['name'] = $row['firstName'] . " " . $row['lastName'];

            header("Location: homepage.php");
            exit();

        } else {

            echo "Incorrect email or password.";
        }

    } else {

        echo "Incorrect email or password.";
    }

    $stmt->close();
}

$conn->close();
?>
