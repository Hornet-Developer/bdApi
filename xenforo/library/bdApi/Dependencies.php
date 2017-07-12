<?php

class bdApi_Dependencies_Base extends XenForo_Dependencies_Public
{
    public function preLoadData()
    {
        // trigger auto loading of our classes
        class_exists('bdApi_Link');

        if (isset($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
        ) {
            class_exists('bdApi_Input');
            class_exists('bdApi_Upload');
        }

        $this->_dataPreLoadFromRegistry += array(// TODO
        );

        XenForo_CodeEvent::addListener('load_class_model', array('bdApi_Listener', 'extend'));

        // XenForo 1.2.0+ only
        XenForo_CodeEvent::addListener('load_class', array('bdApi_Listener', 'extend'), 'XenForo_Visitor');

        parent::preLoadData();
    }

    protected function _handleCustomPreloadedData(array &$data)
    {
        // setup our routes
        $routes = array();
        bdApi_Route_PrefixApi::setupRoutes($routes);
        XenForo_Link::setHandlerInfoForGroup('api', $routes);

        parent::_handleCustomPreloadedData($data);
    }

    public function getNotFoundErrorRoute()
    {
        return array(
            'bdApi_ControllerApi_Error',
            'ErrorNotFound'
        );
    }

    public function getServerErrorRoute()
    {
        return array(
            'bdApi_ControllerApi_Error',
            'ErrorServer'
        );
    }

    public function getViewRenderer(
        Zend_Controller_Response_Http $response,
        $responseType,
        Zend_Controller_Request_Http $request
    ) {
        switch ($responseType) {
            case 'jsonp':
                return new bdApi_ViewRenderer_Jsonp($this, $response, $request);
            case 'raw':
                return new XenForo_ViewRenderer_Raw($this, $response, $request);
            default:
                // because the oauth2-php library only supports JSON
                // it makes little sense for us to support anything else
                // so for now, we will only use the JSON renderer...
                // TODO: support XML?
                return new bdApi_ViewRenderer_Json($this, $response, $request);
        }
    }

    public function getBaseViewClassName()
    {
        return 'bdApi_ViewApi_Base';
    }

    protected function _bdApi_reRoute(Zend_Controller_Request_Http $request, XenForo_RouteMatch $routeMatch)
    {
        if (!empty($routeMatch)) {
            $controllerName = $routeMatch->getControllerName();

            switch ($controllerName) {
                case 'bdApi_ControllerApi_Error':
                    // ignore
                    break;
                default:
                    $action = $routeMatch->getAction();
                    if (empty($action)) {
                        $action = 'index';
                    }

                    $method = strtolower($request->getMethod());
                    if ($method === 'head') {
                        $method = 'get';
                    }

                    if ($method === 'options') {
                        $routeMatch->setAction('options');
                        $request->setParam('action', $action);
                    } else {
                        $routeMatch->setAction($method . '-' . $action);
                    }
            }
        }

        return $routeMatch;
    }
}

if (XenForo_Application::$versionId >= 1020000) {
    class bdApi_Dependencies extends bdApi_Dependencies_Base
    {
        public function route(Zend_Controller_Request_Http $request, $routePath = null)
        {
            $routeMatch = parent::route($request, $routePath);

            $routeMatch = $this->_bdApi_reRoute($request, $routeMatch);

            return $routeMatch;
        }

        public function getRouter()
        {
            $router = new XenForo_Router();
            $router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix');
            $router->addRule(new bdApi_Route_PrefixApi('api'), 'Prefix');

            return $router;
        }

        public function routePublic(Zend_Controller_Request_Http $request, $routePath = null)
        {
            return parent::getRouter()->match($request, $routePath);
        }
    }

} else {
    class bdApi_Dependencies extends bdApi_Dependencies_Base
    {

        /** @noinspection PhpSignatureMismatchDuringInheritanceInspection
         * @param Zend_Controller_Request_Http $request
         * @return bool|XenForo_RouteMatch
         */
        public function route(Zend_Controller_Request_Http $request)
        {
            $router = new XenForo_Router();
            $router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix');
            $router->addRule(new bdApi_Route_PrefixApi('api'), 'Prefix');

            $routeMatch = $router->match($request);

            $routeMatch = $this->_bdApi_reRoute($request, $routeMatch);

            return $routeMatch;
        }

        public function routePublic(Zend_Controller_Request_Http $request)
        {
            return parent::route($request);
        }
    }

}
