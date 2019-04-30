<?php

	namespace Bolt;

	class Request extends Component {

		function isPost() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'POST';
		}

		function get( $prop = null ) {
			return is_null( $prop ) ? $_GET : ( isset( $_GET[ $prop ] ) ? $_GET[ $prop ] : null );
		}

		function post( $prop = null ) {
			return is_null( $prop ) ? $_POST : ( isset( $_POST[ $prop ] ) ? $_POST[ $prop ] : null );
		}
	}
