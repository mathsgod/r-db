<?php
date_default_timezone_set('Asia/Hong_Kong');
ini_set("display_errors", "On");
error_reporting(E_ALL && ~E_WARNING);
setlocale(LC_ALL, 'en_US.UTF-8'); //do not remove

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/tests/Testing.php";

Testing::_table()->truncate();

Testing::_table()->insert(["name" => "abc1"]);

echo Testing::_table()->count();

