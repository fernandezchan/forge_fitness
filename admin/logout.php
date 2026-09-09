<?php
require_once __DIR__ . "/../shared/bootstrap.php";
logout_session();
redirect("auth/login.php");
