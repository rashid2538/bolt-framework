<?php

namespace Bolt;

class DbResult extends DbTable implements \Iterator, \JsonSerializable, \ArrayAccess, \Serializable, \Countable
{

    private array $_records = [];
    private Db $_context;
    private int $_position = 0;
    private int $_totalCount = 0;
    private int $_totalPages = 1;
    private int $_page = 1;
    private int $_quantity = 0;

    public function __construct($name, $records, $context = null, $totalCount = null, $quantity = 10, $page = 1)
    {
        $this->_records = $records;
        $this->_name = $name;
        $this->_context = $context;
        $this->_position = 0;
        if (is_null($totalCount)) {
            $totalCount = count($records);
        }
        $this->_totalCount = $totalCount;
        $this->_quantity = $quantity;
        $this->_totalPages = ceil($totalCount / $quantity);
        $this->_page = $page;
    }

    public function getTotalPages()
    {
        return $this->_totalPages;
    }

    public function getTotalCount()
    {
        return $this->_totalCount;
    }

    public function getPage()
    {
        return $this->_page;
    }

    public function getQuantity()
    {
        return $this->_quantity;
    }

    public function iterator()
    {
        foreach ($this->_records as &$record) {
            yield new DbModel($this->_name, $record, $this->_context);
        }
    }

    public function first()
    {
        return empty($this->_records) ? null : $this[0];
    }

    public function last()
    {
        return empty($this->_records) ? null : $this[count($this->_records) - 1];
    }

    public function toArray()
    {
        $result = [];
        foreach ($this->_records as &$record) {
            $result[] = new DbModel($this->_name, $record, $this->_context);
        }
        return $result;
    }

    public function rewind(): void
    {
        $this->_position = 0;
    }

    public function current()
    {
        return new DbModel($this->_name, $this->_records[$this->_position], $this->_context);
    }

    public function key()
    {
        return $this->_position;
    }

    public function next(): void
    {
        ++$this->_position;
    }

    public function valid(): bool
    {
        return isset($this->_records[$this->_position]);
    }

    public function jsonSerialize()
    {
        return $this->_records;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->_records[$offset] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->_records[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->_records[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return isset($this->_records[$offset]) ? new DbModel($this->_name, $this->_records[$offset], $this->_context) : null;
    }

    public function serialize(): string
    {
        return serialize([
            $this->_name,
            $this->_records,
            $this->_totalCount,
            $this->_quantity,
            $this->_page,
        ]);
    }

    public function unserialize(string $data): void
    {
        list($this->_name, $this->_records, $this->_totalCount, $this->_quantity, $this->_page) = unserialize($data);
        $this->usingDb(function ($db) {
            $this->_context = $db;
        });
    }

    public function count(): int
    {
        return count($this->_records);
    }
}
