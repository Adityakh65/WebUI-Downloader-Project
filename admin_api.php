<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    http_response_code(403);
    echo json_encode(["error" => "Admin only"]);
    exit;
}

$usersFile = __DIR__ . "/data/users.json";
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, "{}");
}

$users = json_decode(file_get_contents($usersFile), true);
if (!is_array($users)) $users = [];

function saveUsers($users, $file) {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

$action = $_GET["action"] ?? "";

/* ========== LIST USERS ========== */
if ($action === "list_users") {
    $out = [];
    foreach ($users as $name => $info) {
        $out[] = [
            "user" => $name,
            "role" => $info["role"] ?? "user"
        ];
    }
    echo json_encode($out);
    exit;
}

/* ========== CREATE USER ========== */
if ($action === "create_user") {
    $user = trim($_POST["user"] ?? "");
    $pass = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "user";

    if (!$user || !$pass) {
        echo json_encode(["error" => "Missing fields"]);
        exit;
    }

    if (isset($users[$user])) {
        echo json_encode(["error" => "User already exists"]);
        exit;
    }

    $users[$user] = [
        "password" => password_hash($pass, PASSWORD_DEFAULT),
        "role" => ($role === "admin") ? "admin" : "user"
    ];

    saveUsers($users, $usersFile);

    // Create user folder
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $user);
    @mkdir(__DIR__ . "/data/$safe/downloads", 0755, true);

    echo json_encode(["ok" => true]);
    exit;
}

/* ========== CHANGE PASSWORD ========== */
if ($action === "change_password") {
    $target = trim($_POST["user"] ?? "");
    $newpass = $_POST["password"] ?? "";

    if (!$target || !$newpass) {
        echo json_encode(["error" => "Missing fields"]);
        exit;
    }

    if (!isset($users[$target])) {
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    $users[$target]["password"] = password_hash($newpass, PASSWORD_DEFAULT);
    saveUsers($users, $usersFile);

    echo json_encode(["ok" => true]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);
