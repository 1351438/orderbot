<?php

const DB_HOST = "127.0.0.1";
const DB_USER = 'admin_test';
const DB_PASS = 'mry)e3K!FiQ9ZeJU';
const DB_NAME = 'admin_test';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli->set_charset("utf8");
if ($mysqli->connect_errno) {
    printf("Connect failed: %s\n", $mysqli->connect_error);
}
