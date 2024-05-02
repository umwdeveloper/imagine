<?php

class DB {
    private const HOST = "localhost";
    private const DBNAME = "imgopenai";
    private const DSN = "mysql:host=" . self::HOST . ";dbname=" . self::DBNAME;
    private const USERNAME = "root";
    private const PASSWORD = "";
    private array $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ];

    private $conn;

    public function open() {
        try {
            $this->conn = new PDO(self::DSN, self::USERNAME, self::PASSWORD, $this->options);
            return $this->conn;
        } catch (PDOException $e) {
            die("Connection Error: " . $e->getMessage());
        }
    }

    public function close() {
        $this->conn = null;
    }
}

$pdo = new DB;
