<?php

	namespace Bolt;

	class Request extends Component {

		function isPost():bool {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'POST';
		}

		function get(string $prop = null ):mixed {
			if(is_null( $prop )) {
				return $_GET;
			}
			return isset( $_GET[ $prop ] ) ? $_GET[ $prop ] : null;
		}

		function post(string $prop = null ):array {
			if(is_null( $prop )) {
				return $_POST;
			}
			return isset( $_POST[ $prop ] ) ? $_POST[ $prop ] : null;
		}

		function sanitize(array $data, array $properties ):array {
			$result = [];
			foreach( $properties as $property ) {
				if(!isset( $data[ $property ] )) {
					$result[ $property ] = null;
				} else {
					$result[ $property ] = empty( $data[ $property ] ) ? null : $data[ $property ];
				}
			}
			return $result;
		}
	}
