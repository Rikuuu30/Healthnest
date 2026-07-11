(function () {
    "use strict";

    document.querySelectorAll("[data-password-toggle]").forEach(function (button) {
        button.addEventListener("click", function () {
            var input = document.getElementById(button.dataset.passwordToggle);

            if (!input) {
                return;
            }

            var shouldShow = input.type === "password";
            input.type = shouldShow ? "text" : "password";
            button.textContent = shouldShow ? "Hide" : "Show";
            button.setAttribute("aria-pressed", shouldShow ? "true" : "false");
            button.setAttribute("aria-label", (shouldShow ? "Hide" : "Show") + " " + (input.id === "confirm" ? "confirm password" : "password"));
            input.focus();
        });
    });

    var password = document.getElementById("password");
    var confirmPassword = document.getElementById("confirm");

    if (password && confirmPassword) {
        var validatePasswordMatch = function () {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords do not match.");
            } else {
                confirmPassword.setCustomValidity("");
            }
        };

        password.addEventListener("input", validatePasswordMatch);
        confirmPassword.addEventListener("input", validatePasswordMatch);
    }

    var roleSelect = document.querySelector("[data-role-select]");

    if (roleSelect) {
        var nativeRoleSelect = roleSelect.querySelector("select");
        var roleTrigger = roleSelect.querySelector(".role-select-trigger");
        var roleMenu = roleSelect.querySelector(".role-select-menu");
        var roleValue = roleSelect.querySelector("#role-select-value");
        var roleOptions = Array.prototype.slice.call(roleSelect.querySelectorAll(".role-select-option"));

        var syncRole = function (value) {
            var selectedOption = roleOptions.find(function (option) {
                return option.dataset.roleValue === value;
            });

            if (!selectedOption) {
                return;
            }

            nativeRoleSelect.value = value;
            roleValue.textContent = selectedOption.textContent.trim();

            roleOptions.forEach(function (option) {
                option.setAttribute("aria-selected", option === selectedOption ? "true" : "false");
            });
        };

        var openRoleMenu = function () {
            roleMenu.hidden = false;
            roleTrigger.setAttribute("aria-expanded", "true");
            roleSelect.classList.add("is-open");

            var selectedOption = roleOptions.find(function (option) {
                return option.getAttribute("aria-selected") === "true";
            });

            (selectedOption || roleOptions[0]).focus();
        };

        var closeRoleMenu = function (restoreFocus) {
            roleMenu.hidden = true;
            roleTrigger.setAttribute("aria-expanded", "false");
            roleSelect.classList.remove("is-open");

            if (restoreFocus) {
                roleTrigger.focus();
            }
        };

        roleSelect.classList.add("custom-select-ready");
        nativeRoleSelect.tabIndex = -1;
        nativeRoleSelect.setAttribute("aria-hidden", "true");
        syncRole(nativeRoleSelect.value);

        roleTrigger.addEventListener("click", function () {
            if (roleMenu.hidden) {
                openRoleMenu();
            } else {
                closeRoleMenu(false);
            }
        });

        roleTrigger.addEventListener("keydown", function (event) {
            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                openRoleMenu();
            }
        });

        roleOptions.forEach(function (option, index) {
            option.addEventListener("click", function () {
                syncRole(option.dataset.roleValue);
                nativeRoleSelect.dispatchEvent(new Event("change", { bubbles: true }));
                closeRoleMenu(true);
            });

            option.addEventListener("keydown", function (event) {
                var nextIndex = index;

                if (event.key === "ArrowDown") {
                    nextIndex = (index + 1) % roleOptions.length;
                } else if (event.key === "ArrowUp") {
                    nextIndex = (index - 1 + roleOptions.length) % roleOptions.length;
                } else if (event.key === "Home") {
                    nextIndex = 0;
                } else if (event.key === "End") {
                    nextIndex = roleOptions.length - 1;
                } else if (event.key === "Escape") {
                    event.preventDefault();
                    closeRoleMenu(true);
                    return;
                } else if (event.key === "Tab") {
                    closeRoleMenu(false);
                    return;
                } else {
                    return;
                }

                event.preventDefault();
                roleOptions[nextIndex].focus();
            });
        });

        nativeRoleSelect.addEventListener("change", function () {
            syncRole(nativeRoleSelect.value);
        });

        document.addEventListener("click", function (event) {
            if (!roleMenu.hidden && !roleSelect.contains(event.target)) {
                closeRoleMenu(false);
            }
        });
    }

    var birthdate = document.getElementById("birthdate");
    var ageStatus = document.getElementById("age-status");

    if (birthdate && ageStatus) {
        var validateAge = function () {
            if (!birthdate.value) {
                ageStatus.textContent = "You must be at least 18 years old.";
                ageStatus.className = "field-hint";
                birthdate.setCustomValidity("");
                return;
            }

            var selected = new Date(birthdate.value + "T00:00:00");
            var today = new Date();
            var age = today.getFullYear() - selected.getFullYear();
            var monthDifference = today.getMonth() - selected.getMonth();

            if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < selected.getDate())) {
                age -= 1;
            }

            if (age < 18) {
                ageStatus.textContent = "You must be 18 or older to register.";
                ageStatus.className = "field-hint invalid";
                birthdate.setCustomValidity("You must be at least 18 years old to register.");
            } else {
                ageStatus.textContent = "Age requirement met (" + age + " years old).";
                ageStatus.className = "field-hint valid";
                birthdate.setCustomValidity("");
            }
        };

        birthdate.addEventListener("input", validateAge);
        birthdate.addEventListener("change", validateAge);
        validateAge();
    }
}());
