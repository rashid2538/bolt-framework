<?php

	namespace Bolt;

	interface IDbConfig {
		function getType();
		function getHost();
		function getDatabase();
		function getUser();
		function getPassword();
		function getPrefix();
	}