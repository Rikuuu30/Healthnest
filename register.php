<?php

require_once __DIR__ . "/init.php";

if (isLoggedIn()) {
    redirect(dashboardUrl());
}

$message = "";
$values = [
    "firstname" => "",
    "middlename" => "",
    "lastname" => "",
    "email" => "",
    "address" => "",
    "contact" => "",
    "birthdate" => "",
    "level" => "buyer",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? "");
    }

    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm"] ?? "";
    $birthDateObj = DateTime::createFromFormat("Y-m-d", $values["birthdate"]);
    $age = $birthDateObj ? $birthDateObj->diff(new DateTime("today"))->y : 0;

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif ($values["firstname"] === "" || $values["lastname"] === "" || $values["address"] === "" || $values["contact"] === "") {
        $message = "Please complete all required fields.";
    } elseif (!filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } elseif (!in_array($values["level"], ["buyer", "seller"], true)) {
        $message = "Please select whether you are registering as a buyer or seller.";
    } elseif (!$birthDateObj || $age < 18) {
        $message = "You must be at least 18 years old to register.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm) {
        $message = "Password and confirm password do not match.";
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM tblaccount WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "s", $values["email"]);
        mysqli_stmt_execute($check);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

        if ($existing) {
            $message = "Email is already registered.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $level = $values["level"];
            $status = "active";
            $image = "";

            $stmt = mysqli_prepare($conn, "INSERT INTO tblaccount (firstname, middlename, lastname, email, password, address, contact, birthdate, level, status, image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssss",
                $values["firstname"],
                $values["middlename"],
                $values["lastname"],
                $values["email"],
                $passwordHash,
                $values["address"],
                $values["contact"],
                $values["birthdate"],
                $level,
                $status,
                $image
            );
            mysqli_stmt_execute($stmt);
            ensureAccountIdByEmail($conn, $values["email"]);

            setFlash("success", "Registration successful. You can now log in.");
            redirect("login.php");
        }
    }
}

$pageTitle = "Register";
require __DIR__ . "/header.php";
?>

<main class="auth-shell auth-shell-register">
    <div class="auth-card auth-card-register">
        <div class="auth-card-topline">
            <a class="home-button" href="index.php" aria-label="Return to the HealthNest home page">&larr; Home</a>
            <img class="auth-logo" src="assets/healthnest-logo.png" alt="HealthNest">
        </div>

        <div class="auth-heading">
            <h1>Register</h1>
            <p>Complete the required details to create your account.</p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="error"><?php echo e($message); ?></div>
        <?php endif; ?>

        <form method="post" action="register.php" autocomplete="off" data-form-type="other">
            <?php echo csrfField(); ?>

            <div class="form-grid">
                <div>
                    <label for="firstname">First Name</label>
                    <input id="firstname" type="text" name="firstname" value="<?php echo e($values["firstname"]); ?>" placeholder="Enter first name" autocomplete="given-name" required>
                </div>

                <div>
                    <label for="middlename">Middle Name</label>
                    <input id="middlename" type="text" name="middlename" value="<?php echo e($values["middlename"]); ?>" placeholder="Enter middle name" autocomplete="additional-name">
                </div>

                <div>
                    <label for="lastname">Last Name</label>
                    <input id="lastname" type="text" name="lastname" value="<?php echo e($values["lastname"]); ?>" placeholder="Enter last name" autocomplete="family-name" required>
                </div>

                <div>
                    <label for="email">Email Address</label>
                    <input id="email" type="email" name="email" value="<?php echo e($values["email"]); ?>" placeholder="name@example.com" autocomplete="email" required>
                </div>

                <div>
                    <label for="contact">Contact Number</label>
                    <input id="contact" type="tel" name="contact" value="<?php echo e($values["contact"]); ?>" placeholder="Enter contact number" autocomplete="tel" required>
                </div>

                <div>
                    <label for="birthdate">Birth Date</label>
                    <input id="birthdate" type="date" name="birthdate" value="<?php echo e($values["birthdate"]); ?>" max="<?php echo date("Y-m-d", strtotime("-18 years")); ?>" autocomplete="bday" aria-describedby="age-status" required>
                    <span id="age-status" class="field-hint" aria-live="polite">You must be at least 18 years old.</span>
                </div>

                <div class="full">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" placeholder="Enter complete address" autocomplete="street-address" required><?php echo e($values["address"]); ?></textarea>
                </div>

                <div>
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input id="password" type="password" name="password" placeholder="At least 8 characters" minlength="8" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" data-form-type="other" data-lpignore="true" data-1p-ignore required>
                        <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                </div>

                <div>
                    <label for="confirm">Confirm Password</label>
                    <div class="password-field">
                        <input id="confirm" type="password" name="confirm" placeholder="Re-enter password" minlength="8" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" data-form-type="other" data-lpignore="true" data-1p-ignore required>
                        <button class="password-toggle" type="button" data-password-toggle="confirm" aria-label="Show confirm password" aria-pressed="false">Show</button>
                    </div>
                </div>

                <div class="full">
                    <label id="level-label">Register As</label>
                    <div class="role-select" data-role-select>
                        <select id="level" name="level" aria-labelledby="level-label" required>
                            <option value="buyer" <?php echo $values["level"] === "buyer" ? "selected" : ""; ?>>Buyer</option>
                            <option value="seller" <?php echo $values["level"] === "seller" ? "selected" : ""; ?>>Seller</option>
                        </select>

                        <button class="role-select-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="level-label role-select-value">
                            <strong id="role-select-value"><?php echo $values["level"] === "seller" ? "Seller" : "Buyer"; ?></strong>
                            <span class="role-select-chevron" aria-hidden="true"></span>
                        </button>

                        <div class="role-select-menu" role="listbox" aria-labelledby="level-label" hidden>
                            <button type="button" class="role-select-option" role="option" data-role-value="buyer" aria-selected="<?php echo $values["level"] === "buyer" ? "true" : "false"; ?>">Buyer</button>
                            <button type="button" class="role-select-option" role="option" data-role-value="seller" aria-selected="<?php echo $values["level"] === "seller" ? "true" : "false"; ?>">Seller</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit">Create Account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="login.php">Login</a></p>
    </div>
</main>

<script src="assets/auth.js"></script>

<?php require __DIR__ . "/footer.php"; ?>
