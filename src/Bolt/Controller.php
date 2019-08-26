<?php

	namespace Bolt;

	abstract class Controller extends Component {

		protected $_layout;
		protected $_name;
		protected $_viewBag;
		protected $_authorize = false;
		protected $_roles = [];
		protected $_assets = [ 'css' => [], 'js' => [] ];
		protected $_title = '';
		protected $_action;
		public $request;
		public $path;
		public $model;
		public $template;
		public $html;

		function title() {
			return empty( $this->_title ) ? @end( explode( '\\', get_class( $this ) ) ) . ' ' . $this->_action : $this->_title;
		}

		protected function beforeExecute() {}
		protected function beforeRender() {}

		function __construct( $name, $action ) {
			$this->_name = $name;
			$this->_action = $action;
			$this->request = new Request();
			$this->_viewBag = new \StdClass();
			$this->path = Application::getInstance()->getConfig( 'defaults/viewPath', 'application/view/' );
			$this->debug( 'Checking authorization', Application::getInstance()->isAuthorized() );
			if( $this->_authorize ) {
				$this->debug( 'checking authorization', Application::getInstance()->isAuthorized() );
				if( !Application::getInstance()->isAuthorized() ) {
					$this->redirect( Application::getInstance()->getConfig( 'defaults/loginPath', 'account/login' ) . '?next=' . urlencode( $_SERVER[ 'REQUEST_URI' ] ), true );
				} else if( !empty( $this->_roles ) ) {
					if( empty( array_intersect( $this->_roles, $this->getUserRoles() ) ) ) {
						$this->redirect( Application::getInstance()->getConfig( 'defaults/loginPath', 'account/login' ) );
					}
				}
			}
			$this->beforeExecute();
		}

		function __set( $prop, $val ) {
			$this->_viewBag->$prop = $val;
		}

		protected function view( $model = null, $options = [] ) {
			if( isset( $options[ 'view' ] ) ) {
				$this->_action = $options[ 'view' ];
			}
			$this->_action = strtolower( $this->_action );
			$this->template = Application::getInstance()->getConfig( 'defaults/viewPath', 'application/view/' ) . $this->_name . '/' . $this->_action . '.' . $this->getConfig( 'view/extension', 'html' );
			$this->model = $model;
			$this->html = new Html( $model );
			$renderer = $this->trigger( 'getRenderer' );
			$this->beforeRender();
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

		protected function htmlCss( $file, $position = null ) {
			is_null( $position ) ? array_push( $this->_assets[ 'css' ], $file ) : array_splice( $this->_assets[ 'css' ], $position, 0, $file );
		}

		protected function htmlJs( $file, $position = null ) {
			is_null( $position ) ? array_push( $this->_assets[ 'js' ], $file ) : array_splice( $this->_assets[ 'js' ], $position, 0, $file );
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

		function csrf() {
			$_SESSION[ 'CSRF_TOKEN' ] = substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ), 0, 20 );
			return '<input type="hidden" name="CSRF_TOKEN" value="' . $_SESSION[ 'CSRF_TOKEN' ] . '" />';
		}
	}
