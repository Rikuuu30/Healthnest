<?php

require_once __DIR__ . "/init.php";

requireAdmin();

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

    $hasUpper = preg_match('/[A-Z]/', $new);
    $hasLower = preg_match('/[a-z]/', $new);
    $hasNumber = preg_match('/\d/', $new);

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif (!passwordMatches($current, $user["password"])) {
        $message = "Current password is incorrect.";
    } elseif (strlen($new) < 8) {
        $message = "New password must be at least 8 characters long.";
    } elseif (!$hasUpper || !$hasLower || !$hasNumber) {
        $message = "Use uppercase, lowercase, and at least one number for admin security.";
    } elseif ($new !== $confirm) {
        $message = "New password and confirm password do not match.";
    } elseif (passwordMatches($new, $user["password"])) {
        $message = "Choose a password that is different from your current password.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $user["id"]);
        mysqli_stmt_execute($stmt);

        logAudit($conn, sessionUserId(), "Change Admin Password", "tblaccount", (int) $user["id"], "Admin changed own password");

        $message = "Admin password changed successfully.";
        $messageType = "success";
    }
}

$pageTitle = "Admin Password";
require __DIR__ . "/header.php";
?>

<main class="page-main admin-password-page">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Security</div>
            <h2>Change Admin Password</h2>
            <p>Update the password for <?php echo e($user["email"]); ?> with stronger seller-console requirements.</p>
        </div>
        <a class="button secondary" href="profile.php">Back to Seller Profile</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="<?php echo e($messageType); ?>"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="admin-password-layout">
        <form class="seller-form admin-password-form" method="post" action="seller_changepassword.php">
            <?php echo csrfField(); ?>

            <div class="seller-form-heading">
                <span class="panel-label">Password Update</span>
                <h3>Secure Access</h3>
            </div>

            <div class="password-field-group">
                <label for="current">Current Password</label>
                <div class="password-entry">
                    <input id="current" type="password" name="current" required autocomplete="current-password">
                    <button type="button" class="password-visibility-toggle" data-toggle-password="current">Show</button>
                </div>
            </div>

            <div class="password-field-group">
                <label for="new">New Password</label>
                <div class="password-entry">
                    <input id="new" type="password" name="new" minlength="8" required autocomplete="new-password">
                    <button type="button" class="password-visibility-toggle" data-toggle-password="new">Show</button>
                </div>
            </div>

            <div class="password-field-group">
                <label for="confirm">Confirm New Password</label>
                <div class="password-entry">
                    <input id="confirm" type="password" name="confirm" minlength="8" required autocomplete="new-password">
                    <button type="button" class="password-visibility-toggle" data-toggle-password="confirm">Show</button>
                </div>
            </div>

            <button type="submit">Save Admin Password</button>
        </form>

        <aside class="card password-assist-card">
            <span class="panel-label">Password Readiness</span>
            <div class="readiness-score">
                <strong id="passwordScore">0%</strong>
                <div class="status-meter"><span id="passwordScoreBar" style="width: 0%;"></span></div>
            </div>
            <ul class="readiness-list">
                <li id="passwordLengthCheck">At least 8 characters</li>
                <li id="passwordUpperCheck">Includes uppercase letter</li>
                <li id="passwordLowerCheck">Includes lowercase letter</li>
                <li id="passwordNumberCheck">Includes a number</li>
                <li id="passwordMatchCheck">Confirmation matches</li>
            </ul>
        </aside>
    </div>
</main>

<script>
const newPasswordInput = document.getElementById("new");
const confirmPasswordInput = document.getElementById("confirm");
const passwordScore = document.getElementById("passwordScore");
const passwordScoreBar = document.getElementById("passwordScoreBar");
const passwordChecks = [
    { element: document.getElementById("passwordLengthCheck"), test: () => newPasswordInput.value.length >= 8 },
    { element: document.getElementById("passwordUpperCheck"), test: () => /[A-Z]/.test(newPasswordInput.value) },
    { element: document.getElementById("passwordLowerCheck"), test: () => /[a-z]/.test(newPasswordInput.value) },
    { element: document.getElementById("passwordNumberCheck"), test: () => /\d/.test(newPasswordInput.value) },
    { element: document.getElementById("passwordMatchCheck"), test: () => newPasswordInput.value !== "" && newPasswordInput.value === confirmPasswordInput.value },
];

function updatePasswordReadiness() {
    const passed = passwordChecks.filter((check) => check.test()).length;
    const score = Math.round((passed / passwordChecks.length) * 100);
    passwordScore.textContent = `${score}%`;
    passwordScoreBar.style.width = `${score}%`;
    passwordChecks.forEach((check) => check.element.classList.toggle("is-ready", check.test()));
}

[newPasswordInput, confirmPasswordInput].forEach((input) => {
    input.addEventListener("input", updatePasswordReadiness);
});

document.querySelectorAll("[data-toggle-password]").forEach((button) => {
    button.addEventListener("click", () => {
        const input = document.getElementById(button.dataset.togglePassword);
        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";
        button.textContent = isPassword ? "Hide" : "Show";
    });
});

updatePasswordReadiness();
</script>

<?php require __DIR__ . "/footer.php"; ?>
