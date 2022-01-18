<?php

	namespace Bolt;

	abstract class DbTable extends Component {

		protected string $_name;

		function getTableName():string {
			return $this->getConfig(Constant::CONFIG_DB_PREFIX) . Helper::camelToUnderScore( $this->_name );
		}
	}