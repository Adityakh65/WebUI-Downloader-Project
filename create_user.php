<?php
if (php_sapi_name() !== "cli") die("CLI only\n");

if ($argc < 3) {
    echo "Usage: php create_user.php username password [admin|user]\n";
    exit;
}

$user = preg_replace('/[^a-zA-Z0-9_-]/', '', $argv[1]);
$pass = $argv[2];
$role = ($argv[3] ?? "user") === "admin" ? "admin" : "user";

$dbFile = __DIR__ . "/users.json";
$users = json_decode(@file_get_contents($dbFile), true) ?: [];

if (isset($users[$user])) {
    echo "User exists\n";
    exit;
}

$users[$user] = [
    "password" => password_hash($pass, PASSWORD_BCRYPT),
    "role" => $role,
    "disabled" => false,
    "created" => date("Y-m-d H:i:s")
];

file_put_contents($dbFile, json_encode($users, JSON_PRETTY_PRINT));

$dir = __DIR__ . "/data/$user";
@mkdir("$dir/downloads", 0755, true);
file_put_contents("$dir/index.json", "{}");
file_put_contents("$dir/settings.json", json_encode([
    "type" => "none",
    "user" => "",
    "pass" => "",
    "host" => "",
    "port" => ""
], JSON_PRETTY_PRINT));

echo "User '$user' ($role) created\n";
