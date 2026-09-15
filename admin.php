<?php
session_start();
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: app.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="box">

    <div style="float:right">
        <button onclick="location.href='app.php'">Back</button>
    </div>

    <h2>Admin Dashboard</h2>

    <!-- CREATE USER -->
    <div class="card">
        <h3>Create User</h3>

        <input id="new_user" placeholder="Username">
        <input id="new_pass" type="password" placeholder="Password">

        <select id="new_role">
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select>

        <button onclick="createUser()">Create</button>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="card">
        <h3>Change User Password</h3>

        <select id="pw_user"></select>
        <input id="pw_new" type="password" placeholder="New password">
        <button onclick="changePassword()">Change Password</button>
    </div>

    <!-- USER LIST -->
    <div class="card">
        <h3>Existing Users</h3>
        <div id="user_list"></div>
    </div>

</div>

<script>
const API = "admin_api.php";

async function api(action, data = {}) {
    const r = await fetch(API + "?action=" + action, {
        method: "POST",
        body: new URLSearchParams(data)
    });
    return r.json();
}

async function loadUsers() {
    const users = await api("list_users");

    const sel = document.getElementById("pw_user");
    const list = document.getElementById("user_list");

    sel.innerHTML = "";
    list.innerHTML = "";

    if (!users.length) {
        list.innerHTML = "<div class='empty'>No users found</div>";
        return;
    }

    users.forEach(u => {
        // Dropdown
        const opt = document.createElement("option");
        opt.value = u.user;
        opt.textContent = `${u.user} (${u.role})`;
        sel.appendChild(opt);

        // List
        const row = document.createElement("div");
        row.className = "user-row";
        row.textContent = `${u.user} — ${u.role}`;
        list.appendChild(row);
    });
}

async function createUser() {
    const user = document.getElementById("new_user").value.trim();
    const pass = document.getElementById("new_pass").value;
    const role = document.getElementById("new_role").value;

    if (!user || !pass) {
        alert("Enter username and password");
        return;
    }

    const res = await api("create_user", {
        user: user,
        password: pass,
        role: role
    });

    if (res.ok) {
        alert("User created");
        document.getElementById("new_user").value = "";
        document.getElementById("new_pass").value = "";
        loadUsers();
    } else {
        alert(res.error || "Failed");
    }
}

async function changePassword() {
    const user = document.getElementById("pw_user").value;
    const pass = document.getElementById("pw_new").value;

    if (!pass) {
        alert("Enter a new password");
        return;
    }

    if (!confirm(`Change password for "${user}"?`)) return;

    const res = await api("change_password", {
        user: user,
        password: pass
    });

    if (res.ok) {
        alert("Password updated");
        document.getElementById("pw_new").value = "";
    } else {
        alert(res.error || "Failed");
    }
}

loadUsers();
</script>

</body>
</html>
