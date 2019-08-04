<?php

	namespace Bolt;

	class Db extends Component implements IDisposable {

		private static $_instance;
		private $_connection;
		private $_sets = [];

		static function execute( $callback ) {
			if( is_callable( $callback ) ) {
				$args = func_get_args();
				$args[ 0 ] = self::getInstance();
				return call_user_func_array( $callback, $args );
			}
		}

		private static function getInstance() {
			if( !self::$_instance || !self::$_instance->_connection ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		private function __construct() {
			$this->_connect();
		}

		private function _connect() {
			$this->_connection = new \PDO( 'mysql:host=' . $this->getConfig( 'db/host', 'localhost' ) . ';dbname=' . $this->getConfig( 'db/database', new \Exception( 'Unable to find database configuration in the config file' ) ), $this->getConfig( 'db/user', new \Exception( 'Unable to find database configuration in the config file' ) ), $this->getConfig( 'db/password', new \Exception( 'Unable to find database configuration in the config file' ) ) );
			$this->_connection->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
			$this->trigger( 'databaseConnected', $this->_connection );
			$this->debug( 'Database connection opened.' );
		}

		function newId() {
			return $this->_connection ? $this->_connection->lastInsertId() : null;
		}

		function getError() {
			return $this->_connection ? [
				'code' => intval( $this->_connection->errorCode() ),
				'info' => $this->_connection->errorInfo()
			] : [
				'code' => -1,
				'info' => 'Database not connected!'
			];
		}

		function dispose() {
			unset( $this->_connection );
			$this->_connection = null;
			$this->_sets = [];
			self::$_instance = null;
			$this->trigger( 'databaseDisconnected' );
			$this->debug( 'Database connection closed.' );
		}

		function beat( $fromSelf = false ) {
			if( $this->_connection === null ) {
				if( $fromSelf ) {
					$this->_connect();
				} else if( self::$_instance->getConnection() == null ) {
					self::$_instance->beat( true );
					$this->_connection = self::$_instance->getConnection();
				}
			}
			return $this;
		}

		function __destruct() {
			$this->dispose();
		}

		function __get( $dbSetName ) {
			if( !isset( $this->_sets[ $dbSetName ] ) ) {
				$this->_sets[ $dbSetName ] = new DbSet( $dbSetName, $this );
			}
			return $this->_sets[ $dbSetName ];
		}

		function escape( $str ) {
			return $this->_connection->quote( $str );
		}

		function select( $sql, $params = [], $name = null, $totalCount, $quantity ) {
			return strtolower( substr( trim( $sql ), 0, 7 ) ) == 'select ' ? new DbResult( $name, $this->query( $sql, $params ), $this, $totalCount, $quantity ) : null;
		}

		function query( $sql, $params = [] ) {
			$sql = $this->trigger( 'executingQuery', $sql, $params );
			if( empty( $params ) ) {
				$statement = $this->_connection->query( $sql );
				return strtolower( substr( trim( $sql ), 0, 7 ) ) == 'select ' ? $statement->fetchAll( \PDO::FETCH_ASSOC ) : $statement;
			} else {
				$statement = $this->_connection->prepare( $sql );
				$statement->execute( $params );
				return strtolower( substr( trim( $sql ), 0, 7 ) ) == 'select ' ? $statement->fetchAll( \PDO::FETCH_ASSOC ) : $statement;
			}

		}
	}