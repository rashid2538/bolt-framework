<?php

namespace Bolt;

class DbModel extends DbTable implements \JsonSerializable, \ArrayAccess, \Serializable
{

    private array $_record;
    private array $_original;
    private Db $_context;
    private string $_error;
    private array $_fkMaps = [];
    public string $pk = 'id';

    public function __construct(string $name, array&$record, Db $context = null)
    {
        $this->_name = $name;
        $this->_record = $record;
        $this->_original = $record;
        $this->_context = $context;
    }

    public function __get(string $name): mixed
    {
        if (in_array($name, array_keys($this->_record))) {
            return $this->_record[$name];
        }
        $this->_context->beat();
        if (isset($this->_fkMaps[$name])) {
            $dbSet = $this->_fkMaps[$name][0];
            return $this->_context->$dbSet->where('id', $this->_record[$this->_fkMaps[$name][1]]);
        }
        if (isset($this->_record[$name . 'Id'])) {
            return $this->_context->$name->where('id', $this->_record[$name . 'Id'])->first();
        } else {
            return $this->_context->$name->where($this->_name . 'Id', $this->_record[$this->pk]);
        }
    }

    public function merge(array $data):DbModel
    {
        foreach ($data as $key => $val) {
            $this->_record[$key] = $val;
        }
        return $this;
    }

    public function __call(string $name, array $args):mixed
    {
        if (substr($name, 0, 3) == 'get') {
            $prop = lcfirst(substr($name, 3));
            return $this->__get($prop);
        }
        return parent::__call($name, $args);
    }

    public function save():DbModel|bool
    {
        return isset($this[$this->pk]) && $this[$this->pk] > 0 ? $this->update() : $this->create();
    }

    public function create():DbModel|bool
    {
        $data = $this->trigger('beforeInsert', $this->_record, $this->_name);
        if ($data === false) {
            return false;
        }
        $keys = array_keys($data);
        $sql = 'INSERT INTO `' . $this->getTableName() . '`( `' . implode('`, `', $keys) . '` ) VALUES ( :' . implode(', :', $keys) . ' )';
        try {
            $this->_context->beat()->query($sql, $data);
            $this->_record[$this->pk] = $this->_context->newId();
            $this->_record = $this->trigger('afterInsert', $this->_record, $this->_name);
            return $this;
        } catch (\Exception$ex) {
            $this->_error = $ex->getMessage();
            return false;
        }
    }

    public function update():DbModel|bool
    {
        if (!isset($this->_record[$this->pk]) || !$this->_record[$this->pk]) {
            return false;
        }
        $data = $this->trigger('beforeUpdate', $this->_record, $this->_name);
        if ($data === false) {
            return false;
        }
        $keys = array_filter(array_map(function ($k) {
            return $k == $this->pk ? null : "`$k` = :$k";
        }, array_keys($data)));
        $sql = 'UPDATE `' . $this->getTableName() . '` SET ' . implode(', ', $keys) . ' WHERE `id` = :id';
        try {
            $this->_context->beat()->query($sql, $data);
            $this->_record = $this->trigger('afterUpdate', $this->_record, $this->_name);
            return $this;
        } catch (\Exception$ex) {
            $this->_error = $ex->getMessage();
            return false;
        }
    }

    public function delete():bool
    {
        if (!isset($this->_record[$this->pk]) || !$this->_record[$this->pk]) {
            return false;
        }
        if ($this->trigger('beforeDelete', $this->_record, $this->_name) === false) {
            return false;
        }
        $sql = 'DELETE FROM `' . $this->getTableName() . '` WHERE `' . $this->pk . '` = :id';
        try {
            $this->_context->beat()->query($sql, ['id' => $this->_record[$this->pk]]);
            $this->_record = $this->trigger('afterDelete', $this->_record, $this->_name);
            return true;
        } catch (\Exception$ex) {
            $this->_error = $ex->getMessage();
            return false;
        }
    }

    public function __isset($prop)
    {
        return true; // set to make other properties accessible also
        // return  in_array( $prop, array_keys( $this->_record ) );
    }

    public function __unset($prop)
    {
        unset($this->_record[$prop]);
    }

    public function getMessage():string
    {
        return $this->_error;
    }

    public function __set($name, $val)
    {
        $this->_record[$name] = $val;
    }

    public function isModified()
    {
        return md5(json_encode($this->_original)) != md5(json_encode($this->_record));
    }

    public function reset()
    {
        $this->_original = $this->_record;
    }

    public function jsonSerialize()
    {
        return $this->_record;
    }

    public function toArray()
    {
        return $this->_record;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->_record[$offset] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->_record[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->_record[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->_record[$offset]) ? $this->_record[$offset] : null;
    }

    public function serialize()
    {
        return serialize([
            $this->_name,
            $this->_record,
        ]);
    }

    public function unserialize($data)
    {
        list($this->_name, $this->_record) = unserialize($data);
        $this->usingDb(function ($db) {
            $this->_context = $db;
        });
    }

    public function mapFk($column, $dbSet, $name = null)
    {
        $name = $name ? $name : $dbSet;
        $this->_fkMaps[$name] = [$dbSet, $column];
        return $this;
    }
}
