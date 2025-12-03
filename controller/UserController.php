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

    public function getSetting($name, $defaultValue = null)
    {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM settings WHERE user_id = ? AND name = ?");
        $stmt->bind_param("is", $this->userId, $name);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['value'] ?? $defaultValue;
    }
    public function getDriver()
    {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM drivers WHERE user_id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
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

    public function getBalance()
    {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT balance FROM balances WHERE user_id=?");
        $stmt->bind_param('i', $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows == 0 ? 0 : $result->fetch_assoc()['balance'] ?? 0;
    }

    public function addBalance($balance, $reference) {
        global $mysqli;
        $this->addReference($this->getBalance(), $balance, $reference);
        $stmt = $mysqli->prepare("INSERT INTO balances (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE `balance` = balance + ?");
        $stmt->bind_param("idd", $this->userId, $balance, $balance);
        $stmt->execute();
        return $mysqli->affected_rows;
    }

    public function reduceBalance($amount, $reference)
    {
        global $mysqli;
        $balance = $this->getBalance();
        if ($amount <= $balance) {
            $this->addReference($balance, $amount, $reference);
            $stmt = $mysqli->prepare("UPDATE balances SET balance = balance - ? WHERE user_id=?");
            $stmt->bind_param("di", $amount, $this->userId);
            $stmt->execute();

            return true;
        } else {
            return false;
        }
    }

    public function addReference($beforeBalance, $amount, $reference)
    {
        global $mysqli;
        $mysqli->query("INSERT INTO balance_history (user_id, amount, before_balance, reference) VALUES ('{$this->userId}', '$amount', '$beforeBalance','$reference')");
    }

    public function checkReferenceExist($reference)
    {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM balance_history WHERE reference = ?");
        $stmt->bind_param("i", $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows != 0;
    }
}