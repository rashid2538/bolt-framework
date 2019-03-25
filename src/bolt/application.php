<?php

	namespace Bolt;

	class Application extends Component {

		private static $_instance;

		private $_config;
		private $_route = [];
		private $_auth;
		private $_events = [];
		private $_requestStartTime;

		private function __construct() {
			$this->_requestStartTime = microtime( true );
		}

		static function getInstance() {
			return self::$_instance ? self::$_instance : ( self::$_instance = new self() );
		}

		function subscribe( $event, $handler ) {
			if( is_callable( $handler ) ) {
				$this->_events[ $event ][] = $handler;
			} else {
				throw new Exception( print_r( $handler, true ) . ' is not a valid callback for an event!' );
			}
		}

		function trigger() {
			$args = func_get_args();
			$result = isset( $args[ 1 ] ) ? $args[ 1 ] : null;
			if( isset( $this->_events[ $args[ 0 ] ] ) && !empty( $this->_events[ $args[ 0 ] ] ) ) {
				$event = $args[ 0 ];
				unset( $args[ 0 ] );
				$args = array_values( $args );
				foreach( $this->_events[ $event ] as $handler ) {
					$resp = call_user_func_array( $handler, $args );
					if( !is_null( $resp ) ) {
						$result = $resp;
						if( !empty( $args ) ) {
							$args[ 0 ] = $result;
						}
					}
					if( $result === false ) {
						break;
					}
				}
			}
			return $result;
		}

		function run() {
			$this->trigger( 'start' );
			try {
				$this->proceed();
			} catch( Exception $ex ) {
				$this->trigger( 'error' );
			}
		}

		function end( $response = '' ) {
			$response = $this->trigger( 'end', $response, $this->_requestStartTime );
			die( $response );
		}

		public function setAuthProvider( IAuth $auth ) {
			$this->_auth = $auth;
			return $this;
		}

		public function setDbConfig( IDbConfig $config ) {
			Db::setConfig( $config );
			return $this;
		}

		public function setEventConfig( IEventConfig $config ) {
			$this->_eventConfig = $config;
			return $this;
		}

		public function getAuthProvider() {
			return $this->_auth;
		}

		public function isAuthorized() {
			$this->debug( 'Application authorization', $this->_auth, $this->_auth->getUser(), $_SERVER );
			return $this->_auth && $this->_auth->getUser();
		}

		private function proceed() {
			$controllerClass = $this->getConfig( 'defaults/appNamespace', 'Application\\' ) . 'Controller\\' . ucfirst( $this->_route[ 'controller' ] );
			$controller = null;
			if( class_exists( $controllerClass ) ) {
				$controller = new $controllerClass( $this->_route[ 'controller' ], $this->_route[ 'action' ] );
			} else {
				$errorControllerClass = $this->getConfig( 'defaults/appNamespace' ) . 'Controller\\' . ucfirst( $this->getConfig( 'defaults/errorController', 'error' ) );
				if( class_exists( $errorControllerClass ) ) {
					$this->_route[ 'controller' ] = $this->getConfig( 'defaults/errorController', 'error' );
					$this->_route[ 'action' ] = $this->getConfig( 'defaults/action', 'main' );
					$controller = new $errorControllerClass( $this->_route[ 'controller' ], $this->_route[ 'action' ] );
				} else {
					throw new \Exception( 'Unable to find the controller!' );
				}
			}
			$this->debug( $controller );

			if( strtolower( $_SERVER[ 'REQUEST_METHOD' ] ) != 'get' && is_callable( [ $controller, $this->_route[ 'action' ] . ucfirst( $_SERVER[ 'REQUEST_METHOD' ] ) . 'Action' ] ) ) {
				$this->_route[ 'params' ][] = $_REQUEST;
				$result = call_user_func_array( [ $controller, $this->_route[ 'action' ] . ucfirst( $_SERVER[ 'REQUEST_METHOD' ] ) . 'Action' ], $this->_route[ 'params' ] );
				$this->end( $result );
			} else if( is_callable( [ $controller, $this->_route[ 'action' ] . 'Action' ] ) ) {
				$result = call_user_func_array( [ $controller, $this->_route[ 'action' ] . 'Action' ], $this->_route[ 'params' ] );
				$this->end( $result );
			} else {
				$errorControllerClass = $this->getConfig( 'defaults/appNamespace' ) . 'Controller\\' . ucfirst( $this->getConfig( 'defaults/errorController', 'error' ) );
				if( class_exists( $errorControllerClass ) ) {
					$this->_route[ 'controller' ] = $this->getConfig( 'defaults/errorController', 'error' );
					$this->_route[ 'action' ] = $this->getConfig( 'defaults/action', 'main' );
					$controller = new $errorControllerClass( $this->_route[ 'controller' ], $this->_route[ 'action' ] );
				} else {
					throw new \Exception( 'Unable to find the error controller to show action not found error!' );
				}
			}
		}

		function setConfig( $config ) {
			$this->_config = is_string( $config ) ? require $config : $config;
			$this->debug( 'Config', $this->_config );
			$this->loadPlugins();
			$this->defineRoute();
			return $this;
		}

		function loadPlugins() {
			$plugins = $this->getConfig( 'plugins' );
			if( !empty( $plugins ) ) {
				foreach( $plugins as $pluginClass ) {
					( new $pluginClass() )->activate();
				}
			}
		}

		public function getConfig( $key = null, $default = null ) {
			if( is_null( $key ) ) {
				return $this->_config;
			}
			$key = explode( '/', $key );
			$returnVal = $this->_config;
			foreach( $key as $part ) {
				if( isset( $returnVal[ $part ] ) ) {
					$returnVal = $returnVal[ $part ];
				} else {
					if( is_a( $default, 'Exception' ) ) {
						throw $default;
					}
					return $default;
				}
			}
			return $returnVal;
		}

		public function defineRoute() {
			global $argv;
			$url = explode( '/', trim( explode( '?', defined( 'STDOUT' ) ? ( isset( $argv[ 1 ] ) ? $argv[ 1 ] : '' ) : $_SERVER[ 'REQUEST_URI' ], 2 )[ 0 ], '/' ) );
			$url = explode( '/', $this->trigger( 'beforeRouting', implode( '/', $url ) ) );

			$this->_route[ 'controller' ] = empty( $url[ 0 ] ) ? $this->getConfig( 'defaults/controller', 'home' ) : Helper::slugToCamel( $url[ 0 ] );
			$this->_route[ 'action' ] = isset( $url[ 1 ] ) ? Helper::slugToCamel( $url[ 1 ] ) : $this->getConfig( 'defaults/action', 'main' );
			unset( $url[ 0 ], $url[ 1 ] );
			$this->debug( 'route', $this->_route );
			$this->_route[ 'params' ] = $url;
			$this->_route = $this->trigger( 'afterRouting', $this->_route );
		}
	}