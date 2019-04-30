<?php

	namespace Bolt;

	class Dummy implements \ArrayAccess {

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

		function offsetExists( $offset ) {
			return isset( $this->_properties[ $offset ] );
		}

		function offsetGet( $offset ) {
			return isset( $this->_properties[ $offset ] ) ? $this->_properties[ $offset ] : null;
		}

		function offsetSet( $offset, $value ) {
			$this->_properties[ $offset ] = $value;
		}

		function offsetUnset( $offset ) {
			unset( $this->_properties[ $offset ] );
		}
	}
