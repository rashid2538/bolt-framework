<?php

namespace Bolt;

abstract class Constant {

    const CONFIG_APP_DEBUG = 'app.debug';
    const CONFIG_APP_NAMESPACE = 'app.namespace';
    const CONFIG_APP_ERROR_CONTROLLER = 'app.error.controller';
    const CONFIG_DEFAULT_ACTION = 'app.action';
    const CONFIG_DEFAULT_BASE = 'app.base';
    const CONFIG_VIEW_PATH = 'app.view.path';
    const CONFIG_PLUGINS = 'app.plugins';
    const CONFIG_LOGIN_PATH = 'app.login.path';
    const CONFIG_VIEW_EXTENSTION = 'app.view.extension';

    const CONFIG_DB_HOST = 'db.host';
    const CONFIG_DB_DATABASE = 'db.database';
    const CONFIG_DB_USER = 'db.user';
    const CONFIG_DB_PASSWORD = 'db.password';
    const CONFIG_DB_PREFIX = 'db.prefix';

    const EVENT_PLUGINS_LOADED = 'pluginsLoaded';
}