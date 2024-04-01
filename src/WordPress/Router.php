<?php

namespace WPHTMX\Router\WordPress;

use WPHTMX\Router\Exceptions\TooLateToAddNewRouteException;
use WPHTMX\Router\Router as ParentRouter;

class Router extends ParentRouter
{

	/**
	 * Initialise the WpRouter
	 *
	 * @return void
	 */
	public static function init ()
	{
		$router = self::instance();

		$basePath = '/wp-htmx/';

		$router->setBasePath( $basePath );

		// Give a chance for the outside app to modify the WpRouter object post configuration
		apply_filters( 'wordpress_router_configured', $router );

		// Listen for when we should check whether any defined routes match
		add_action( 'wp_loaded', [ static::class, 'processRequest' ] );
	}


	/**
	 * This method generates wordpress page template on the fly.
	 *
	 * @param string $uri
	 * @param VirtualPage $virtualPage
	 * @throws TooLateToAddNewRouteException
	 */
	public static function virtualPage ( string $uri, VirtualPage $virtualPage )
	{
		$virtualPage->setUri( $uri );
		static::map( [ 'GET', 'POST' ], $uri, [ $virtualPage, 'onRoute' ] );
	}

}
