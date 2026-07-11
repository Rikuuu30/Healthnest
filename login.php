<?php

require_once __DIR__ . "/init.php";

if (isLoggedIn()) {
    redirect(dashboardUrl());
}

$message = "";
$email = $_COOKIE["remember_email"] ?? ($_COOKIE["remember"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
        $message = "Enter a valid email address and password.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM tblaccount WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (!$user || !passwordMatches($password, $user["password"])) {
            $message = "Invalid email or password.";
        } elseif (!isAccountActive($user)) {
            $message = "This account is disabled. Please contact the seller.";
        } else {
            $user = ensureAccountHasId($conn, $user);
            signInUser($user);

            if (!empty($_POST["remember"])) {
                setRememberEmail($email);
            } else {
                clearRememberEmail();
            }

            redirect(dashboardUrl());
        }
    }
}

$pageTitle = "Login";
require __DIR__ . "/header.php";
?>

<main class="auth-shell auth-shell-login">
    <div class="auth-card auth-card-login">
        <div class="auth-card-topline">
            <a class="home-button" href="index.php" aria-label="Return to the HealthNest home page">&larr; Home</a>
            <img class="auth-logo" src="assets/healthnest-logo.png" alt="HealthNest">
        </div>

        <div class="auth-heading">
            <h1>Log-In Form</h1>
            <p>Enter your registered email and password.</p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="error"><?php echo e($message); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <?php echo csrfField(); ?>

            <label for="email">Email Address</label>
            <input id="email" type="email" name="email" value="<?php echo e($email); ?>" placeholder="Enter Email" autocomplete="email" required>

            <label for="password">Password</label>
            <div class="password-field">
                <input id="password" type="password" name="password" placeholder="Enter Password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
            </div>

            <label class="remember-row">
                <input type="checkbox" name="remember" value="1" <?php echo $email !== "" ? "checked" : ""; ?>>
                Remember my email
            </label>

            <button type="submit">Login</button>
        </form>

        <p class="auth-switch">Don't have an account yet? <a href="register.php">Register</a></p>
    </div>
</main>

<script src="assets/auth.js"></script>

<?php require __DIR__ . "/footer.php"; ?>
