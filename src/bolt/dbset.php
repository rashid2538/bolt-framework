<?php

	namespace Bolt;

	class DbSet extends DbTable {

		private $_context;

		private $_columns = '*';
		private $_where = [];
		private $_groupBy = '';
		private $_orderBy = '';
		private $_limit = '0, 10';
		private $_params = [];

		function __construct( $name, $context ) {
			$this->_name = $name;
			$this->_context = $context;
		}

		function add( $record ) {
			return new DbModel( $this->_name, $record, $this->_context );
		}

		function where() {
			$args = func_get_args();
			switch( count( $args ) ) {
				case 1 : {
					$this->_where[] = $args[ 0 ];
					break;
				} case 2 : {
					$this->_where[] = "`$args[0]` = :$args[0]";
					$this->_params[ ":$args[0]" ] = $args[ 1 ];
					break;
				} case 3 : {
					$this->_where[] = "`$args[0]` $args[1] :$args[0]";
					$this->_params[ ":$args[0]" ] = $args[ 2 ];
					break;
				}
			}
			return $this;
		}

		function andWhere() {
			return call_user_func_array( [ $this, 'where' ], func_get_args() );
		}

		function select( $columns ) {
			$this->_columns = $columns;
			return $this;
		}

		function fetch() {
			$result = $this->trigger( 'beforeSelect', $this, $this->_name );
			if( $result === false ) {
				return new DbResult( $this->_name, [], $this->_context );
			}
			$where = implode( ' AND ', $this->_where );
			$table = $this->getTableName();
			$sql = "SELECT $this->_columns FROM `{$table}` WHERE {$where}";
			if( $this->_groupBy ) {
				$sql .= ' ' . $this->_groupBy;
			}
			if( $this->_orderBy ) {
				$sql .= ' ' . $this->_orderBy;
			}
			$sql .= ' ' . " LIMIT {$this->_limit}";

			return $this->_context->select( $sql, $this->_name, $this->_params );
		}

		function first() {
			$this->_limit = '0, 1';
			$result = $this->fetch();
			return count( $result ) > 0 ? $result[ 0 ] : null;
		}

		function toArray() {
			return $this->fetch()->toArray();
		}
	}