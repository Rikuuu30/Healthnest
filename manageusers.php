<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$message = "";
$userStatsResult = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN status <> 'active' THEN 1 ELSE 0 END) AS inactive_users,
        SUM(CASE WHEN level = 'seller' THEN 1 ELSE 0 END) AS seller_users,
        SUM(CASE WHEN level = 'buyer' THEN 1 ELSE 0 END) AS buyer_users
    FROM tblaccount
");
$userStats = mysqli_fetch_assoc($userStatsResult);
$totalUsers = (int) ($userStats["total_users"] ?? 0);
$activeUsers = (int) ($userStats["active_users"] ?? 0);
$inactiveUsers = (int) ($userStats["inactive_users"] ?? 0);
$sellerUsers = (int) ($userStats["seller_users"] ?? 0);
$buyerUsers = (int) ($userStats["buyer_users"] ?? 0);
$editUserId = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT);
$disableUserId = filter_input(INPUT_GET, "delete", FILTER_VALIDATE_INT);

if ($disableUserId) {
    if ($disableUserId === sessionUserId()) {
        setFlash("error", "You cannot disable your own account while logged in.");
    } else {
        $status = "inactive";
        $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $status, $disableUserId);
        mysqli_stmt_execute($stmt);
        logAudit($conn, sessionUserId(), "Disable User", "tblaccount", $disableUserId, "Set account inactive");
        setFlash("success", "User account set to inactive.");
    }

    redirect("manageusers.php");
}

$editMode = false;
$editUser = [
    "id" => "",
    "firstname" => "",
    "middlename" => "",
    "lastname" => "",
    "email" => "",
    "address" => "",
    "contact" => "",
    "birthdate" => "",
    "level" => "buyer",
    "status" => "active",
];

if ($editUserId) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM tblaccount WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $editUserId);
    mysqli_stmt_execute($stmt);
    $foundUser = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($foundUser) {
        $editUser = array_merge($editUser, $foundUser);
        $editMode = true;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedUserId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
    $values = [
        "firstname" => trim($_POST["firstname"] ?? ""),
        "middlename" => trim($_POST["middlename"] ?? ""),
        "lastname" => trim($_POST["lastname"] ?? ""),
        "email" => trim($_POST["email"] ?? ""),
        "address" => trim($_POST["address"] ?? ""),
        "contact" => trim($_POST["contact"] ?? ""),
        "birthdate" => trim($_POST["birthdate"] ?? ""),
        "level" => strtolower(trim($_POST["level"] ?? "")),
        "status" => strtolower(trim($_POST["status"] ?? "")),
    ];
    $password = $_POST["password"] ?? "";
    $birthDateObj = $values["birthdate"] !== "" ? DateTime::createFromFormat("Y-m-d", $values["birthdate"]) : null;

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif ($values["firstname"] === "" || $values["lastname"] === "" || $values["email"] === "") {
        $message = "Please fill in first name, last name, and email.";
    } elseif (!filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } elseif ($values["birthdate"] !== "" && !$birthDateObj) {
        $message = "Enter a valid birth date.";
    } elseif (!in_array($values["level"], ["buyer", "seller"], true)) {
        $message = "Please select a valid account level.";
    } elseif (!in_array($values["status"], ["active", "inactive", "disabled"], true)) {
        $message = "Please select a valid account status.";
    } elseif (!$postedUserId && strlen($password) < 8) {
        $message = "New users need a password of at least 8 characters.";
    } elseif ($postedUserId && $password !== "" && strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM tblaccount WHERE email = ? AND id <> ? LIMIT 1");
        $currentId = $postedUserId ?: 0;
        mysqli_stmt_bind_param($check, "si", $values["email"], $currentId);
        mysqli_stmt_execute($check);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

        if ($existing) {
            $message = "Email is already used by another account.";
        } elseif ($postedUserId) {
            if ($password !== "") {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET firstname = ?, middlename = ?, lastname = ?, email = ?, password = ?, address = ?, contact = ?, birthdate = ?, level = ?, status = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssssssssssi", $values["firstname"], $values["middlename"], $values["lastname"], $values["email"], $hash, $values["address"], $values["contact"], $values["birthdate"], $values["level"], $values["status"], $postedUserId);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET firstname = ?, middlename = ?, lastname = ?, email = ?, address = ?, contact = ?, birthdate = ?, level = ?, status = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssssssssi", $values["firstname"], $values["middlename"], $values["lastname"], $values["email"], $values["address"], $values["contact"], $values["birthdate"], $values["level"], $values["status"], $postedUserId);
            }
            mysqli_stmt_execute($stmt);

            logAudit($conn, sessionUserId(), "Edit User", "tblaccount", $postedUserId, "Updated account: " . $values["email"]);

            if ($postedUserId === sessionUserId()) {
                $updatedUser = currentUser($conn);
                signInUser($updatedUser);
            }

            setFlash("success", "User account updated.");
            redirect("manageusers.php");
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $image = "";
            $stmt = mysqli_prepare($conn, "INSERT INTO tblaccount (firstname, middlename, lastname, email, password, address, contact, birthdate, level, status, image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
            mysqli_stmt_bind_param($stmt, "sssssssssss", $values["firstname"], $values["middlename"], $values["lastname"], $values["email"], $hash, $values["address"], $values["contact"], $values["birthdate"], $values["level"], $values["status"], $image);
            mysqli_stmt_execute($stmt);

            $newUser = ensureAccountIdByEmail($conn, $values["email"]);
            $newUserId = (int) ($newUser["id"] ?? mysqli_insert_id($conn));
            logAudit($conn, sessionUserId(), "Add User", "tblaccount", $newUserId, "Added account: " . $values["email"]);
            setFlash("success", "User account created.");
            redirect("manageusers.php");
        }
    }

    $editUser = array_merge($editUser, $values);
    if ($postedUserId) {
        $editUser["id"] = $postedUserId;
        $editMode = true;
    }
}

$usersResult = mysqli_query($conn, "SELECT * FROM tblaccount ORDER BY id DESC");

$pageTitle = "Manage Users";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Manage Users</h2>
            <p>Create buyer or seller accounts, update access, and keep account status easy to review.</p>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="card insight-card">
            <span class="panel-label">Total Accounts</span>
            <strong><?php echo $totalUsers; ?></strong>
            <p><?php echo $buyerUsers; ?> buyers · <?php echo $sellerUsers; ?> sellers</p>
        </div>
        <div class="card insight-card">
            <span class="panel-label">Active</span>
            <strong><?php echo $activeUsers; ?></strong>
            <p>Accounts currently allowed to use the system.</p>
        </div>
        <div class="card insight-card warning">
            <span class="panel-label">Inactive / Disabled</span>
            <strong><?php echo $inactiveUsers; ?></strong>
            <p>Accounts requiring review or reactivation.</p>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <form class="seller-form" method="post" action="manageusers.php<?php echo $editMode ? "?edit=" . (int) $editUser["id"] : ""; ?>">
        <?php echo csrfField(); ?>
        <h3><?php echo $editMode ? "Edit User" : "Add User"; ?></h3>

        <?php if ($editMode): ?>
            <input type="hidden" name="user_id" value="<?php echo (int) $editUser["id"]; ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label for="firstname">First Name</label>
                <input id="firstname" type="text" name="firstname" value="<?php echo e($editUser["firstname"]); ?>" required>
            </div>

            <div>
                <label for="middlename">Middle Name</label>
                <input id="middlename" type="text" name="middlename" value="<?php echo e($editUser["middlename"]); ?>">
            </div>

            <div>
                <label for="lastname">Last Name</label>
                <input id="lastname" type="text" name="lastname" value="<?php echo e($editUser["lastname"]); ?>" required>
            </div>

            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?php echo e($editUser["email"]); ?>" required>
            </div>

            <div>
                <label for="contact">Contact</label>
                <input id="contact" type="text" name="contact" value="<?php echo e($editUser["contact"]); ?>">
            </div>

            <div>
                <label for="birthdate">Birth Date</label>
                <input id="birthdate" type="date" name="birthdate" value="<?php echo e($editUser["birthdate"]); ?>">
            </div>

            <div>
                <label for="level">Account Level</label>
                <select id="level" name="level" required>
                    <option value="buyer" <?php echo accountLevelValue($editUser["level"]) === "buyer" ? "selected" : ""; ?>>Buyer</option>
                    <option value="seller" <?php echo accountLevelValue($editUser["level"]) === "seller" ? "selected" : ""; ?>>Seller</option>
                </select>
            </div>

            <div>
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="active" <?php echo $editUser["status"] === "active" ? "selected" : ""; ?>>Active</option>
                    <option value="inactive" <?php echo $editUser["status"] === "inactive" ? "selected" : ""; ?>>Inactive</option>
                    <option value="disabled" <?php echo $editUser["status"] === "disabled" ? "selected" : ""; ?>>Disabled</option>
                </select>
            </div>

            <div class="full">
                <label for="address">Address</label>
                <textarea id="address" name="address"><?php echo e($editUser["address"]); ?></textarea>
            </div>

            <div class="full">
                <label for="password"><?php echo $editMode ? "New Password (optional)" : "Password"; ?></label>
                <input id="password" type="password" name="password" <?php echo $editMode ? "" : "required"; ?> minlength="8">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit">Save User</button>
            <?php if ($editMode): ?>
                <a href="manageusers.php">Cancel Edit</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-card">
        <h3>Account List</h3>
        <div class="table-wrap">
            <table border="1" cellpadding="8" cellspacing="0">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php if ($usersResult && mysqli_num_rows($usersResult) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($usersResult)): ?>
                        <tr>
                            <td><?php echo (int) $row["id"]; ?></td>
                            <td><strong><?php echo e(accountFullName($row)); ?></strong></td>
                            <td><?php echo e($row["email"]); ?></td>
                            <td><span class="badge"><?php echo e(accountLevelLabel($row["level"])); ?></span></td>
                            <td><span class="status <?php echo e(strtolower($row["status"])); ?>"><?php echo e(ucfirst($row["status"])); ?></span></td>
                            <td>
                                <a href="manageusers.php?edit=<?php echo (int) $row["id"]; ?>">Edit</a>
                                <?php if ((int) $row["id"] !== sessionUserId()): ?>
                                    <a href="manageusers.php?delete=<?php echo (int) $row["id"]; ?>">Disable</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No users found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
