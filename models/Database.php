<?php
class Database
{
    private static $instance = null;
    private $conn;

    private $host;
    private $user;
    private $pass;
    private $dbname;

    private function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $this->user = $_ENV['DB_USER'] ?? 'root';
        $this->pass = $_ENV['DB_PASS'] ?? '';
        $this->dbname = $_ENV['DB_NAME'] ?? 'merauke_db';

        $this->conn = mysqli_connect($this->host, $this->user, $this->pass, $this->dbname);
        if (!$this->conn) {
            throw new Exception('Koneksi database gagal: ' . mysqli_connect_error());
        }
        mysqli_set_charset($this->conn, 'utf8');
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function query($sql)
    {
        return mysqli_query($this->conn, $sql);
    }

    public function fetchAll($sql)
    {
        $result = $this->query($sql);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        return $data;
    }

    public function fetchOne($sql)
    {
        $result = $this->query($sql);
        if ($result) {
            return mysqli_fetch_assoc($result);
        }
        return null;
    }

    public function escape($value)
    {
        return mysqli_real_escape_string($this->conn, $value);
    }

    public function close()
    {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }
}
