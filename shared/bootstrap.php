<?php

ini_set("display_errors", "0");
ini_set("log_errors", "1");
error_reporting(E_ALL);

date_default_timezone_set("Asia/Manila");

if (!defined("APP_ROOT")) {
    define("APP_ROOT", dirname(__DIR__));
}

function app_base_uri(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $document_root = realpath((string) ($_SERVER["DOCUMENT_ROOT"] ?? ""));
    $app_root = realpath(APP_ROOT);
    $base = "";

    if ($document_root && $app_root) {
        $doc = str_replace("\\", "/", $document_root);
        $root = str_replace("\\", "/", $app_root);
        $doc_cmp = strtolower($doc);
        $root_cmp = strtolower($root);
        if (str_starts_with($root_cmp, $doc_cmp)) {
            $base = substr($root, strlen($doc));
        }
    }

    $base = "/" . trim(str_replace("\\", "/", $base), "/");
    if ($base === "/") {
        $base = "";
    }

    return $base;
}

function url(string $path = ""): string
{
    $hash = "";
    if (str_contains($path, "#")) {
        [$path, $hash_part] = explode("#", $path, 2);
        $hash = "#" . $hash_part;
    }

    $path = ltrim(str_replace("\\", "/", $path), "/");
    $base = app_base_uri();

    if ($path === "") {
        return ($base === "" ? "/" : $base . "/") . $hash;
    }

    return ($base === "" ? "" : $base) . "/" . $path . $hash;
}

set_exception_handler(function (Throwable $exception): void {
    error_log("Forge Fitness error: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: text/html; charset=UTF-8");
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Something went wrong</title>';
    echo '<style>body{font-family:Calibri,Arial,sans-serif;background:#2a2b2a;color:#f1f1f2;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}div{max-width:480px;padding:32px;border:1px solid #d5af34;text-align:center;}h1{color:#d5af34;}a{color:#d5af34;}</style></head><body><div>';
    echo "<h1>Something went wrong</h1><p>Please try again in a moment. If this continues, contact Forge Fitness Gym.</p>";
    echo '<p><a href="' . htmlspecialchars(url("index.php"), ENT_QUOTES, "UTF-8") . '">Back to home</a></p></div></body></html>';
    exit();
});

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/functions.php";
forget_deleted_login($conn);
