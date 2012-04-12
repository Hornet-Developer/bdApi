<?php

class bdApi_Route_Prefix extends XenForo_Route_Prefix
{
	public function __construct($routeType)
	{
		$this->_routeType = $routeType;
	}
	
	/**
	 * Setups all default routes for [bd] Api. Also fires the code event
	 * `bdapi_setup_routes` and let any other add-ons to setup extra
	 * routes for the system.
	 * 
	 * @param array $routes the target routes array
	 */
	public static function setupRoutes(array &$routes)
	{
		self::addRoute($routes, 'index', 'bdApi_Route_Prefix_Index');
		
		self::addRoute($routes, 'users', 'bdApi_Route_Prefix_Users', 'data_only');
		self::addRoute($routes, 'nodes', 'bdApi_Route_Prefix_Nodes', 'data_only');
		self::addRoute($routes, 'posts', 'bdApi_Route_Prefix_Posts', 'data_only');
		self::addRoute($routes, 'threads', 'bdApi_Route_Prefix_Threads', 'data_only');
		
		XenForo_CodeEvent::fire('bdapi_setup_routes', array(&$routes));
	}
	
	/**
	 * Helper method to easily add new route to a routes array.
	 * 
	 * @param array $routes the target routes array
	 * @param string $originalPrefix
	 * @param string $routeClass
	 * @param string $buildLink
	 */
	public static function addRoute(array &$routes, $originalPrefix, $routeClass, $buildLink = 'none')
	{
		$routes[$originalPrefix] = array(
			'route_class' => $routeClass,
			'build_link' => $buildLink,
		);
	}
}