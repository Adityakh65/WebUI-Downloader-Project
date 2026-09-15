<?php
session_start();

if (isset($_SESSION["user"])) {
    header("Location: app.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user = $_POST["user"] ?? "";
    $pass = $_POST["pass"] ?? "";

    $db = json_decode(@file_get_contents(__DIR__ . "/users.json"), true) ?: [];

    if (!isset($db[$user])) {
        $error = "Invalid login";
    } elseif ($db[$user]["disabled"]) {
        $error = "Account disabled";
    } elseif (password_verify($pass, $db[$user]["password"])) {
        $_SESSION["user"] = $user;
        $_SESSION["role"] = $db[$user]["role"];
        header("Location: app.php");
        exit;
    } else {
        $error = "Invalid login";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="box">
    <h2>Web Download Manager</h2>
    <form method="post" class="card">
        <input name="user" placeholder="Username" required>
        <input name="pass" type="password" placeholder="Password" required>
        <button>Login</button>
        <?php if ($error): ?>
            <div style="color:#e74c3c;margin-top:10px"><?=htmlspecialchars($error)?></div>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
