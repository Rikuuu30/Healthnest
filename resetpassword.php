<?php

require_once __DIR__ . "/init.php";

requireLogin();

$user = currentUser($conn);

if (!$user) {
    signOut();
    redirect("login.php");
}

$message = "";
$messageType = "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current = $_POST["current"] ?? "";
    $new = $_POST["new"] ?? "";
    $confirm = $_POST["confirm"] ?? "";

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif (!passwordMatches($current, $user["password"])) {
        $message = "Current password is incorrect.";
    } elseif (strlen($new) < 8) {
        $message = "New password must be at least 8 characters long.";
    } elseif ($new !== $confirm) {
        $message = "New password and confirm password do not match.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $user["id"]);
        mysqli_stmt_execute($stmt);

        $message = "Password changed successfully.";
        $messageType = "success";
    }
}

$pageTitle = "Change Password";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Buyer Account</div>
            <h2>Change Password</h2>
            <p>Update the password for <?php echo e($user["email"]); ?>.</p>
        </div>

        <div class="admin-menu">
            <a href="profile.php">Back to My Profile</a>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="<?php echo e($messageType); ?>"><?php echo e($message); ?></div>
    <?php endif; ?>

    <form method="post" action="resetpassword.php">
        <?php echo csrfField(); ?>

        <label for="current">Current Password</label><br>
        <input id="current" type="password" name="current" required><br><br>

        <label for="new">New Password</label><br>
        <input id="new" type="password" name="new" minlength="8" required><br><br>

        <label for="confirm">Confirm New Password</label><br>
        <input id="confirm" type="password" name="confirm" minlength="8" required><br><br>

        <button type="submit">Save Password</button>
    </form>

</main>

<?php require __DIR__ . "/footer.php"; ?>
