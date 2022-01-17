<?php

	namespace Bolt;

	abstract class DbTable extends Component {

		protected $_name;

		function getTableName() {
			return $this->getConfig(Constant::CONFIG_DB_PREFIX) . Helper::camelToUnderScore( $this->_name );
		}
	}