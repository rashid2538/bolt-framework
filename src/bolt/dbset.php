<?php

	namespace Bolt;

	class DbSet extends DbTable {

		private $_context;

		private $_columns = '*';
		private $_where = [];
		private $_groupBy = '';
		private $_orderBy = '';
		private $_quantity = 10;
		private $_page = 1;
		private $_params = [];

		function __construct( $name, $context ) {
			$this->_name = $name;
			$this->_context = $context;
		}

		function quantity( $q ) {
			$this->_quantity = $q;
			return $this;
		}

		function page( $p ) {
			$this->_page = $p;
			return $this;
		}

		private function _reset() {
			$this->_columns = '*';
			$this->_where = [];
			$this->_groupBy = '';
			$this->_orderBy = '';
			$this->_quantity = 10;
			$this->_page = 1;
			$this->_params = [];
		}

		function add( $record ) {
			return new DbModel( $this->_name, $record, $this->_context );
		}

		function where() {
			$args = func_get_args();
			$numArgs = func_num_args();
			switch( $numArgs ) {
				case 0 : {
					return $this;
				} case 1 : {
					$this->_where[] = $args[ 0 ];
					break;
				} case 2 : {
					$this->_where[] = "`$args[0]` = :$args[0]";
					$this->_params[ ":$args[0]" ] = $args[ 1 ];
					break;
				} default : {
					if( strtolower( trim( $args[ 1 ] ) ) == 'in' ) {
						$ins = [];
						$inArgs = is_array( $args[ 2 ] ) ? $args[ 2 ] : array_slice( $args, 2 );
						foreach( $inArgs as $i => $val ) {
							$ins[] = ":{$args[0]}In$i";
							$this->_params[ ":{$args[0]}In$i" ] = $val;
						}
						$this->_where[] = "`$args[0]` IN ( " . implode( ', ', $ins ) . ' )';
					} else if( strtolower( trim( $args[ 1 ] ) ) == 'between' ) {
						if( $numArgs != 4 ) {
							throw new \Exception( 'Between operator requires two operands!' );
						}
						$this->_params[ ":{$args[0]}Between1" ] = $args[ 2 ];
						$this->_params[ ":{$args[0]}Between2" ] = $args[ 3 ];
						$this->_where[] = "`$args[0]` BETWEEN :{$args[0]}Between1 AND :{$args[0]}Between2";
					} else {
						$this->_where[] = "`$args[0]` $args[1] :$args[0]";
						$this->_params[ ":$args[0]" ] = $args[ 2 ];
					}
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

		function orderBy( $order ) {
			$this->_orderBy = $order;
			return $this;
		}

		function groupBy( $group ) {
			$this->_groupBy = $group;
			return $this;
		}

		function fetch() {
			$result = $this->trigger( 'beforeSelect', $this, $this->_name );
			if( $result === false ) {
				return new DbResult( $this->_name, [], $this->_context );
			}
			$where = implode( ' AND ', $this->_where );
			$table = $this->getTableName();
			$sql = "SELECT {$this->_columns} FROM `{$table}`";
			if( $where ) {
				$sql .= " WHERE {$where}";
			}
			if( $this->_groupBy ) {
				$sql .= ' GROUP BY ' . $this->_groupBy;
			}
			if( $this->_orderBy ) {
				$sql .= ' ORDER BY ' . $this->_orderBy;
			}
			$sql .= '  LIMIT ' . $this->_limit();

			$result = $this->_context->select( $sql, $this->_params, $this->_name );
			$this->_reset();
			return $result;
		}

		private function _limit() {
			return ( ( $this->_page - 1 ) * $this->_quantity ) . ', ' . $this->_quantity;
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
