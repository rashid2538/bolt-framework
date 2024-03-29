<?php

namespace Bolt;

abstract class Component
{

    private static $_dependencies = [];
    private static $_callbacks = [];

    public function setDependency($name, $value)
    {
        self::$_dependencies[$name] = $value;
        return $this;
    }

    public function url($url = '', $asItIs = false)
    {
        if (empty($url)) {
            return $this->getHomePath();
        }
        if (!$asItIs) {
            $url = $this->trigger('makeUrl', $url);
        }
        $last = @end(explode('/', $url));
        return $this->getHomePath() . $url . ((strpos($last, '.') !== false || $asItIs) ? '' : '/');
    }

    protected function redirect($url, $asItIs = false)
    {
        header('Location: ' . $this->url($url, $asItIs), true, 301);
        Application::getInstance()->end();
    }

    protected function getHomePath()
    {
        $basePath = $this->getConfig(Constant::CONFIG_DEFAULT_BASE);
        if(!empty($basePath)) {
            return $basePath;
        }
        // server protocol
        $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';

        // domain name
        $domain = $_SERVER['SERVER_NAME'];

        // doc root
        $docRoot = str_replace(DIRECTORY_SEPARATOR, '/', preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']));

        // server port
        $port = $_SERVER['SERVER_PORT'];
        $port = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";

        // put em all together to get the complete base URL
        return "{$protocol}://{$domain}{$port}/";
    }

    protected function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $this->getHomePath()) === 0;
    }

    public function __isset($prop)
    {
        return isset(self::$_dependencies[$prop]) || isset(self::$_callbacks[$prop]);
    }

    public function getDependency($prop)
    {
        if (isset(self::$_dependencies[$prop])) {
            if (is_callable(self::$_dependencies[$prop])) {
                self::$_dependencies[$prop] = call_user_func(self::$_dependencies[$prop]);
            }
            return self::$_dependencies[$prop];
        }
    }

    public function setCallback($name, $value)
    {
        if (is_callable($value)) {
            self::$_callbacks[$name] = $value;
        } else {
            throw new Exception("Unable to set callback as it should be a callable!");
        }
        return $this;
    }

    public function __set(string $prop,mixed $val):void
    {
        $this->setDependency($prop, $val);
    }

    public function getMessages()
    {
        $messages = isset($_SESSION['messages']) ? $_SESSION['messages'] : [];
        unset($_SESSION['messages']);
        return $messages;
    }

    public function dummy()
    {
        return new Dummy();
    }

    public function setMessage($message, $type = 'info')
    {
        $_SESSION['messages'][] = [
            'message' => $message,
            'type' => $type,
        ];
        return $this;
    }

    public function __get($prop)
    {
        if (isset(self::$_dependencies[$prop])) {
            if (is_callable(self::$_dependencies[$prop])) {
                self::$_dependencies[$prop] = call_user_func(self::$_dependencies[$prop]);
            }
            return self::$_dependencies[$prop];
        }
        throw new Exception("Call to undefined property '$prop' on '" . __CLASS__ . "'!");
    }

    public function debug()
    {
        if (isset($_GET['myDebug'])) {
            call_user_func_array('var_dump', array_merge([microtime(true), '<pre>'], func_get_args(), ['</pre>']));
        }
    }

    public function getUser()
    {
        $authProvider = Application::getInstance()->getAuthProvider();
        return $authProvider ? $authProvider->getUser() : null;
    }

    public function getUserRoles()
    {
        $authProvider = Application::getInstance()->getAuthProvider();
        return $authProvider ? $authProvider->getUserRoles() : null;
    }

    public function userHasRole($role)
    {
        return in_array($role, $this->getUserRoles());
    }

    public function getConfig()
    {
        return call_user_func_array([Application::getInstance(), 'getConfig'], func_get_args());
    }

    public function usingDb()
    {
        return call_user_func_array('Bolt\\Db::execute', func_get_args());
    }

    public function on()
    {
        return call_user_func_array([Application::getInstance(), 'subscribe'], func_get_args());
    }

    public function trigger()
    {
        return call_user_func_array([Application::getInstance(), 'trigger'], func_get_args());
    }

    public function isValidCsrf()
    {
        return $_SESSION['CSRF_TOKEN'] == $_POST['CSRF_TOKEN'];
    }

    public function __call($func, $args)
    {
        if (empty($args) && substr($func, 0, 3) == 'get') {
            $prop = '_' . lcfirst(substr($func, 3));
            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
        } else if (isset(self::$_callbacks[$func])) {
            return call_user_func_array(self::$_callbacks[$func], $args);
        }
        throw new Exception("Call to undefined function `$func`!");
    }
}
