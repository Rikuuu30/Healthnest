<?php

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $secureCookie,
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    session_start();
}

require_once __DIR__ . "/dbconnect.php";
require_once __DIR__ . "/functions.php";
