<?php

const DB_HOST = "127.0.0.1";//localhost
const DB_USER = 'DB_USER';
const DB_PASS = 'DB_PASS';
const DB_NAME = 'DB_NAME';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli->set_charset("utf8");
if ($mysqli->connect_errno) {
    printf("Connect failed: %s\n", $mysqli->connect_error);
}
