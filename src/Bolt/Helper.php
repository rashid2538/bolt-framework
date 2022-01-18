<?php

	namespace Bolt;

	abstract class Helper {

		static function slugToCamel(string $str ):string {
			return str_replace( ' ', '', lcfirst( ucwords( str_replace( '-', ' ', $str ) ) ) );
		}

		static function camelToUnderScore(string $str ):string {
			$result = '';
			for( $i = 0; $i < strlen( $str ); $i++ ) {
				$char = substr( $str, $i, 1 );
				$result .= strtolower( $char ) == $char ? $char : '_' . strtolower( $char );
			}
			return $result;
		}
	}