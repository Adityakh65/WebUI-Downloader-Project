<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: index.php");
    exit;
}
$isAdmin = ($_SESSION["role"] ?? "") === "admin";
?>
<!DOCTYPE html>
<html>
<head>
<title>Web Download Manager</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="box">

    <div style="float:right">
        <?php if ($isAdmin): ?>
            <button onclick="location.href='admin.php'">Admin</button>
        <?php endif; ?>
        <button onclick="location.href='logout.php'">Logout</button>
    </div>

    <h2>Web Download Manager</h2>

    <!-- Prevent Chrome password manager -->
    <form onsubmit="return false" autocomplete="off">
        <div class="form">
            <input
                id="url"
                type="url"
                name="download_url"
                placeholder="Paste direct download URL"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
            >
            <button type="button" onclick="add()">Start</button>
        </div>
    </form>

    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('downloads', this)">Downloads</button>
        <button class="tab-btn" onclick="showTab('files', this)">Files</button>
        <button class="tab-btn" onclick="showTab('settings', this)">Settings</button>
    </div>

    <div id="downloads"></div>
    <div id="files" style="display:none"></div>

    <div id="settings" style="display:none">
        <div class="card">
            <h3>Proxy Settings</h3>

            <label>Proxy Type</label>
            <select id="proxy_type" onchange="proxyTypeChanged()">
                <option value="none">None</option>
                <option value="socks5">SOCKS5</option>
                <option value="http">HTTP</option>
                <option value="https">HTTPS</option>
            </select>

            <div id="proxy_fields" style="display:none;margin-top:10px">
                <input id="proxy_user" placeholder="Username (optional)">
                <input id="proxy_pass" type="password" placeholder="Password (optional)">
                <input id="proxy_host" placeholder="Host">
                <input id="proxy_port" placeholder="Port">
            </div>

            <br>
            <button type="button" onclick="saveSettings()">Save Settings</button>
        </div>
    </div>

</div>

<script>
const API = "api.php";

async function api(action, data = {}) {
    const r = await fetch(API + "?action=" + action, {
        method: "POST",
        body: new URLSearchParams(data)
    });
    return r.json();
}

async function add() {
    const url = document.getElementById("url").value.trim();
    if (!url) return;
    await api("add", { url });
    document.getElementById("url").value = "";
}

function showTab(tab, btn) {
    ["downloads", "files", "settings"].forEach(t => {
        document.getElementById(t).style.display = t === tab ? "block" : "none";
    });
    document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");

    if (tab === "settings") loadSettings();
}

function proxyTypeChanged() {
    const t = document.getElementById("proxy_type").value;
    document.getElementById("proxy_fields").style.display =
        t === "none" ? "none" : "block";
}

function humanSize(bytes) {
    if (!bytes || bytes <= 0) return "0 MB";
    const units = ["B", "KB", "MB", "GB", "TB"];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    return bytes.toFixed(1) + " " + units[i];
}

function extractSpeed(status) {
    const m = status.match(/([\d.]+\s*(KB|MB|GB)\/s)/i);
    return m ? m[1] : "—";
}

function extractETA(status) {
    const m = status.match(/eta\s+([0-9a-zA-Z\s]+)/i);
    return m ? m[1].trim() : "—";
}



async function loadSettings() {
    const s = await api("get_settings");
    document.getElementById("proxy_type").value = s.type || "none";
    proxyTypeChanged();
    document.getElementById("proxy_user").value = s.user || "";
    document.getElementById("proxy_pass").value = s.pass || "";
    document.getElementById("proxy_host").value = s.host || "";
    document.getElementById("proxy_port").value = s.port || "";
}

async function saveSettings() {
    await api("save_settings", {
        type: proxy_type.value,
        user: proxy_user.value,
        pass: proxy_pass.value,
        host: proxy_host.value,
        port: proxy_port.value
    });
    alert("Settings saved");
}

async function deleteFile(name) {
    if (!confirm("Delete file?\n\n" + name)) return;
    await api("delete_file", { file: name });
    refreshFiles();
}


async function refreshDownloads() {
    const list = await api("list");
    const box = document.getElementById("downloads");
    box.innerHTML = "";

    if (!list.length) {
        box.innerHTML = "<div class='empty'>No downloads</div>";
        return;
    }

    list.forEach((d, i) => {
        const div = document.createElement("div");
        div.className = "card";

        let badge = "";
        if (d.finished) badge = "<span class='finished'>FINISHED</span>";
        else if (d.paused) badge = "<span class='badge'>PAUSED</span>";

        div.innerHTML = `
            <div class="filename">${i + 1}. ${d.file} ${badge}</div>

            <div class="progress-wrap big">
                <div class="progress-bar" style="width:${d.percent || 0}%"></div>
            </div>

            <div class="progress-text big-text">
                ${d.percent || 0}% Downloaded — 
                ${humanSize(d.downloaded)} / ${humanSize(d.total)}
            </div>

            <div class="speed-text">
                Speed: ${extractSpeed(d.status || "")} — ETA: ${extractETA(d.status || "")}
            </div>

            <div class="controls">
                ${!d.finished ? `
                    <button class="pause" onclick="api('pause',{id:'${d.id}'})">Pause</button>
                    <button class="resume" onclick="api('resume',{id:'${d.id}'})">Resume</button>
                    <button class="cancel" onclick="api('cancel',{id:'${d.id}'})">Cancel</button>
                ` : `
                    <button class="cancel" onclick="api('remove',{id:'${d.id}'})">Remove</button>
                `}
            </div>
        `;
        box.appendChild(div);
    });
}

async function refreshFiles() {
    const files = await api("files");
    const box = document.getElementById("files");

    if (!files.length) {
        box.innerHTML = "<div class='empty'>No files</div>";
        return;
    }

    let html = "<table class='table'><tr><th>File</th><th>Size</th><th>Downloaded On</th><th>Action</th></tr>";
    files.forEach(f => {
        html += `
        <tr>
            <td>
                <a href="api.php?download=${encodeURIComponent(f.name)}">
                    ${f.name}
                </a>
            </td>
            <td>${f.size}</td>
            <td>${f.time}</td>
            <td>
                <button class="cancel" onclick="deleteFile('${f.name}')">
                    Delete
                </button>
            </td>

        </tr>`;
    });
    html += "</table>";
    box.innerHTML = html;
}

setInterval(() => {
    refreshDownloads();
    refreshFiles();
}, 3000);

refreshDownloads();
refreshFiles();
</script>
</body>
</html>
