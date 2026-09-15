<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["user"])) {
    http_response_code(403);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION["user"]);

$baseDir = __DIR__ . "/data/$user";
$downloadDir = "$baseDir/downloads";
$indexFile = "$baseDir/index.json";
$settingsFile = "$baseDir/settings.json";

@mkdir($downloadDir, 0755, true);
if (!file_exists($indexFile)) file_put_contents($indexFile, "{}");
if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode([
        "type" => "none",
        "user" => "",
        "pass" => "",
        "host" => "",
        "port" => ""
    ], JSON_PRETTY_PRINT));
}

/* ================= FILE DOWNLOAD ================= */
if (isset($_GET["download"])) {
    $file = basename($_GET["download"]);
    $path = "$downloadDir/$file";

    if (!file_exists($path)) {
        http_response_code(404);
        exit("Not found");
    }

    while (ob_get_level()) ob_end_clean();
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$file\"");
    header("Content-Length: " . filesize($path));
    header("Cache-Control: no-cache");
    readfile($path);
    exit;
}

/* ================= HELPERS ================= */

function loadIndex() {
    global $indexFile;
    return json_decode(@file_get_contents($indexFile), true) ?: [];
}

function saveIndex($d) {
    global $indexFile;
    file_put_contents($indexFile, json_encode($d, JSON_PRETTY_PRINT));
}

function loadSettings() {
    global $settingsFile;
    return json_decode(@file_get_contents($settingsFile), true) ?: [];
}

function buildProxyArgs() {
    $s = loadSettings();
    if (($s["type"] ?? "none") === "none") return "";

    if (empty($s["host"]) || empty($s["port"])) return "";

    $auth = "";
    if (!empty($s["user"]) || !empty($s["pass"])) {
        $auth = $s["user"] . ":" . $s["pass"] . "@";
    }

    if ($s["type"] === "http") $scheme = "http://";
    elseif ($s["type"] === "https") $scheme = "https://";
    else $scheme = "socks5h://";

    $proxy = escapeshellarg($scheme . $auth . $s["host"] . ":" . $s["port"]);

    return "-e use_proxy=yes -e http_proxy=$proxy -e https_proxy=$proxy";
}

/*
 * CR-AWARE TAIL
 * Handles wget --progress=bar:force correctly
 */
function tailLine($file) {
    if (!file_exists($file)) return "";

    $content = file_get_contents($file);
    if ($content === false) return "";

    // bar:force uses carriage returns, not newlines
    $parts = preg_split("/\r|\n/", $content);

    for ($i = count($parts) - 1; $i >= 0; $i--) {
        $line = trim($parts[$i]);
        if ($line !== "") {
            // Remove filename spam
            $line = preg_replace('/^.*?\]\s*/', '', $line);
            return $line;
        }
    }
    return "";
}

function isFinished($log) {
    if (!file_exists($log)) return false;
    $c = file_get_contents($log);
    return strpos($c, "saved [") !== false ||
           strpos($c, "already fully retrieved") !== false;
}

/* ================= API ================= */

$action = $_GET["action"] ?? "";
$id = basename($_POST["id"] ?? "");

$logFile = "$downloadDir/$id.log";
$pidFile = "$downloadDir/$id.pid";
$nameFile = "$downloadDir/$id.name";
$stateFile = "$downloadDir/$id.state";

$index = loadIndex();

switch ($action) {

    case "get_settings":
        echo json_encode(loadSettings());
        break;

    case "save_settings":
        file_put_contents($settingsFile, json_encode([
            "type" => $_POST["type"] ?? "none",
            "user" => trim($_POST["user"] ?? ""),
            "pass" => trim($_POST["pass"] ?? ""),
            "host" => trim($_POST["host"] ?? ""),
            "port" => trim($_POST["port"] ?? "")
        ], JSON_PRETTY_PRINT));
        echo json_encode(["ok" => true]);
        break;

    case "add":
        $url = trim($_POST["url"] ?? "");
        if (!$url) exit(json_encode(["error" => "No URL"]));

        $id = "dl_" . time() . "_" . rand(1000, 9999);

        $logFile = "$downloadDir/$id.log";
        $pidFile = "$downloadDir/$id.pid";
        $nameFile = "$downloadDir/$id.name";
        $stateFile = "$downloadDir/$id.state";

        $file = basename(parse_url($url, PHP_URL_PATH));
        file_put_contents($nameFile, $file);
        // Get total file size (HEAD request)
        $total = 0;
        $headers = @get_headers($url, 1);
        if ($headers && isset($headers["Content-Length"])) {
            $total = is_array($headers["Content-Length"])
                ? intval(end($headers["Content-Length"]))
                : intval($headers["Content-Length"]);
        }

        // Save total size
        file_put_contents("$downloadDir/$id.size", $total);


        date_default_timezone_set("Asia/Kolkata");
        $index[$id] = [
            "file" => $file,
            "time" => date("Y-m-d H:i:s"),
            "url" => $url
        ];
        saveIndex($index);

        $proxy = buildProxyArgs();

        // BACK TO WORKING MODE
        $cmd = "cd " . escapeshellarg($downloadDir) .
               " && nohup wget -c --progress=bar:force $proxy " .
               escapeshellarg($url) .
               " > " . escapeshellarg($logFile) .
               " 2>&1 & echo $! > " . escapeshellarg($pidFile);

        exec($cmd);
        echo json_encode(["ok" => true]);
        break;

    case "pause":
        if (file_exists($pidFile)) {
            exec("kill -STOP " . trim(file_get_contents($pidFile)));
            file_put_contents($stateFile, "paused");
        }
        echo json_encode(["ok" => true]);
        break;

    case "resume":
        if (file_exists($pidFile)) {
            exec("kill -CONT " . trim(file_get_contents($pidFile)));
            @unlink($stateFile);
        }
        echo json_encode(["ok" => true]);
        break;

    case "cancel":
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            exec("kill $pid");
            exec("kill -9 $pid");
        }

        if (file_exists($nameFile)) {
            @unlink("$downloadDir/" . trim(file_get_contents($nameFile)));
        }

        @unlink($pidFile);
        @unlink($logFile);
        @unlink($nameFile);
        @unlink($stateFile);

        unset($index[$id]);
        saveIndex($index);
        echo json_encode(["ok" => true]);
        break;

    case "remove":
        @unlink($pidFile);
        @unlink($logFile);
        @unlink($nameFile);
        @unlink($stateFile);
        @unlink("$downloadDir/$id.size");
        echo json_encode(["ok" => true]);
        break;

    case "list":
        $out = [];

        foreach (glob("$downloadDir/dl_*.log") as $log) {
            $id = basename($log, ".log");
            $nameFile = "$downloadDir/$id.name";
            $stateFile = "$downloadDir/$id.state";

            $finished = isFinished($log);
            $status = tailLine($log);

            $percent = 0;
            $sizeFile = "$downloadDir/$id.size";
            $downloaded = 0;
            $targetFile = "$downloadDir/" . trim(@file_get_contents($nameFile));

            if (file_exists($targetFile)) {
                $downloaded = filesize($targetFile);
            }

            $total = file_exists($sizeFile) ? intval(file_get_contents($sizeFile)) : 0;

            if ($total > 0) {
                $percent = min(100, intval(($downloaded / $total) * 100));
            } elseif ($finished) {
                $percent = 100;
            }


            $out[] = [
                "id" => $id,
                "file" => file_exists($nameFile) ? trim(file_get_contents($nameFile)) : "Unknown",
                "status" => $status,
                "percent" => $percent,
                "downloaded" => $downloaded,
                "total" => $total,
                "paused" => file_exists($stateFile) && !$finished,
                "finished" => $finished
            ];
        }

        echo json_encode($out);
        break;

    case "files":
        $files = [];
        foreach (glob("$downloadDir/*") as $f) {
            if (is_file($f) && !preg_match('/\.log$|\.pid$|\.name$|\.state$|\.size$/', $f)) {
                $files[] = [
                    "name" => basename($f),
                    "size" => round(filesize($f) / 1024 / 1024, 2) . " MB",
                    "time" => date("Y-m-d H:i:s", filemtime($f))
                ];
            }
        }
        echo json_encode($files);
        break;
    
    /* ---------- DELETE FILE ---------- */
    case "delete_file":
        $file = basename($_POST["file"] ?? "");
        if (!$file) {
            echo json_encode(["error" => "No file"]);
            break;
        }

        $path = "$downloadDir/$file";

        if (!file_exists($path)) {
            echo json_encode(["error" => "File not found"]);
            break;
        }

        if (@unlink($path)) {
            echo json_encode(["ok" => true]);
        } else {
            echo json_encode(["error" => "Delete failed"]);
        }
        break;




    default:
        echo json_encode(["error" => "Invalid action"]);
}
