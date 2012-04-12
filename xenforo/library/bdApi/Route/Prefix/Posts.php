<?php

class bdApi_Route_Prefix_Posts extends bdApi_Route_Prefix_Abstract
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'post_id');
		return $router->getRouteMatch('bdApi_ControllerApi_Post', $action);
	}
	
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'post_id');
	}
}