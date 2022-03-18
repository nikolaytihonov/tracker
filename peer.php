<?php

function hash_decode($urldata)
{
    return bin2hex(rawurldecode($urldata));
}

function hash_encode($str)
{
    return hex2bin($str);
}

class Peer {
    protected $info_hash;
    protected $ip;
    protected $port;
    protected $update_time;

    public function __construct($hash, $ip, $port) {
        $this->info_hash = $hash;
        $this->ip = $ip;
        $this->port = $port;
        
        $date = new DateTime("now");
        $this->update_time = $date->format('Y-m-d H:i:s');
    }

    public function bind($stmt) {
        $stmt->bindValue(":info_hash", $this->info_hash, PDO::PARAM_STR);
        $stmt->bindValue(":ip", $this->ip, PDO::PARAM_STR);
        $stmt->bindValue(":port", $this->port, PDO::PARAM_INT);
        $stmt->bindValue(":update_time", $this->update_time, PDO::PARAM_STR);
    }

    public function getIp() {
        return $this->ip;
    }

    public function getPort() {
        return $this->port;
    }
}

?>