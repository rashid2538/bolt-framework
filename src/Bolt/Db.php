<?php

namespace Bolt;

use PDOStatement;

class Db extends Component implements IDisposable
{

    private static $_instance;
    private \PDO $_connection;
    private $_sets = [];

    public static function execute(callable $callback)
    {
        if (is_callable($callback)) {
            $args = func_get_args();
            $args[0] = self::getInstance();
            return call_user_func_array($callback, $args);
        }
    }

    private static function getInstance():Db
    {
        if (!self::$_instance || !self::$_instance->_connection) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
        $this->_connect();
    }

    private function _connect():void
    {
        $this->_connection = new \PDO('mysql:host=' . $this->getConfig(Constant::CONFIG_DB_HOST, 'localhost') . ';dbname=' . $this->getConfig(Constant::CONFIG_DB_DATABASE, new Exception('Unable to find database configuration in the config file')), $this->getConfig(Constant::CONFIG_DB_USER, function () {
            throw new Exception('Unable to find database configuration in the config file!');
        }), $this->getConfig(Constant::CONFIG_DB_PASSWORD, function () {
            throw new Exception('Unable to find database configuration in the config file!');
        }));
        $this->_connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->trigger('databaseConnected', $this->_connection);
        $this->debug('Database connection opened.');
    }

    public function newId():int
    {
        return $this->_connection ? $this->_connection->lastInsertId() : null;
    }

    public function getError():array
    {
        return $this->_connection ? [
            'code' => intval($this->_connection->errorCode()),
            'info' => $this->_connection->errorInfo(),
        ] : [
            'code' => -1,
            'info' => 'Database not connected!',
        ];
    }

    public function dispose():void
    {
        unset($this->_connection);
        $this->_connection = null;
        $this->_sets = [];
        self::$_instance = null;
        $this->trigger('databaseDisconnected');
        $this->debug('Database connection closed.');
    }

    public function beat($fromSelf = false):Db
    {
        if ($this->_connection === null) {
            if ($fromSelf) {
                $this->_connect();
            } else if (self::$_instance->getConnection() == null) {
                self::$_instance->beat(true);
                $this->_connection = self::$_instance->getConnection();
            }
        }
        return $this;
    }

    public function __destruct()
    {
        $this->dispose();
    }

    public function __get(string $dbSetName):DbSet
    {
        if (!isset($this->_sets[$dbSetName])) {
            $this->_sets[$dbSetName] = new DbSet($dbSetName, $this);
        }
        return $this->_sets[$dbSetName];
    }

    public function escape(string $str):string
    {
        return $this->_connection->quote($str);
    }

    public function select(string $sql, array $params = [], string $name = null, int $totalCount = null, int $quantity = 10, int $page = 1):DbResult
    {
        return strtolower(substr(trim($sql), 0, 7)) == 'select ' ? new DbResult($name, $this->query($sql, $params), $this, $totalCount, $quantity, $page) : null;
    }

    public function query(string $sql, array $params = []):array|\PDOStatement
    {
        $sql = $this->trigger('executingQuery', $sql, $params);
        if (empty($params)) {
            $statement = $this->_connection->query($sql);
            $this->trigger('queryExecuted', $sql, $params, $statement);
            return strtolower(substr(trim($sql), 0, 7)) == 'select ' ? $statement->fetchAll(\PDO::FETCH_ASSOC) : $statement;
        } else {
            $statement = $this->_connection->prepare($sql);
            $statement->execute($params);
            $this->trigger('queryExecuted', $sql, $params, $statement);
            return strtolower(substr(trim($sql), 0, 7)) == 'select ' ? $statement->fetchAll(\PDO::FETCH_ASSOC) : $statement;
        }
    }
}
