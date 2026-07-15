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

$profileImage = str_replace("\\", "/", trim($user["image"] ?? ""));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? "");
    }

    $birthDateObj = DateTime::createFromFormat("!Y-m-d", $values["birthdate"]);
    $birthDateErrors = DateTime::getLastErrors();
    $hasValidBirthDate = $birthDateObj
        && ($birthDateErrors === false || ($birthDateErrors["warning_count"] === 0 && $birthDateErrors["error_count"] === 0))
        && $birthDateObj->format("Y-m-d") === $values["birthdate"];
    $adultBirthDateCutoff = (new DateTime("today"))->modify("-18 years");
    $uploadedImage = $_FILES["profile_image"] ?? null;
    $uploadedImageExtension = "";

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif ($values["firstname"] === "" || $values["lastname"] === "" || $values["contact"] === "" || $values["address"] === "") {
        $message = "Please complete all required fields.";
    } elseif (!filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } elseif (!$hasValidBirthDate || $birthDateObj > $adultBirthDateCutoff) {
        $message = "You must be at least 18 years old.";
    } elseif ($uploadedImage && (!isset($uploadedImage["error"]) || is_array($uploadedImage["error"]))) {
        $message = "The selected profile photo is not a valid upload.";
    } elseif ($uploadedImage && (int) $uploadedImage["error"] !== UPLOAD_ERR_NO_FILE) {
        if ((int) $uploadedImage["error"] !== UPLOAD_ERR_OK) {
            $message = "The profile photo could not be uploaded. Please try again.";
        } elseif (!isset($uploadedImage["size"], $uploadedImage["tmp_name"]) || is_array($uploadedImage["size"]) || is_array($uploadedImage["tmp_name"])) {
            $message = "The selected profile photo is not a valid upload.";
        } elseif ((int) $uploadedImage["size"] <= 0 || (int) $uploadedImage["size"] > 3 * 1024 * 1024) {
            $message = "The profile photo must be 3 MB or smaller.";
        } elseif (!is_uploaded_file($uploadedImage["tmp_name"])) {
            $message = "The selected profile photo is not a valid upload.";
        } else {
            $imageInfo = @getimagesize($uploadedImage["tmp_name"]);
            $allowedImageTypes = [
                "image/jpeg" => [IMAGETYPE_JPEG, "jpg"],
                "image/png" => [IMAGETYPE_PNG, "png"],
            ];
            $imageMime = class_exists("finfo")
                ? (new finfo(FILEINFO_MIME_TYPE))->file($uploadedImage["tmp_name"])
                : ($imageInfo["mime"] ?? "");

            if (
                !$imageInfo
                || !isset($allowedImageTypes[$imageMime])
                || $imageInfo[2] !== $allowedImageTypes[$imageMime][0]
            ) {
                $message = "Choose a JPG or PNG image for your profile photo.";
            } elseif ($imageInfo[0] <= 0 || $imageInfo[1] <= 0 || $imageInfo[0] > 6000 || $imageInfo[1] > 6000 || ($imageInfo[0] * $imageInfo[1]) > 25000000) {
                $message = "The profile photo dimensions are too large.";
            } else {
                $uploadedImageExtension = $allowedImageTypes[$imageMime][1];
            }
        }
    }

    if ($message === "") {
        $check = mysqli_prepare($conn, "SELECT id FROM tblaccount WHERE email = ? AND id <> ? LIMIT 1");
        mysqli_stmt_bind_param($check, "si", $values["email"], $user["id"]);
        mysqli_stmt_execute($check);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

        if ($existing) {
            $message = "Email is already used by another account.";
        } else {
            $newProfileImage = $profileImage;

            if ($uploadedImageExtension !== "") {
                $uploadDirectory = __DIR__ . "/uploads/profiles";

                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
                    $message = "The profile photo folder could not be created.";
                } else {
                    $imageFilename = "user-" . (int) $user["id"] . "-" . date("YmdHis") . "-" . bin2hex(random_bytes(5)) . "." . $uploadedImageExtension;
                    $destination = $uploadDirectory . "/" . $imageFilename;

                    if (!move_uploaded_file($uploadedImage["tmp_name"], $destination)) {
                        $message = "The profile photo could not be saved. Please try again.";
                    } else {
                        $newProfileImage = "uploads/profiles/" . $imageFilename;
                    }
                }
            }

            if ($message === "") {
                $stmt = mysqli_prepare($conn, "UPDATE tblaccount SET firstname = ?, middlename = ?, lastname = ?, email = ?, address = ?, contact = ?, birthdate = ?, image = ? WHERE id = ?");
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssi",
                    $values["firstname"],
                    $values["middlename"],
                    $values["lastname"],
                    $values["email"],
                    $values["address"],
                    $values["contact"],
                    $values["birthdate"],
                    $newProfileImage,
                    $user["id"]
                );

                if (!mysqli_stmt_execute($stmt)) {
                    if ($newProfileImage !== $profileImage && is_file(__DIR__ . "/" . $newProfileImage)) {
                        @unlink(__DIR__ . "/" . $newProfileImage);
                    }
                    $message = "Your profile could not be updated. Please try again.";
                } else {
                    if (
                        $newProfileImage !== $profileImage
                        && preg_match('#^uploads/profiles/[A-Za-z0-9._-]+$#', $profileImage)
                        && is_file(__DIR__ . "/" . $profileImage)
                    ) {
                        @unlink(__DIR__ . "/" . $profileImage);
                    }

                    $updatedUser = currentUser($conn);
                    signInUser($updatedUser);

                    setFlash("success", "Profile updated successfully.");
                    redirect("profile.php");
                }
            }
        }
    }
}

$isSellerProfile = isAdmin();
$roleLabel = accountLevelLabel($user["level"]);
$fullName = accountFullName($user);
$statusLabel = ucfirst(strtolower($user["status"] ?? "active"));
$statusClass = preg_replace('/[^a-z0-9_-]/', '', strtolower($user["status"] ?? "active"));
$nameParts = array_values(array_filter(preg_split('/\s+/', trim($fullName))));
$profileInitials = "U";
$birthDateText = $values["birthdate"] !== "" ? date("M j, Y", strtotime($values["birthdate"])) : "Not set";
$memberSinceText = !empty($user["created_at"]) ? date("M j, Y", strtotime($user["created_at"])) : "Not recorded";
$contactText = $values["contact"] !== "" ? $values["contact"] : "No contact";
$sellerStats = [
    "products" => 0,
    "low_stock" => 0,
    "audit_events" => 0,
    "last_event" => "No activity yet",
];
$buyerStats = [
    "cart_items" => 0,
    "cart_total" => 0,
    "orders" => 0,
    "active_orders" => 0,
    "total_spent" => 0,
];

if ($nameParts) {
    $profileInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $profileInitials .= mb_strtoupper(mb_substr($nameParts[count($nameParts) - 1], 0, 1));
    }
}

$profileImageUrl = "";
if (
    preg_match('#^uploads/profiles/[A-Za-z0-9._-]+$#', $profileImage)
    && is_file(__DIR__ . "/" . $profileImage)
) {
    $profileImageUrl = $profileImage;
}

if ($isSellerProfile) {
    $sellerProductResult = mysqli_query($conn, "
        SELECT
            COUNT(*) AS products,
            SUM(CASE WHEN stock_quantity <= 10 THEN 1 ELSE 0 END) AS low_stock
        FROM products
    ");
    $sellerProductStats = $sellerProductResult ? mysqli_fetch_assoc($sellerProductResult) : [];
    $sellerStats["products"] = (int) ($sellerProductStats["products"] ?? 0);
    $sellerStats["low_stock"] = (int) ($sellerProductStats["low_stock"] ?? 0);

    $sellerAuditResult = mysqli_query($conn, "
        SELECT COUNT(*) AS audit_events, MAX(created_at) AS last_event
        FROM audit_logs
        WHERE user_id = " . (int) $user["id"] . "
    ");
    $sellerAuditStats = $sellerAuditResult ? mysqli_fetch_assoc($sellerAuditResult) : [];
    $sellerStats["audit_events"] = (int) ($sellerAuditStats["audit_events"] ?? 0);
    if (!empty($sellerAuditStats["last_event"])) {
        $sellerStats["last_event"] = date("M j, Y", strtotime($sellerAuditStats["last_event"]));
    }
} else {
    $buyerStats["cart_items"] = cartCount($conn, (int) $user["id"]);
    $buyerStats["cart_total"] = cartTotal($conn, (int) $user["id"]);

    $buyerOrderResult = mysqli_query($conn, "
        SELECT
            COUNT(*) AS orders,
            COALESCE(SUM(total_amount), 0) AS total_spent,
            SUM(CASE WHEN LOWER(COALESCE(status, 'paid')) NOT IN ('delivered', 'cancelled') THEN 1 ELSE 0 END) AS active_orders
        FROM orders
        WHERE user_id = " . (int) $user["id"] . "
    ");
    $buyerOrderStats = $buyerOrderResult ? mysqli_fetch_assoc($buyerOrderResult) : [];
    $buyerStats["orders"] = (int) ($buyerOrderStats["orders"] ?? 0);
    $buyerStats["active_orders"] = (int) ($buyerOrderStats["active_orders"] ?? 0);
    $buyerStats["total_spent"] = (float) ($buyerOrderStats["total_spent"] ?? 0);
}

$pageTitle = "My Profile";
require __DIR__ . "/header.php";
?>

<main class="profile-page">
    <header class="profile-page-header">
        <span class="profile-account-label"><?php echo e($roleLabel); ?> Account</span>
        <h1>My Profile</h1>
        <p>Update your photo and personal details. This won't affect anything else in your account.</p>
    </header>

    <?php if ($message !== ""): ?>
        <div class="profile-message error" role="alert"><?php echo e($message); ?></div>
    <?php endif; ?>

    <form id="profileForm" class="profile-form" method="post" action="profile.php" enctype="multipart/form-data">
        <?php echo csrfField(); ?>

        <div class="seller-profile-sidebar">
            <section class="profile-card profile-overview-card" aria-labelledby="profile-overview-title">
                <h2 id="profile-overview-title" class="visually-hidden">Profile overview</h2>

                <div class="profile-avatar-wrap">
                    <span id="profileAvatar" class="profile-avatar" aria-hidden="true">
                        <?php if ($profileImageUrl !== ""): ?>
                            <img id="profileAvatarImage" src="<?php echo e($profileImageUrl); ?>" alt="">
                            <span id="profileAvatarInitials" hidden><?php echo e($profileInitials); ?></span>
                        <?php else: ?>
                            <img id="profileAvatarImage" src="" alt="" hidden>
                            <span id="profileAvatarInitials"><?php echo e($profileInitials); ?></span>
                        <?php endif; ?>
                    </span>

                    <label class="profile-photo-control" for="profile_image" title="Choose a new profile photo">
                        <span aria-hidden="true">&#128247;</span>
                        <span class="visually-hidden">Choose a new profile photo</span>
                    </label>
                    <input class="visually-hidden" id="profile_image" type="file" name="profile_image" accept="image/jpeg,image/png" aria-describedby="profile-photo-help">
                </div>

                <div class="profile-identity">
                    <h2><?php echo e($fullName); ?></h2>
                    <span class="profile-role-badge"><?php echo e($roleLabel); ?></span>
                </div>

                <p id="profile-photo-help" class="profile-photo-help">JPG or PNG, up to 3 MB. Square photos look best.</p>

                <div class="profile-status-row">
                    <span>Status</span>
                    <strong class="profile-status <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></strong>
                </div>

                <?php if ($isSellerProfile): ?>
                    <div class="seller-profile-facts">
                        <div>
                            <span>Email</span>
                            <strong><?php echo e($values["email"]); ?></strong>
                        </div>
                        <div>
                            <span>Contact</span>
                            <strong><?php echo e($contactText); ?></strong>
                        </div>
                        <div>
                            <span>Birth Date</span>
                            <strong><?php echo e($birthDateText); ?></strong>
                        </div>
                        <div>
                            <span>Member Since</span>
                            <strong><?php echo e($memberSinceText); ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="seller-profile-facts buyer-profile-facts">
                        <div>
                            <span>Email</span>
                            <strong><?php echo e($values["email"]); ?></strong>
                        </div>
                        <div>
                            <span>Contact</span>
                            <strong><?php echo e($contactText); ?></strong>
                        </div>
                        <div>
                            <span>Member Since</span>
                            <strong><?php echo e($memberSinceText); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="profile-completion-card">
                    <div>
                        <span>Profile Readiness</span>
                        <strong id="profileCompletionScore">0%</strong>
                    </div>
                    <div class="status-meter"><span id="profileCompletionBar" style="width: 0%;"></span></div>
                    <p id="profileCompletionHint">Complete the required details to keep the account ready.</p>
                </div>
            </section>

            <?php if ($isSellerProfile): ?>
                <section class="profile-card seller-workspace-card" aria-labelledby="seller-workspace-title">
                    <div class="profile-card-heading">
                        <h2 id="seller-workspace-title">Seller Snapshot</h2>
                        <p>Quick context for the account you are using now.</p>
                    </div>
                    <div class="seller-workspace-stats">
                        <div>
                            <span>Products</span>
                            <strong><?php echo $sellerStats["products"]; ?></strong>
                        </div>
                        <div>
                            <span>Low Stock</span>
                            <strong><?php echo $sellerStats["low_stock"]; ?></strong>
                        </div>
                        <div>
                            <span>Your Audit Events</span>
                            <strong><?php echo $sellerStats["audit_events"]; ?></strong>
                        </div>
                        <div>
                            <span>Last Activity</span>
                            <strong><?php echo e($sellerStats["last_event"]); ?></strong>
                        </div>
                    </div>
                    <div class="seller-profile-actions">
                        <a href="inventory.php">Inventory</a>
                        <a href="auditlog.php">Audit Log</a>
                        <a href="seller_changepassword.php">Password</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="profile-card seller-workspace-card buyer-workspace-card" aria-labelledby="buyer-workspace-title">
                    <div class="profile-card-heading">
                        <h2 id="buyer-workspace-title">Buyer Snapshot</h2>
                        <p>Quick context for shopping, checkout, and tracking.</p>
                    </div>
                    <div class="seller-workspace-stats">
                        <div>
                            <span>Cart Items</span>
                            <strong><?php echo $buyerStats["cart_items"]; ?></strong>
                        </div>
                        <div>
                            <span>Cart Total</span>
                            <strong><?php echo formatPrice($buyerStats["cart_total"]); ?></strong>
                        </div>
                        <div>
                            <span>Orders</span>
                            <strong><?php echo $buyerStats["orders"]; ?></strong>
                        </div>
                        <div>
                            <span>Active Orders</span>
                            <strong><?php echo $buyerStats["active_orders"]; ?></strong>
                        </div>
                        <div>
                            <span>Total Spent</span>
                            <strong><?php echo formatPrice($buyerStats["total_spent"]); ?></strong>
                        </div>
                    </div>
                    <div class="seller-profile-actions">
                        <a href="products.php">Products</a>
                        <a href="cart.php">Cart</a>
                        <a href="buyer_orders.php">Orders</a>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <section class="profile-card profile-information-card buyer-profile-information-card" aria-labelledby="personal-information-title">
            <div class="profile-card-heading">
                <h2 id="personal-information-title">Personal Information</h2>
                <p><?php echo $isSellerProfile
                    ? "Keep your seller account contact details clean and ready for account recovery, shipping, and support records."
                    : "Used for your orders, delivery, and account recovery."; ?></p>
            </div>

            <?php if ($isSellerProfile): ?>
                <div class="seller-form-toolbar">
                    <div>
                        <span>Editing</span>
                        <strong><?php echo e($fullName); ?></strong>
                    </div>
                    <button type="button" id="copySellerEmail">Copy Email</button>
                </div>
            <?php endif; ?>

            <div class="profile-fields">
                <div class="profile-field">
                    <label for="firstname">First Name</label>
                    <input id="firstname" type="text" name="firstname" value="<?php echo e($values["firstname"]); ?>" autocomplete="given-name" required>
                </div>

                <div class="profile-field">
                    <label for="middlename">Middle Name</label>
                    <input id="middlename" type="text" name="middlename" value="<?php echo e($values["middlename"]); ?>" autocomplete="additional-name">
                </div>

                <div class="profile-field">
                    <label for="lastname">Last Name</label>
                    <input id="lastname" type="text" name="lastname" value="<?php echo e($values["lastname"]); ?>" autocomplete="family-name" required>
                </div>

                <div class="profile-field">
                    <label for="birthdate">Birth Date</label>
                    <input id="birthdate" type="date" name="birthdate" value="<?php echo e($values["birthdate"]); ?>" max="<?php echo date("Y-m-d", strtotime("-18 years")); ?>" autocomplete="bday" required>
                </div>

                <div class="profile-field">
                    <label for="email">Email Address</label>
                    <input id="email" type="email" name="email" value="<?php echo e($values["email"]); ?>" autocomplete="email" required>
                </div>

                <div class="profile-field">
                    <label for="contact">Contact Number</label>
                    <input id="contact" type="tel" name="contact" value="<?php echo e($values["contact"]); ?>" autocomplete="tel" required>
                </div>

                <div class="profile-field profile-field-wide">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" autocomplete="street-address" required><?php echo e($values["address"]); ?></textarea>
                </div>
            </div>

            <div class="profile-form-actions">
                <button class="profile-button profile-button-secondary" type="reset">Discard</button>
                <button class="profile-button profile-button-primary" type="submit">Save Profile</button>
            </div>
        </section>

    <section class="profile-card profile-security-card buyer-profile-security-card" aria-labelledby="profile-security-title">
        <div class="profile-card-heading">
            <h2 id="profile-security-title">Security</h2>
            <p>Manage your password separately from your profile details.</p>
        </div>
        <div class="profile-security-row">
            <p>Your password was last changed on your account creation date. We recommend updating it periodically.</p>
            <a class="profile-button profile-button-secondary" href="<?php echo $isSellerProfile ? "seller_changepassword.php" : "resetpassword.php"; ?>">Change Password</a>
        </div>
    </section>
    </form>
</main>

<script>
(() => {
    const form = document.getElementById("profileForm");
    const fileInput = document.getElementById("profile_image");
    const avatarImage = document.getElementById("profileAvatarImage");
    const avatarInitials = document.getElementById("profileAvatarInitials");
    const completionScore = document.getElementById("profileCompletionScore");
    const completionBar = document.getElementById("profileCompletionBar");
    const completionHint = document.getElementById("profileCompletionHint");
    const copySellerEmail = document.getElementById("copySellerEmail");
    const emailInput = document.getElementById("email");
    const completionFields = Array.from(form.querySelectorAll("input[required], textarea[required]"));
    const originalSource = avatarImage.getAttribute("src");
    const originallyHidden = avatarImage.hidden;
    let previewUrl = "";

    fileInput.addEventListener("change", () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            return;
        }

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        previewUrl = URL.createObjectURL(file);
        avatarImage.src = previewUrl;
        avatarImage.hidden = false;
        avatarInitials.hidden = true;
    });

    form.addEventListener("reset", () => {
        window.setTimeout(() => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = "";
            }

            avatarImage.src = originalSource;
            avatarImage.hidden = originallyHidden;
            avatarInitials.hidden = !originallyHidden;
            updateProfileCompletion();
        }, 0);
    });

    function updateProfileCompletion() {
        const completed = completionFields.filter((field) => field.value.trim() !== "").length;
        const score = completionFields.length ? Math.round((completed / completionFields.length) * 100) : 100;

        completionScore.textContent = `${score}%`;
        completionBar.style.width = `${score}%`;

        if (score === 100) {
            completionHint.textContent = "Your profile has the essentials filled in.";
        } else if (score >= 70) {
            completionHint.textContent = "Almost ready. Review any missing required detail.";
        } else {
            completionHint.textContent = "Add the required details to keep this profile useful.";
        }
    }

    completionFields.forEach((field) => {
        field.addEventListener("input", updateProfileCompletion);
        field.addEventListener("change", updateProfileCompletion);
    });

    if (copySellerEmail && emailInput) {
        copySellerEmail.addEventListener("click", async () => {
            const email = emailInput.value.trim();
            if (!email) {
                emailInput.focus();
                return;
            }

            try {
                await navigator.clipboard.writeText(email);
                copySellerEmail.textContent = "Copied";
                window.setTimeout(() => {
                    copySellerEmail.textContent = "Copy Email";
                }, 1400);
            } catch (error) {
                emailInput.select();
            }
        });
    }

    updateProfileCompletion();
})();
</script>

<?php require __DIR__ . "/footer.php"; ?>
