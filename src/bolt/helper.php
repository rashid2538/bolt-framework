<?php

	namespace Bolt;

	abstract class Helper {

		static function slugToCamel( $str ) {
			return lcfirst( ucwords( str_replace( '-', ' ', $str ) ) );
		}

		static function camelToUnderScore( $str ) {
			$result = '';
			for( $i = 0; $i < strlen( $str ); $i++ ) {
				$char = substr( $str, $i, 1 );
				$result .= strtolower( $char ) == $char ? $char : '_' . strtolower( $char );
			}
			return $result;
		}
	}