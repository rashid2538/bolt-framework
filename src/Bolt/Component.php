<?php

	namespace Bolt;

	abstract class Component {

		private static $_dependencies = [];
		private static $_callbacks = [];

		function setDependency( $name, $value ) {
			self::$_dependencies[ $name ] = $value;
			return $this;
		}

		function __isset( $prop ) {
			return isset( self::$_dependencies[ $prop ] ) || isset( self::$_callbacks[ $prop ] );
		}

		function getDependency( $prop ) {
			if( isset( self::$_dependencies[ $prop ] ) ) {
				if( is_callable( self::$_dependencies[ $prop ] ) ) {
					self::$_dependencies[ $prop ] = call_user_func( self::$_dependencies[ $prop ] );
				}
				return self::$_dependencies[ $prop ];
			}
		}

		function setCallback( $name, $value ) {
			if( is_callable( $value ) ) {
				self::$_callbacks[ $name ] = $value;
			} else {
				throw new \Exception( "Unable to set callback as it should be a callable!" );
			}
			return $this;
		}

		function __set( $prop, $val ) {
			return $this->setDependency( $prop, $val );
		}

		function getMessages() {
			$messages = isset( $_SESSION[ 'messages' ] ) ? $_SESSION[ 'messages' ] : [];
			unset( $_SESSION[ 'messages' ] );
			return $messages;
		}

		function dummy() {
			return new Dummy();
		}

		function setMessage( $message, $type = 'info' ) {
			$_SESSION[ 'messages' ][] = [
				'message' => $message,
				'type' => $type
			];
			return $this;
		}

		function __get( $prop ) {
			if( isset( self::$_dependencies[ $prop ] ) ) {
				if( is_callable( self::$_dependencies[ $prop ] ) ) {
					self::$_dependencies[ $prop ] = call_user_func( self::$_dependencies[ $prop ] );
				}
				return self::$_dependencies[ $prop ];
			}
			throw new \Exception( "Call to undefined property '$prop' on '" . __CLASS__ . "'!" );
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

		function userHasRole( $role ) {
			return in_array( $role, $this->getUserRoles() );
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
			} else if( isset( self::$_callbacks[ $func ] ) ) {
				return call_user_func_array( self::$_callbacks[ $func ], $args );
			}
			throw new \Exception( "Call to undefined function `$func`!" );
		}
	}
