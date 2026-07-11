<?php

require_once __DIR__ . "/init.php";

clearRememberEmail();
signOut();

redirect("login.php");
