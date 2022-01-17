<?php

namespace Bolt;

class Db extends Component implements IDisposable
{

    private static $_instance;
    private $_connection;
    private $_sets = [];

    public static function execute($callback)
    {
        if (is_callable($callback)) {
            $args = func_get_args();
            $args[0] = self::getInstance();
            return call_user_func_array($callback, $args);
        }
    }

    private static function getInstance()
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

    private function _connect()
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

    public function newId()
    {
        return $this->_connection ? $this->_connection->lastInsertId() : null;
    }

    public function getError()
    {
        return $this->_connection ? [
            'code' => intval($this->_connection->errorCode()),
            'info' => $this->_connection->errorInfo(),
        ] : [
            'code' => -1,
            'info' => 'Database not connected!',
        ];
    }

    public function dispose()
    {
        unset($this->_connection);
        $this->_connection = null;
        $this->_sets = [];
        self::$_instance = null;
        $this->trigger('databaseDisconnected');
        $this->debug('Database connection closed.');
    }

    public function beat($fromSelf = false)
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

    public function __get($dbSetName)
    {
        if (!isset($this->_sets[$dbSetName])) {
            $this->_sets[$dbSetName] = new DbSet($dbSetName, $this);
        }
        return $this->_sets[$dbSetName];
    }

    public function escape($str)
    {
        return $this->_connection->quote($str);
    }

    public function select($sql, $params = [], $name = null, $totalCount = null, $quantity = 10, $page = 1)
    {
        return strtolower(substr(trim($sql), 0, 7)) == 'select ' ? new DbResult($name, $this->query($sql, $params), $this, $totalCount, $quantity, $page) : null;
    }

    public function query($sql, $params = [])
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
