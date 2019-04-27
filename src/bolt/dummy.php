<?php

	namespace Bolt;

	class Dummy {

		protected $_properties = [];

		function __get( $prop ) {
			return isset( $this->_properties[ $prop ] ) ? $this->_properties[ $prop ] : '';
		}

		function __set( $prop, $val ) {
			$this->_properties[ $prop ] = $val;
		}

		function __call( $func, $args ) {
			$prop = 'unknown';
			if( $func == 'get' && isset( $args[ 0 ] ) ) {
				$prop = $args[ 0 ];
			} else if( substr( $func, 0, 3 ) == 'get' ) {
				$prop = lcfirst( substr( $func, 3 ) );
			}
			return $this->$prop;
		}
	}