<?php

require_once __DIR__ . "/init.php";

requireLogin();

$user = currentUser($conn);

if (!$user) {
    signOut();
    redirect("login.php");
}

$message = "";
$values = [
    "firstname" => $user["firstname"] ?? "",
    "middlename" => $user["middlename"] ?? "",
    "lastname" => $user["lastname"] ?? "",
    "email" => $user["email"] ?? "",
    "contact" => $user["contact"] ?? "",
    "address" => $user["address"] ?? "",
    "birthdate" => $user["birthdate"] ?? "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? "");
    }

    $birthDateObj = DateTime::createFromFormat("Y-m-d", $values["birthdate"]);

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif ($values["firstname"] === "" || $values["lastname"] === "" || $values["contact"] === "" || $values["address"] === "") {
        $message = "Please complete all required fields.";
    } elseif (!filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } elseif (!$birthDateObj) {
        $message = "Enter a valid birth date.";
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM tblaccount WHERE email = ? AND id <> ? LIMIT 1");
        mysqli_stmt_bind_param($check, "si", $values["email"], $user["id"]);
        mysqli_stmt_execute($check);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

        if ($existing) {
            $message = "Email is already used by another account.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET firstname = ?, middlename = ?, lastname = ?, email = ?, address = ?, contact = ?, birthdate = ? WHERE id = ?");
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssi",
                $values["firstname"],
                $values["middlename"],
                $values["lastname"],
                $values["email"],
                $values["address"],
                $values["contact"],
                $values["birthdate"],
                $user["id"]
            );
            mysqli_stmt_execute($stmt);

            $updatedUser = currentUser($conn);
            signInUser($updatedUser);

            setFlash("success", "Profile updated successfully.");
            redirect("profile.php");
        }
    }
}

$pageTitle = "Profile";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow"><?php echo e(accountLevelLabel($user["level"])); ?> Account</div>
            <h2>My Profile</h2>
            <p>Welcome, <?php echo e(accountFullName($user)); ?>.</p>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Account Details</h3>
        <p><strong>Account Level:</strong> <span class="badge"><?php echo e(accountLevelLabel($user["level"])); ?></span></p>
        <p><strong>Status:</strong> <span class="status <?php echo e(strtolower($user["status"])); ?>"><?php echo e(ucfirst($user["status"])); ?></span></p>
        <div class="actions">
            <a href="resetpassword.php">Change Password</a>
        </div>
    </div>

    <form method="post" action="profile.php">
        <?php echo csrfField(); ?>
        <h3>Edit Profile</h3>

        <label for="firstname">First Name</label><br>
        <input id="firstname" type="text" name="firstname" value="<?php echo e($values["firstname"]); ?>" required><br><br>

        <label for="middlename">Middle Name</label><br>
        <input id="middlename" type="text" name="middlename" value="<?php echo e($values["middlename"]); ?>"><br><br>

        <label for="lastname">Last Name</label><br>
        <input id="lastname" type="text" name="lastname" value="<?php echo e($values["lastname"]); ?>" required><br><br>

        <label for="email">Email Address</label><br>
        <input id="email" type="email" name="email" value="<?php echo e($values["email"]); ?>" required><br><br>

        <label for="contact">Contact Number</label><br>
        <input id="contact" type="text" name="contact" value="<?php echo e($values["contact"]); ?>" required><br><br>

        <label for="address">Address</label><br>
        <textarea id="address" name="address" required><?php echo e($values["address"]); ?></textarea><br><br>

        <label for="birthdate">Birth Date</label><br>
        <input id="birthdate" type="date" name="birthdate" value="<?php echo e($values["birthdate"]); ?>" required><br><br>

        <button type="submit">Save Profile</button>
    </form>
</main>

<?php require __DIR__ . "/footer.php"; ?>
