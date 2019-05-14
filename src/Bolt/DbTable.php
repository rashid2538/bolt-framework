<?php

	namespace Bolt;

	abstract class DbTable extends Component {

		protected $_name;

		function getTableName() {
			return $this->getConfig( 'db/prefix' ) . Helper::camelToUnderScore( $this->_name );
		}
	}