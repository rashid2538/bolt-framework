<?php

	namespace Bolt;

	abstract class Controller extends Component {

		protected $_layout;
		protected $_name;
		protected $viewBag;
		protected $_authorize = false;
		protected $_roles = [];
		protected $_assets = [ 'css' => [], 'js' => [] ];
		protected $_title = '';
		private $_action;
		public $path;
		public $model;
		public $template;

		function title() {
			return empty( $this->_title ) ? @end( explode( '\\', get_class( $this ) ) ) . ' ' . $this->_action : $this->_title;
		}

		function __construct( $name, $action ) {
			$this->_name = $name;
			$this->_action = $action;
			$this->viewBag = new \StdClass();
			$this->path = Application::getInstance()->getConfig( 'defaults/viewPath', 'application/view/' );
			$this->debug( 'Checking authorization', Application::getInstance()->isAuthorized() );
			if( $this->_authorize ) {
				$this->debug( 'checking authorization', Application::getInstance()->isAuthorized() );
				if( !Application::getInstance()->isAuthorized() ) {
					$this->redirect( Application::getInstance()->getConfig( 'defaults/loginPath', 'account/login' ) );
				} else if( !empty( $this->_roles ) ) {
					$this->redirect( Application::getInstance()->getConfig( 'defaults/loginPath', 'account/login' ) );
				}
			}
		}

		protected function view( $model = null, $options = [] ) {
			if( isset( $options[ 'view' ] ) ) {
				$this->_action = $options[ 'view' ];
			}
			$this->template = Application::getInstance()->getConfig( 'defaults/viewPath', 'application/view/' ) . $this->_name . '/' . $this->_action . '.' . $this->getConfig( 'view/extension', 'html' );
			$this->model = $model;
			$renderer = $this->trigger( 'getRenderer' );
			return $renderer && is_a( $renderer, '\\Closure' ) ? \Closure::bind( $renderer, $this )->__invoke() : $this->_render();
		}

		private function _render() {
			ob_start();
			$this->template = Application::getInstance()->getConfig( 'defaults/viewPath', 'application/view/' ) . $this->_name . '/' . $this->_action . '.' . $this->getConfig( 'view/extension', 'html' );
			include $this->_layout ? Application::getInstance()->getConfig( 'defaults/viewPath', 'application/view/' ) . $this->_layout . '.' . $this->getConfig( 'view/extension', 'html' ) : Application::getInstance()->getConfig( 'defaults/viewPath', 'application/view/' ) . $this->_name . '/' . $this->_action . '.' . $this->getConfig( 'view/extension', 'html' );
			$page = ob_get_contents();
			ob_end_clean();
			return $page;
		}

		function url( $url = '' ) {
			if( empty( $url ) ) {
				return $this->getHomePath();
			}
			if( strpos( $url, '/' ) === false ) {
				$url = $this->_name . '/' . $url;
			}
			return $this->getHomePath() . $url . '/';
		}

		protected function redirect( $url ) {
			header( 'Location: ' . $this->url( $url ), true, 301 );
			Application::getInstance()->end();
		}

		protected function getHomePath() {
			// server protocol
			$protocol = empty( $_SERVER[ 'HTTPS' ] ) ? 'http' : 'https';

			// domain name
			$domain = $_SERVER[ 'SERVER_NAME' ];


			// doc root
			$docRoot = str_replace( DIRECTORY_SEPARATOR, '/', preg_replace( "!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER[ 'SCRIPT_FILENAME' ] ) );

			// base url
			$base_url = $this->getConfig( 'defaults/base', '/' );

			// server port
			$port = $_SERVER['SERVER_PORT'];
			$disp_port = ( $protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443 ) ? '' : ":$port";

			// put em all together to get the complete base URL
			return "${protocol}://${domain}${disp_port}${base_url}";
		}

		protected function isAjaxRequest() {
			return $this->getHeader( 'X-Requested-With' ) == 'XMLHttpRequest';
		}

		// 200
		protected function ok( $response ) {
			return $response;
		}

		// 200
		protected function json( $response ) {
			header( 'Content-Type: application/json' );
			return is_string( $response ) ? $response : json_encode( $response );
		}

		// 500
		protected function internalError( $ex = null ) {
			header( 'HTTP/1.1 500 Internal Server Error' );
			if( $ex ) {
				return $ex->getMessage() . "\n" . $ex->getTraceAsString();
			}
			return '500 Internal Server Error';
		}

		// 403
		protected function unauthorized( $ex = null ) {
			header( 'HTTP/1.1 403 Unauthorized' );
			if( $ex ) {
				return $ex->getMessage() . "\n" . $ex->getTraceAsString();
			}
			return '403 Unauthorized';
		}

		// 404
		protected function notFound( $resp = '' ) {
			header( 'HTTP/1.1 404 Not Found' );
			return $resp;
		}

		function userHasRole( $role ) {
			return in_array( $role, $this->getUserRoles() );
		}

		protected function htmlCss( $file, $position = null ) {
			is_null( $position ) ? array_push( $this->_assets[ 'css' ][], $file ) : array_splice( $this->_assets[ 'css' ], $position, 0, $file );
		}

		protected function htmlJs( $files, $position = null ) {
			is_null( $position ) ? array_push( $this->_assets[ 'js' ][], $file ) : array_splice( $this->_assets[ 'js' ], $position, 0, $file );
		}

		function renderCss() {
			$result = [];
			foreach( $this->_assets[ 'css' ] as $styleSheet ) {
				$result[] = '<link rel="stylesheet" href="' . $this->url() . 'assets/' . $styleSheet . '.css" />';
			}
			return implode( "\n\t\t", $result );
		}

		function renderJs() {
			$result = [];
			foreach( $this->_assets[ 'js' ] as $script ) {
				$result[] = '<script src="' . $this->url() . 'assets/' . $script . '.js"></script>';
			}
			return implode( "\n\t\t", $result );
		}
	}