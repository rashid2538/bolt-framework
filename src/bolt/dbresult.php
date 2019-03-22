<?php

	namespace Bolt;

	class DbResult extends DbTable implements \Iterator, \JsonSerializable, \ArrayAccess, \Serializable, \Countable {

		private $_records = [];
		private $_context;
		private $_position = 0;

		function __construct( $name, $records, $context = null ) {
			$this->_records = $records;
			$this->_name = $name;
			$this->_context = $context;
			$this->_position = 0;
		}

		function iterator() {
			foreach( $this->_records as &$record ) {
				yield new DbModel( $this->_name, $record, $this->_context );
			}
		}

		function toArray() {
			$result = [];
			foreach( $this->_records as &$record ) {
				$result[] = new DbModel( $this->_name, $record, $this->_context );
			}
			return $result;
		}

		function rewind() {
			$this->_position = 0;
		}

		function current() {
			return new DbModel( $this->_name, $this->_records[ $this->_position ], $this->_context );
		}

		function key() {
			return $this->_position;
		}

		function next() {
			++$this->_position;
		}

		function valid() {
			return isset( $this->_records[ $this->_position ] );
		}

		function jsonSerialize() {
			return $this->_records;
		}

		function offsetSet( $offset, $value ) {}

		function offsetExists( $offset ) {
			return isset( $this->_records[ $offset ] );
		}

		function offsetUnset( $offset ) {}

		function offsetGet( $offset ) {
			return isset( $this->_records[ $offset ] ) ? new DbModel( $this->_name, $this->_records[ $offset ], $this->_context ) : null;
		}

		function serialize() {
			return serialize([
				$this->_name,
				$this->_records
			]);
		}

		function unserialize( $data ) {
			list( $this->_name, $this->_records ) = unserialize( $data );
		}

		function count() {
			return count( $this->_records );
		}
	}