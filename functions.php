<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function redirect($url)
{
    header("Location: " . $url);
    exit();
}

function setFlash($type, $message)
{
    $_SESSION["flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function getFlash()
{
    if (empty($_SESSION["flash"])) {
        return null;
    }

    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $flash;
}

function csrfToken()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken($token)
{
    return hash_equals($_SESSION["csrf_token"] ?? "", (string) $token);
}

function normalizeLevel($level)
{
    return strtolower(trim((string) $level));
}

function accountFullName($user)
{
    if (!$user) {
        return "User";
    }

    $parts = [
        $user["firstname"] ?? "",
        $user["middlename"] ?? "",
        $user["lastname"] ?? "",
    ];

    $name = trim(preg_replace("/\s+/", " ", implode(" ", array_filter($parts))));
    return $name !== "" ? $name : ($user["email"] ?? "User");
}

function sessionUserId()
{
    if (isset($_SESSION["user_id"])) {
        return (int) $_SESSION["user_id"];
    }

    if (isset($_SESSION["id"])) {
        $_SESSION["user_id"] = (int) $_SESSION["id"];
        return (int) $_SESSION["user_id"];
    }

    return null;
}

function isLoggedIn()
{
    return sessionUserId() !== null;
}

function isAdmin()
{
    $level = normalizeLevel($_SESSION["level"] ?? "");
    return in_array($level, ["admin", "administrator", "seller"], true);
}

function dashboardUrl()
{
    return isAdmin() ? "seller_dashboard.php" : "buyer_dashboard.php";
}

function accountLevelValue($level)
{
    $level = normalizeLevel($level);
    return in_array($level, ["admin", "administrator", "seller"], true) ? "seller" : "buyer";
}

function accountLevelLabel($level)
{
    return ucfirst(accountLevelValue($level));
}

function requireLogin()
{
    if (!isLoggedIn()) {
        setFlash("error", "Please log in first.");
        redirect("login.php");
    }
}

function requireAdmin()
{
    requireLogin();

    if (!isAdmin()) {
        http_response_code(403);
        die("You do not have permission to access this page.");
    }
}

function nextAccountId($conn)
{
    $result = mysqli_query($conn, "SELECT MAX(id) AS max_id FROM tblaccount");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return ((int) ($row["max_id"] ?? 0)) + 1;
}

function ensureAccountHasId($conn, $user)
{
    if (!$user || (int) ($user["id"] ?? 0) > 0) {
        return $user;
    }

    $newId = nextAccountId($conn);
    $email = $user["email"] ?? "";

    if ($email !== "") {
        $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET id = ? WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $newId, $email);
        mysqli_stmt_execute($stmt);
        $user["id"] = $newId;
    }

    return $user;
}

function ensureAccountIdByEmail($conn, $email)
{
    $stmt = mysqli_prepare($conn, "SELECT * FROM tblaccount WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return ensureAccountHasId($conn, $user);
}

function currentUser($conn)
{
    $userId = sessionUserId();

    if ($userId === null) {
        return null;
    }

    if ($userId <= 0 && !empty($_SESSION["email"])) {
        $fixedUser = ensureAccountIdByEmail($conn, $_SESSION["email"]);

        if ($fixedUser && (int) ($fixedUser["id"] ?? 0) > 0) {
            $_SESSION["user_id"] = (int) $fixedUser["id"];
            $_SESSION["id"] = (int) $fixedUser["id"];
            return $fixedUser;
        }
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM tblaccount WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result) ?: null;
}

function signInUser($user)
{
    session_regenerate_id(true);

    $_SESSION["user_id"] = (int) $user["id"];
    $_SESSION["id"] = (int) $user["id"];
    $_SESSION["email"] = $user["email"];
    $_SESSION["level"] = accountLevelValue($user["level"] ?? "buyer");
    $_SESSION["fullname"] = accountFullName($user);
}

function signOut()
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    session_destroy();
}

function passwordMatches($plainPassword, $storedPassword)
{
    if ($storedPassword === null || $storedPassword === "") {
        return false;
    }

    $info = password_get_info($storedPassword);

    if (!empty($info["algo"])) {
        return password_verify($plainPassword, $storedPassword);
    }

    return hash_equals((string) $storedPassword, (string) $plainPassword);
}

function isAccountActive($user)
{
    $status = strtolower(trim((string) ($user["status"] ?? "")));
    return !in_array($status, ["disable", "disabled", "inactive", "blocked"], true);
}

function rememberCookieOptions($expires)
{
    return [
        "expires" => $expires,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ];
}

function setRememberEmail($email)
{
    setcookie("remember_email", $email, rememberCookieOptions(time() + (86400 * 30)));
    setcookie("remember", "", rememberCookieOptions(time() - 3600));
}

function clearRememberEmail()
{
    setcookie("remember_email", "", rememberCookieOptions(time() - 3600));
    setcookie("remember", "", rememberCookieOptions(time() - 3600));
}

// ---- Category helpers ----

function getCategories($conn)
{
    $result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
    $cats = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $cats[] = $row;
    }

    return $cats;
}

function getCategoryById($conn, $id)
{
    $stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE category_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result) ?: null;
}

// ---- Product helpers ----

function getProducts($conn, $categoryId = null)
{
    if ($categoryId) {
        $stmt = mysqli_prepare($conn, "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.status = 'active' AND p.category_id = ? ORDER BY p.product_name");
        mysqli_stmt_bind_param($stmt, "i", $categoryId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.status = 'active' ORDER BY p.product_name");
    }

    $products = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }

    return $products;
}

function getProductById($conn, $id)
{
    $stmt = mysqli_prepare($conn, "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.product_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result) ?: null;
}

function formatPrice($amount)
{
    return "PHP " . number_format((float) $amount, 2);
}

function productImageFilename($image)
{
    $image = str_replace("\\", "/", trim((string) $image));
    $filename = basename($image);

    return $filename !== "" ? $filename : "placeholder.jpg";
}

function handleProductImageUpload($fieldName, $currentImage = "placeholder.jpg")
{
    $currentImage = productImageFilename($currentImage);

    if (empty($_FILES[$fieldName]) || (int) ($_FILES[$fieldName]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [
            "success" => true,
            "filename" => $currentImage,
        ];
    }

    $uploadedImage = $_FILES[$fieldName];

    if (!isset($uploadedImage["error"]) || is_array($uploadedImage["error"])) {
        return [
            "success" => false,
            "message" => "The selected product image is not a valid upload.",
        ];
    }

    if ((int) $uploadedImage["error"] !== UPLOAD_ERR_OK) {
        return [
            "success" => false,
            "message" => "The product image could not be uploaded. Please try again.",
        ];
    }

    if (!isset($uploadedImage["size"], $uploadedImage["tmp_name"]) || is_array($uploadedImage["size"]) || is_array($uploadedImage["tmp_name"])) {
        return [
            "success" => false,
            "message" => "The selected product image is not a valid upload.",
        ];
    }

    if ((int) $uploadedImage["size"] <= 0 || (int) $uploadedImage["size"] > 5 * 1024 * 1024) {
        return [
            "success" => false,
            "message" => "The product image must be 5 MB or smaller.",
        ];
    }

    if (!is_uploaded_file($uploadedImage["tmp_name"])) {
        return [
            "success" => false,
            "message" => "The selected product image is not a valid upload.",
        ];
    }

    $imageInfo = @getimagesize($uploadedImage["tmp_name"]);
    $imageMime = $imageInfo["mime"] ?? "";
    $detectedMime = class_exists("finfo")
        ? (new finfo(FILEINFO_MIME_TYPE))->file($uploadedImage["tmp_name"])
        : $imageMime;
    $allowedImageTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
    ];

    if (!isset($allowedImageTypes[$imageMime]) || $detectedMime !== $imageMime) {
        return [
            "success" => false,
            "message" => "Choose a JPG, PNG, or WebP image for the product.",
        ];
    }

    if (($imageInfo[0] ?? 0) > 6000 || ($imageInfo[1] ?? 0) > 6000) {
        return [
            "success" => false,
            "message" => "The product image dimensions are too large.",
        ];
    }

    $uploadDirectory = __DIR__ . "/images/products";

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
        return [
            "success" => false,
            "message" => "The product image folder could not be created.",
        ];
    }

    $filename = "product-" . date("YmdHis") . "-" . bin2hex(random_bytes(5)) . "." . $allowedImageTypes[$imageMime];
    $destination = $uploadDirectory . "/" . $filename;

    if (!move_uploaded_file($uploadedImage["tmp_name"], $destination)) {
        return [
            "success" => false,
            "message" => "The product image could not be saved. Please try again.",
        ];
    }

    return [
        "success" => true,
        "filename" => $filename,
    ];
}

// ---- Audit log helper ----

function logAudit($conn, $userId, $action, $table, $recordId, $details)
{
    $stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, action, table_affected, record_id, details, created_at) VALUES (?, ?, ?, ?, ?, CURDATE())");
    mysqli_stmt_bind_param($stmt, "issis", $userId, $action, $table, $recordId, $details);
    mysqli_stmt_execute($stmt);
}

// ---- Order tracking helpers ----

function orderStatusLabels()
{
    return [
        "paid" => "To Pack",
        "packed" => "To Ship",
        "shipped" => "Shipped",
        "out_delivery" => "Out for Delivery",
        "delivered" => "Delivered",
        "cancelled" => "Cancelled",
    ];
}

function orderStatusLabel($status)
{
    $status = strtolower(trim((string) $status));
    $labels = orderStatusLabels();

    if (isset($labels[$status])) {
        return $labels[$status];
    }

    return "To Pack";
}

function orderStatusClass($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === "") {
        return "paid";
    }

    return preg_replace("/[^a-z0-9_-]/", "", $status);
}

function orderStatusStep($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === "packed") {
        return 2;
    }

    if ($status === "shipped") {
        return 3;
    }

    if ($status === "out_delivery") {
        return 4;
    }

    if ($status === "delivered") {
        return 5;
    }

    if ($status === "cancelled") {
        return 0;
    }

    return 1;
}

function orderStatusMessage($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === "packed") {
        return "Seller packed and prepared the order.";
    }

    if ($status === "shipped") {
        return "Seller shipped the order.";
    }

    if ($status === "out_delivery") {
        return "Seller handed the order to the courier.";
    }

    if ($status === "delivered") {
        return "Order was marked as delivered.";
    }

    if ($status === "cancelled") {
        return "Order was cancelled.";
    }

    return "Buyer placed this order.";
}

function addOrderHistory($conn, $orderId, $status, $note, $updatedBy)
{
    $stmt = mysqli_prepare($conn, "INSERT INTO order_status_history (order_id, status, note, updated_by, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "issi", $orderId, $status, $note, $updatedBy);
    mysqli_stmt_execute($stmt);
}

function formatDateTimeLabel($value)
{
    if (!$value) {
        return "Pending";
    }

    $time = strtotime($value);

    if (!$time) {
        return e($value);
    }

    return date("M d, Y - g:i A", $time);
}

// ---- Cart helpers ----

function cartAdd($conn, $userId, $productId, $qty)
{
    $stmt = mysqli_prepare($conn, "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $userId, $productId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($existing) {
        $newQty = $existing["quantity"] + $qty;
        $upd = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE cart_id = ?");
        mysqli_stmt_bind_param($upd, "ii", $newQty, $existing["cart_id"]);
        mysqli_stmt_execute($upd);
        return;
    }

    $ins = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity, created_at) VALUES (?, ?, ?, CURDATE())");
    mysqli_stmt_bind_param($ins, "iii", $userId, $productId, $qty);
    mysqli_stmt_execute($ins);
}

function cartUpdate($conn, $userId, $productId, $qty)
{
    if ($qty <= 0) {
        cartRemove($conn, $userId, $productId);
        return;
    }

    $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $qty, $userId, $productId);
    mysqli_stmt_execute($stmt);
}

function cartRemove($conn, $userId, $productId)
{
    $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $userId, $productId);
    mysqli_stmt_execute($stmt);
}

function cartClear($conn, $userId)
{
    $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
}

function cartItems($conn, $userId)
{
    $stmt = mysqli_prepare($conn, "SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price, p.stock_quantity, p.status, p.image, cat.category_name FROM cart c JOIN products p ON c.product_id = p.product_id LEFT JOIN categories cat ON p.category_id = cat.category_id WHERE c.user_id = ? ORDER BY c.cart_id");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $row["subtotal"] = $row["price"] * $row["quantity"];
        $items[] = $row;
    }

    return $items;
}

function cartTotal($conn, $userId)
{
    $total = 0;

    foreach (cartItems($conn, $userId) as $item) {
        $total += $item["subtotal"];
    }

    return $total;
}

function cartCount($conn, $userId)
{
    if (!$userId) {
        return 0;
    }

    $stmt = mysqli_prepare($conn, "SELECT SUM(quantity) AS total_qty FROM cart WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $row["total_qty"] ? (int) $row["total_qty"] : 0;
}
