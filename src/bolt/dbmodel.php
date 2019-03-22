<?php

	namespace Bolt;

	class DbModel extends DbTable implements \JsonSerializable, \ArrayAccess, \Serializable {

		private $_record;
		private $_original;
		private $_context;
		private $_error;
		private $_proxies = [];

		function __construct( $name, &$record, $context = null ) {
			$this->_name = $name;
			$this->_record = $record;
			$this->_original = $record;
			$this->_context = $context;
		}

		function __get( $name ) {
			if( isset( $this->_record[ $name ] ) ) {
				return $this->_record[ $name ];
			}
			if( isset( $this->_proxies[ $name ] ) ) {
				$name = $this->_proxies[ $name ];
			}
			$this->_context->beat();
			if( isset( $this->_record[ $name . 'Id' ] ) ) {
				return $this->_context->$name->where( 'id', $this->_record[ $name . 'Id' ] )->first();
			} else {
				return $this->_context->$name->where( $this->_name . 'Id', $this->_record[ 'id' ] );
			}
		}

		function save() {
			return isset( $this[ 'id' ] ) && $this[ 'id' ] > 0 ? $this->update() : $this->create();
		}

		function create() {
			$data = $this->trigger( 'beforeInsert', $this->_record, $this->_name );
			if( $data === false ) {
				return false;
			}
			$keys = array_keys( $data );
			$sql = 'INSERT INTO `' . $this->getTableName() . '`( `' . implode( '`, `', $keys ) . '` ) VALUES ( :' . implode( ', :', $keys ) . ' )';
			try {
				$result = $this->_context->beat()->query( $sql, $data );
				$this->_record[ 'id' ] = $this->_context->newId();
				$this->_record = $this->trigger( 'afterInsert', $this->_record, $this->_name );
				return true;
			} catch( \Exception $ex ) {
				$this->_error = $ex->getMessage();
				return false;
			}
		}

		function update() {
			if( !isset( $this->_record[ 'id' ] ) || !$this->_record[ 'id' ] ) {
				return false;
			}
			$data = $this->trigger( 'beforeUpdate', $this->_record, $this->_name );
			if( $data === false ) {
				return false;
			}
			$keys = array_filter( array_map( function( $k ) {
				return $k == 'id' ? null : "`$k` = :$k";
			}, array_keys( $data ) ) );
			$sql = 'UPDATE `' . $this->getTableName() . '` SET ' . implode( ', ', $keys ) . ' WHERE `id` = :id';
			try {
				$result = $this->_context->beat()->query( $sql, $data );
				$this->_record = $this->trigger( 'afterUpdate', $this->_record, $this->_name );
				return true;
			} catch( \Exception $ex ) {
				$this->_error = $ex->getMessage();
				return false;
			}
		}

		function delete() {
			if( !isset( $this->_record[ 'id' ] ) || !$this->_record[ 'id' ] ) {
				return false;
			}
			if( $this->trigger( 'beforeDelete', $this->_record, $this->_name ) === false ) {
				return false;
			}
			$sql = 'DELETE FROM `' . $this->getTableName() . '` WHERE `id` = :id';
			try {
				$this->_context->beat()->query( $sql, [ 'id' => $this->_record[ 'id' ] ] );
				$this->_record = $this->trigger( 'afterDelete', $this->_record, $this->_name );
				return true;
			} catch( \Exception $ex ) {
				$this->_error = $ex->getMessage();
				return false;
			}
		}

		function getMessage() {
			return $this->_error;
		}

		function __set( $name, $val ) {
			$this->_record[ $name ] = $val;
		}

		function isModified() {
			return md5( json_encode( $this->_original ) ) != md5( json_encode( $this->_record ) );
		}

		function reset() {
			$this->_original = $this->_record;
		}

		function jsonSerialize() {
			return $this->_record;
		}

		function toArray() {
			return $this->_record;
		}

		function offsetSet( $offset, $value ) {
			$this->_record[ $offset ] = $value;
		}

		function offsetExists( $offset ) {
			return isset( $this->_record[ $offset ] );
		}

		function offsetUnset( $offset ) {
			unset( $this->_record[ $offset ] );
		}

		function offsetGet( $offset ) {
			return isset( $this->_record[ $offset ] ) ? $this->_record[ $offset ] : null;
		}

		function serialize() {
			return serialize([
				$this->_name,
				$this->_record
			]);
		}

		function unserialize( $data ) {
			list( $this->_name, $this->_record ) = unserialize( $data );
		}

		function proxy( $from, $to ) {
			$this->_proxies[ $from ] = $to;
			return $this;
		}
	}