<?php

	namespace Bolt;

	abstract class component {

		private static $_dependencies = [];

		function setDependency( $name, $value ) {
			self::$_dependencies[ $name ] = $value;
			return $this;
		}

		function __set( $prop, $val ) {
			return $this->setDependency( $prop, $val );
		}

		function __get( $prop ) {
			if( isset( self::$_dependencies[ $prop ] ) ) {
				if( is_callable( self::$_dependencies[ $prop ] ) ) {
					self::$_dependencies[ $prop ] = call_user_func( self::$_dependencies[ $prop ] );
				}
				return self::$_dependencies[ $prop ];
			}
			throw new Exception( "Call to undefined property '$prop' on '" . __CLASS__ . "'!" );
		}

		function debug() {
			if( isset( $_GET[ 'myDebug' ] ) ) {
				call_user_func_array( 'var_dump', array_merge( [ microtime( true ) ], func_get_args() ) );
			}
		}

		function getUser() {
			$authProvider = Application::getInstance()->getAuthProvider();
			return $authProvider ? $authProvider->getUser() : null;
		}

		function getUserRoles() {
			$authProvider = Application::getInstance()->getAuthProvider();
			return $authProvider ? $authProvider->getUserRoles() : null;
		}

		function getConfig() {
			return call_user_func_array( [ Application::getInstance(), 'getConfig' ], func_get_args() );
		}

		function usingDb() {
			return call_user_func_array( 'Bolt\\Db::execute', func_get_args() );
		}

		function on() {
			return call_user_func_array( [ Application::getInstance(), 'subscribe' ], func_get_args() );
		}

		function trigger() {
			return call_user_func_array( [ Application::getInstance(), 'trigger' ], func_get_args() );
		}

		function isValidCsrf() {
			return $_SESSION[ 'CSRF_TOKEN' ] == $_POST[ 'CSRF_TOKEN' ];
		}

		function __call( $func, $args ) {
			if( empty( $args ) && substr( $func, 0, 3 ) == 'get' ) {
				$prop = '_' . lcfirst( substr( $func, 3 ) );
				if( property_exists( $this, $prop ) ) {
					return $this->$prop;
				}
			}
			throw new \Exception( "Call to undefined function `$func`!" );
		}
	}
