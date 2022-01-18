<?php

namespace Bolt;

class Application extends Component
{

    private static $_instance;

    private array $_config;
    private array $_route = [];
    private ?IAuth $_auth;
    private array $_events = [];
    private float $_requestStartTime;

    private function __construct()
    {
        $this->_auth = null;
        $this->_requestStartTime = microtime(true);
    }

    public static function getInstance(): Application
    {
        return self::$_instance ? self::$_instance : (self::$_instance = new self());
    }

    public function subscribe(string $event, callable $handler):void
    {
        if (is_callable($handler)) {
            $this->_events[$event][] = $handler;
        } else {
            throw new Exception(print_r($handler, true) . ' is not a valid callback for an event!');
        }
    }

    public function trigger():mixed
    {
        $args = func_get_args();
        $result = isset($args[1]) ? $args[1] : null;
        if (isset($this->_events[$args[0]]) && !empty($this->_events[$args[0]])) {
            $event = $args[0];
            unset($args[0]);
            $args = array_values($args);
            foreach ($this->_events[$event] as $handler) {
                $resp = call_user_func_array($handler, $args);
                if (!is_null($resp)) {
                    $result = $resp;
                    if (!empty($args)) {
                        $args[0] = $result;
                    }
                }
                if ($result === false) {
                    break;
                }
            }
        }
        return $result;
    }

    public function run()
    {
        $this->trigger('start');
        @session_start();
        session_regenerate_id();
        try {
            $this->proceed();
        } catch (Exception $ex) {
            $this->trigger('error');
        }
    }

    public function end(?string $response = '')
    {
        $response = $this->trigger('end', $response, $this->_requestStartTime);
        echo $response;
    }

    public function setAuthProvider(IAuth $auth)
    {
        $this->_auth = $auth;
        return $this;
    }

    public function getAuthProvider(): IAuth
    {
        return $this->_auth;
    }

    public function isAuthorized(): bool
    {
        if(is_null($this->_auth)) {
            return false;
        }
        $this->debug('Application authorization', $this->_auth, !is_null($this->_auth) && $this->_auth->getUser(), $_SERVER);
        return !is_null($this->_auth) && !!$this->_auth->getUser();
    }

    private function proceed()
    {
        $controllerClass = $this->getConfig(Constant::CONFIG_APP_NAMESPACE, 'Application\\') . 'Controller\\' . ucfirst($this->_route['controller']);
        $controller = null;
        if (class_exists($controllerClass)) {
            $controller = new $controllerClass($this->_route['controller'], $this->_route['action']);
        } else {
            $errorControllerClass = $this->getConfig(Constant::CONFIG_APP_ERROR_CONTROLLER) . 'Controller\\' . ucfirst($this->getConfig(Constant::CONFIG_APP_ERROR_CONTROLLER, 'error'));
            if (class_exists($errorControllerClass)) {
                $this->_route['controller'] = $this->getConfig(Constant::CONFIG_APP_ERROR_CONTROLLER, 'error');
                $this->_route['action'] = $this->getConfig(Constant::CONFIG_DEFAULT_ACTION, 'main');
                $controller = new $errorControllerClass($this->_route['controller'], $this->_route['action']);
            } else {
                throw new Exception('Unable to find the controller!');
            }
        }
        $this->debug($controller, $this->_route);

        if (strtolower($_SERVER['REQUEST_METHOD']) != 'get' && method_exists($controller, $this->_route['action'] . ucfirst($_SERVER['REQUEST_METHOD']) . 'Action')) {
            $this->_route['params'][] = $_REQUEST;
            $this->end(call_user_func_array([$controller, $this->_route['action'] . ucfirst($_SERVER['REQUEST_METHOD']) . 'Action'], $this->_route['params']));
        } else if (method_exists($controller, $this->_route['action'])) {
            $this->end(call_user_func_array([$controller, $this->_route['action']], $this->_route['params']));
        } else {
            header('HTTP/1.0 404 Not Found', true, 404);
            $errorControllerClass = $this->getConfig(Constant::CONFIG_APP_NAMESPACE) . 'Controller\\' . ucfirst($this->getConfig(Constant::CONFIG_APP_ERROR_CONTROLLER, 'error'));
            if (class_exists($errorControllerClass)) {
                $this->_route['controller'] = $this->getConfig(Constant::CONFIG_APP_ERROR_CONTROLLER, 'error');
                $this->_route['action'] = $this->getConfig(Constant::CONFIG_DEFAULT_ACTION, 'main');
                $controller = new $errorControllerClass($this->_route['controller'], $this->_route['action']);
                if (method_exists($controller, $this->_route['action'])) {
                    $this->end(call_user_func_array([$controller, $this->_route['action']], ['Unable to find the route!']));
                }
            }
        }
        throw new Exception('Unable to find the error controller to show action not found error!');
    }

    public function setConfig($config)
    {
        if(!is_string($config)) {
            $this->_config = $config;
        } else {
            $this->_config = file_exists($config) ? parse_ini_file($config) : [];
        }
        $this->debug('Config', $this->_config);
        $this->loadPlugins();
        $this->defineRoute();
        return $this;
    }

    public function loadPlugins()
    {
        $plugins = array_filter(explode(',', $this->getConfig(Constant::CONFIG_PLUGINS)));
        if (!empty($plugins)) {
            foreach ($plugins as $pluginClass) {
                if (class_exists($pluginClass)) {
                    (new $pluginClass())->activate();
                }
            }
        }
        $this->trigger('pluginsLoaded');
    }

    public function getConfig($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->_config;
        }
        $key = explode('/', $key);
        $returnVal = $this->_config;
        foreach ($key as $part) {
            if (isset($returnVal[$part])) {
                $returnVal = $returnVal[$part];
            } else {
                if (is_callable($default)) {
                    return call_user_func($default);
                }
                if (is_a($default, 'Exception')) {
                    throw $default;
                }
                return $default;
            }
        }
        return $returnVal;
    }

    public function defineRoute()
    {
        global $argv;
        if (defined('STDOUT')) {
            $_SERVER['REQUEST_METHOD'] = 'CLI';
        }
        $url = explode('/', trim(explode('?', defined('STDOUT') ? (isset($argv[1]) ? $argv[1] : '') : $_SERVER['REQUEST_URI'], 2)[0], '/'));
        $url = explode('/', $this->trigger('beforeRouting', implode('/', $url)));

        $this->_route['controller'] = empty($url[0]) ? $this->getConfig('defaults/controller', 'home') : Helper::slugToCamel($url[0]);
        $this->_route['action'] = isset($url[1]) ? Helper::slugToCamel($url[1]) : $this->getConfig(Constant::CONFIG_DEFAULT_ACTION, 'main');
        unset($url[0], $url[1]);
        $this->debug('route', $this->_route);
        $this->_route['params'] = array_map('urldecode', $url);
        $this->_route = $this->trigger('afterRouting', $this->_route);
    }
}
