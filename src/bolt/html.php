<?php

	namespace Bolt;

	class Html extends Component {

		private $_model;
		private $_useBootstrap = true;

		function __construct( $model ) {
			$this->setModel( $model );
		}

		function setModel( $model ) {
			$this->_model = is_array( $model ) ? (object) $model : $model;
			return $this;
		}

		function useBootstrap( $bool ) {
			$this->_useBootstrap = $bool;
			return $this;
		}

		private function makeAttributes( $attrs ) {
			if( $this->_useBootstrap ) {
				$attrs[ 'class' ] = isset( $attrs[ 'class' ] ) ? $attrs[ 'class' ] . ' form-control' : 'form-control';
			}
			$attributes = [];
			foreach( $attrs as $key => $val ) {
				$attributes[] = $key . '="' . htmlspecialchars( $val ) . '"';
			}
			return empty( $attributes ) ? '' : implode( ' ', $attributes );
		}

		private function createLabel( $name ) {
			return ucwords( str_replace( '_', ' ', Helper::camelToUnderScore( trim( str_replace( [ '[]', '[', ']' ], ' ', $name ) ) ) ) );
		}

		private function getValue( $name ) {
			return property_exists( $this->_model, 'name' ) ? $this->_model : '';
		}

		private function createOptions( $options, $label ) {
			$result = $label ? '<option value="">' . $label . '</option>' : '';
			if( is_array( $options ) ) {
				if( ( is_array( $options[ 0 ] ) || is_object( $options[ 0 ] ) ) && count( $options ) == 3 && is_string( $options[ 1 ] ) && is_string( $options[ 2 ] ) ) {
					if( is_array( $options[ 0 ] ) || is_a( $options[ 0 ], 'ArrayAccess' ) ) {
						$value = $options[ 1 ];
						$text = $options[ 2 ];
						foreach( $options[ 0 ] as $option ) {
							$result += '<option value="' . htmlspecialchars( is_array( $option ) ? $options[ $value ] : $options->$value ) . '">' . ( is_array( $option ) ? $options[ $text ] : $options->$text ) . '</option>';
						}
					}
				} else {
					foreach( $options as $option ) {
						if( is_string( $option ) ) {
							$result += '<option value="' . htmlspecialchars( $option ) . '">' . $option . '</option>';
						} else if( isset( $option[ 0 ], $option[ 1 ] ) ) {
							$result += '<option value="' . htmlspecialchars( $option[ 0 ] ) . '">' . $option[ 1 ] . '</option>';
						} else if( isset( $option[ 'value' ], $option[ 'text' ] ) ) {
							$result += '<option value="' . htmlspecialchars( $option[ 'value' ] ) . '">' . $option[ 'text' ] . '</option>';
						} else if( isset( $option[ 0 ] ) ) {
							$result += '<option value="' . htmlspecialchars( $option[ 0 ] ) . '">' . $option[ 0 ] . '</option>';
						}
					}
				}
			}
			return $result;
		}

		function input( $name, $attrs = [], $label = '', $value = null ) {
			if( !isset( $attrs[ 'type' ] ) ) {
				$attrs[ 'type' ] = 'text';
			}
			if( !is_null( $label ) ) {
				$label = $label ? $label : $this->createLabel( $name );
			}
			$attrs[ 'placeholder' ] = $label;
			$attrs[ 'id' ] = str_replace( [ '[', ']' ], '_', $name ) . 'Input';
			$attrs[ 'value' ] = is_null( $value ) ? $this->getValue( $name ) : $value;
			return ( is_null( $label ) ? '' : '<label for="' . $attrs[ 'id' ] . 'Input">' . ( $label ? $label : $this->createLabel( $name ) ) . ( isset( $attrs[ 'required' ] ) ? ' *' : '' ) . '</label>' ) . '<input name="' . $name . '"' . $this->makeAttributes( $attrs ) . ' />';
		}

		function select( $name, $options, $attrs = [], $label = '', $value = null ) {
			if( !is_null( $label ) ) {
				$label = $label ? $label : $this->createLabel( $name );
			}
			$options = $this->createOptions( $options, $label );
			return ( is_null( $label ) ? '' : '<label for="' . $attrs[ 'id' ] . 'Input">' . ( $label ? $label : $this->createLabel( $name ) ) . ( isset( $attrs[ 'required' ] ) ? ' *' : '' ) . '</label>' ) . '<select name="' . $name . '"' . $this->makeAttributes( $attrs ) . '>' . $options . '</select>';
		}

		function textarea( $name, $attrs = [], $label = '', $value = null ) {
			if( !isset( $attrs[ 'type' ] ) ) {
				$attrs[ 'type' ] = 'text';
			}
			if( !is_null( $label ) ) {
				$label = $label ? $label : $this->createLabel( $name );
			}
			$attrs[ 'placeholder' ] = $label;
			$attrs[ 'id' ] = str_replace( [ '[', ']' ], '_', $name ) . 'Input';
			return ( is_null( $label ) ? '' : '<label for="' . $attrs[ 'id' ] . 'Input">' . ( $label ? $label : $this->createLabel( $name ) ) . ( isset( $attrs[ 'required' ] ) ? ' *' : '' ) . '</label>' ) . '<textarea name="' . $name . '"' . $this->makeAttributes( $attrs ) . '>' . ( is_null( $value ) ? $this->getValue( $name ) : $value ) . '</textarea>';
		}
	}
