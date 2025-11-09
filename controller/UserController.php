<?php

class UserController
{
    private $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function setSetting($name, $value)
    {
        global $mysqli;
        $stmt = $mysqli->prepare("INSERT INTO settings (user_id, name, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value`=?");
        $stmt->bind_param("isss", $this->userId, $name, $value, $value);
        $stmt->execute();
    }

    public function getSetting($name)
    {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM settings WHERE user_id = ? AND name = ?");
        $stmt->bind_param("is", $this->userId, $name);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['value'];
    }
    
    public function getUser()
    {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id=?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}