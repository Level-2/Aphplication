<?php
namespace Aphplication;

session_start();
$_SESSION['__appserverId'] = 101;
session_write_close();
require_once __DIR__ . '/Client.php';
