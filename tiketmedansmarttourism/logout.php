<?php
require_once "auth.php";
$_SESSION = [];
session_destroy();
pindah("index.php");
