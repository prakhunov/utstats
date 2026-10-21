<?php

// Minimal FTP client over ext/ftp with the interface includes/ftp.php expects.
// (Replaces the old socket/pure-PHP FTP classes.)

class ftp
{
    public string $lastmsg = '';
    public string $host = '';
    public int $port = 21;
    private ?FTP\Connection $conn = null;
    private int $transfertype = FTP_BINARY;
    private array $errors = [];

    public function SetServer(string $host, int $port): bool
    {
        $this->host = $host;
        $this->port = $port;
        return true;
    }

    public function connect(): bool
    {
        $conn = $this->call(fn () => ftp_connect($this->host, $this->port, 30));
        if ($conn === false) {
            return false;
        }
        $this->conn = $conn;
        return true;
    }

    public function login(string $user, string $pass): bool
    {
        return $this->call(fn () => ftp_login($this->conn, $user, $pass));
    }

    public function SetType(int $transfertype): bool
    {
        $this->transfertype = $transfertype;
        return true;
    }

    public function Passive(bool $pasv): bool
    {
        return $this->call(fn () => ftp_pasv($this->conn, $pasv));
    }

    public function pwd(): string|false
    {
        return $this->call(fn () => ftp_pwd($this->conn));
    }

    public function chdir(string $dir): bool
    {
        return $this->call(fn () => ftp_chdir($this->conn, $dir));
    }

    public function nlist(): array|false
    {
        return $this->call(fn () => ftp_nlist($this->conn, '.'));
    }

    // Download to $localfile; returns its size in bytes, or false.
    public function get(string $remotefile, string $localfile): int|false
    {
        if (!$this->call(fn () => ftp_get($this->conn, $localfile, $remotefile, $this->transfertype))) {
            $this->PushError('get', "Unable to download $remotefile", $this->lastmsg);
            return false;
        }
        clearstatcache(true, $localfile);
        return filesize($localfile);
    }

    public function delete(string $remotefile): bool
    {
        return $this->call(fn () => ftp_delete($this->conn, $remotefile));
    }

    public function rename(string $from, string $to): bool
    {
        return $this->call(fn () => ftp_rename($this->conn, $from, $to));
    }

    public function quit(bool $force = false): bool
    {
        if ($this->conn !== null) {
            @ftp_close($this->conn);
            $this->conn = null;
        }
        return true;
    }

    public function PushError(string $fctname, string $msg, string|false $desc = false): int
    {
        $this->errors[] = ['time' => time(), 'fctname' => $fctname, 'msg' => $msg, 'desc' => $desc];
        return count($this->errors);
    }

    public function PopError(): array|false
    {
        return array_pop($this->errors) ?? false;
    }

    // Run an ext/ftp call; on failure keep its warning text in $lastmsg and return false.
    private function call(callable $fn): mixed
    {
        if ($this->conn === null && $this->host === '') {
            $this->lastmsg = 'No server set';
            return false;
        }
        error_clear_last();
        $result = @$fn();
        if ($result === false) {
            $this->lastmsg = error_get_last()['message'] ?? 'FTP command failed';
        }
        return $result;
    }
}
