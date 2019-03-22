<?php

	namespace Bolt;

	interface IEventConfig extends Component {
		/// application related
		// start
		// configLoaded
		// beforeRouting
		// afterRouting
		// beforeResponse
		// end

		/// database related
		// databaseConnected
		// databaseError
		// queryExecuted
		// beforeUpdate
		// beforeSelect
		// beforeInsert
		// beforeDelete
		// afterUpdate
		// afterSelect
		// afterInsert
		// afterDelete
		// databaseDisconnected
	}