<?php

namespace Bolt;

abstract class Controller extends Component
{

    protected $_layout;
    protected $_name;
    protected $_viewBag;
    protected $_authorize = false;
    protected $_roles = [];
    protected $_assets = ['css' => [], 'js' => []];
    protected $_title = '';
    protected $_action;
    public $request;
    public $path;
    public $model;
    public $template;
    public $html;
    public $assetsVersion = '1.0';

    public function title():string
    {
        return empty($this->_title) ? @end(explode('\\', get_class($this))) . ' ' . $this->_action : $this->_title;
    }

    protected function beforeExecute():void
    {}

    protected function beforeRender():void
    {}

    public function __construct(string $name, string $action)
    {
        $this->_name = $name;
        $this->_action = $action;
        $this->request = new Request();
        $this->_viewBag = new \StdClass();
        $this->path = Application::getInstance()->getConfig(Constant::CONFIG_VIEW_PATH);
        $this->debug('Checking authorization', Application::getInstance()->isAuthorized());
        if ($this->_authorize) {
            $this->debug('checking authorization', Application::getInstance()->isAuthorized());
            if (!Application::getInstance()->isAuthorized()) {
                $this->redirect(Application::getInstance()->getConfig(Constant::CONFIG_LOGIN_PATH) . '?next=' . urlencode($_SERVER['REQUEST_URI']), true);
            } else if (!empty($this->_roles) && empty(array_intersect($this->_roles, $this->getUserRoles()))) {
                $this->redirect(Application::getInstance()->getConfig(Constant::CONFIG_LOGIN_PATH));
            }
        }
        $this->beforeExecute();
    }

    public function __set(string $prop, mixed $val):void
    {
        $this->_viewBag->$prop = $val;
    }

    protected function view(mixed $model = null, array $options = []):string
    {
        if (isset($options['view'])) {
            $this->_action = $options['view'];
        }
        $this->_action = strtolower($this->_action);
        $this->template = Application::getInstance()->getConfig(Constant::CONFIG_VIEW_PATH) . $this->_name . '/' . $this->_action . '.' . $this->getConfig(Constant::CONFIG_VIEW_EXTENSTION, 'html');
        $this->model = $model;
        $this->html = new Html($model);
        $renderer = $this->trigger('getRenderer');
        $this->beforeRender();
        return $renderer && is_a($renderer, '\\Closure') ? \Closure::bind($renderer, $this)->__invoke() : $this->_render();
    }

    private function _render():string
    {
        ob_start();
        $this->template = Application::getInstance()->getConfig(Constant::CONFIG_VIEW_PATH) . $this->_name . '/' . $this->_action . '.' . $this->getConfig(Constant::CONFIG_VIEW_EXTENSTION, 'html');
        include $this->_layout ? Application::getInstance()->getConfig(Constant::CONFIG_VIEW_PATH) . $this->_layout . '.' . $this->getConfig(Constant::CONFIG_VIEW_EXTENSTION, 'html') : Application::getInstance()->getConfig(Constant::CONFIG_VIEW_PATH) . $this->_name . '/' . $this->_action . '.' . $this->getConfig(Constant::CONFIG_VIEW_EXTENSTION, 'html');
        $page = ob_get_contents();
        ob_end_clean();
        return $page;
    }

    // 200
    protected function ok($response):string
    {
        return $response;
    }

    // 200
    protected function json(mixed $response):string
    {
        header('Content-Type: application/json');
        return is_string($response) ? $response : json_encode($response);
    }

    // 500
    protected function internalError(\Exception $ex = null):string
    {
        header('HTTP/1.1 500 Internal Server Error');
        if ($ex) {
            return $ex->getMessage() . "\n" . $ex->getTraceAsString();
        }
        return '500 Internal Server Error';
    }

    // 403
    protected function unauthorized(\Exception $ex = null)
    {
        header('HTTP/1.1 403 Unauthorized');
        if ($ex) {
            return $ex->getMessage() . "\n" . $ex->getTraceAsString();
        }
        return '403 Unauthorized';
    }

    // 404
    protected function notFound(string $resp = ''):string
    {
        header('HTTP/1.1 404 Not Found');
        return $resp;
    }

    protected function htmlCss(string $file, int $position = null):void
    {
        is_null($position) ? array_push($this->_assets['css'], $file) : array_splice($this->_assets['css'], $position, 0, $file);
    }

    protected function htmlJs(string $file,int $position = null):void
    {
        is_null($position) ? array_push($this->_assets['js'], $file) : array_splice($this->_assets['js'], $position, 0, $file);
    }

    public function renderCss():string
    {
        $result = [];
        foreach ($this->_assets['css'] as $styleSheet) {
            $path = (count(explode('://', $styleSheet, 2)) > 1) || (substr($styleSheet, 0, 2) == '//') ? $styleSheet : $this->url() . $styleSheet . '.css?v=' . $this->assetsVersion;
            $result[] = '<link rel="stylesheet" href="' . $path . '" />';
        }
        return implode("\n\t\t", $result);
    }

    public function renderJs():string
    {
        $result = [];
        foreach ($this->_assets['js'] as $script) {
            $path = (count(explode('://', $script, 2)) > 1) || (substr($script, 0, 2) == '//') ? $script : $this->url() . $script . '.js?v=' . $this->assetsVersion;
            $result[] = '<script src="' . $path . '"></script>';
        }
        return implode("\n\t\t", $result);
    }

    public function csrf():string
    {
        $_SESSION['CSRF_TOKEN'] = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 20);
        return '<input type="hidden" name="CSRF_TOKEN" value="' . $_SESSION['CSRF_TOKEN'] . '" />';
    }
}
