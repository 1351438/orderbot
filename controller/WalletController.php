<?php


class WalletController {
    private $db;
    public function __construct()
    {
        global $mysqli;
        $this->db = $mysqli;
    }

    public function getWallet() {
        $res = $this->db->query("SELECT * FROM `wallets` LIMIT 1");
        if ($res->num_rows == 0) {
            return null;
        } else {
            $row = $res->fetch_assoc();
            return (new \Olifanton\Interop\Address($row['address']))->toString(true, true, false);
        }
    }
}