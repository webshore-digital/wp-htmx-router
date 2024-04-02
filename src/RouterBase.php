<?php

namespace WPHTMX\Router;

use AltoRouter;
use Exception;
use WPHTMX\Router\Exceptions\NamedRouteNotFoundException;
use WPHTMX\Router\Exceptions\RouteParamFailedConstraintException;
use WPHTMX\Router\Exceptions\TooLateToAddNewRouteException;
use WPHTMX\Router\Helpers\Formatting;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RouterBase implements Routable
{
	// common routable jobs
	use VerbShortcutsTrait;

	private $routes = [];
	private $altoRouter;
	private $altoRoutesCreated = false;
	private $altoRouterMatchTypeId = 1;
	private $basePath = '/';
	private $currentRoute;

	public function setBasePath ( $basePath )
	{
		$this->basePath = Formatting::addLeadingSlash( Formatting::addTrailingSlash( $basePath ) );

		// Force the router to rebuild next time we need it
		$this->altoRoutesCreated = false;
	}

	private function addRoute ( Route $route )
	{
		if ( $this->altoRoutesCreated ) {
			throw new TooLateToAddNewRouteException();
		}

		$this->routes[] = $route;
	}

	private function convertRouteToAltoRouterUri ( Route $route, AltoRouter $altoRouter ) : string
	{
		$output = $route->getUri();

		preg_match_all( '/{\s*([a-zA-Z0-9]+\??)\s*}/s', $route->getUri(), $matches );

		$paramConstraints = $route->getParamConstraints();

		for ( $i = 0; $i < count( $matches[0] ); $i++ ) {
			$match    = $matches[0][ $i ];
			$paramKey = $matches[1][ $i ];

			$optional = substr( $paramKey, -1 ) === '?';
			$paramKey = trim( $paramKey, '?' );

			$regex       = $paramConstraints[ $paramKey ] ?? null;
			$matchTypeId = '';

			if ( ! empty( $regex ) ) {
				$matchTypeId = 'rare' . $this->altoRouterMatchTypeId++;
				$altoRouter->addMatchTypes( [ 
					$matchTypeId => $regex,
				] );
			}

			$replacement = '[' . $matchTypeId . ':' . $paramKey . ']';

			if ( $optional ) {
				$replacement .= '?';
			}

			$output = str_replace( $match, $replacement, $output );
		}

		return ltrim( $output, ' /' );
	}

	/**
	 * Map a route
	 *
	 * @param array $verbs
	 * @param string $uri
	 * @param callable|string $callback
	 * @return Route
	 * @throws TooLateToAddNewRouteException
	 */
	public function map ( array $verbs, string $uri, $callback ) : Route
	{
		// Force all verbs to be uppercase
		$verbs = array_map( 'strtoupper', $verbs );

		$route = new Route( $verbs, $uri, $callback );

		$this->addRoute( $route );

		return $route;
	}

	private function createAltoRoutes ()
	{
		if ( $this->altoRoutesCreated ) {
			return;
		}

		$this->altoRouter = new AltoRouter();
		if ( ! empty( $this->basePath ) ) {
			$this->altoRouter->setBasePath( $this->basePath );
		}
		$this->altoRoutesCreated = true;

		foreach ( $this->routes as $route ) {
			$uri = $this->convertRouteToAltoRouterUri( $route, $this->altoRouter );

			// Canonical URI with trailing slash - becomes named route if name is provided
			$this->altoRouter->map(
				implode( '|', $route->getMethods() ),
				Formatting::addTrailingSlash( $uri ),
				$route->getAction(),
				$route->getName() ?? null
			);

			// Also register URI without trailing slash
			$this->altoRouter->map(
				implode( '|', $route->getMethods() ),
				Formatting::removeTrailingSlash( $uri ),
				$route->getAction(),
			);
		}
	}

	/**
	 * Match the provided Request against the defined routes and return a Response
	 *
	 * @param Request $request
	 * @return Response|bool returns false only if we don't matched anything
	 */
	public function match( Request $request )
	{
		if ( ! isset( $request ) ) {
			$request = Request::createFromGlobals();
		}

		$this->createAltoRoutes();

		$altoRoute = $this->altoRouter->match( $request->getRequestUri(), $request->getMethod() );
		//$altoRoute = $this->altoRouter->match($request->getRequestUri()->getPath(), $request->getMethod());
		$route  = $altoRoute['target'] ?? null;
		$params = new RouteParams( $altoRoute['params'] ?? [] );

		if ( ! $route ) {
			return new Response( 'Resource not found', 404 );
		}

		$this->currentRoute = $route;

		return $this->handle( $route, $params, $request );
	}

	protected function handle ( $route, $params, $request )
	{
		if ( is_callable( $route ) ) {
			$response = call_user_func( $route, $params, $request );
		}
		else {
			throw new Exception( 'Route target is not callable' );
		}

		// Ensure that we return an instance of a Response object
		if ( ! ( $response instanceof Response ) ) {
			$response = new Response(
				$response,
				Response::HTTP_OK,
				[ 'content-type' => 'text/html' ],
			);
		}

		return $response;
	}

	public function has ( string $name )
	{
		$routes = array_filter( $this->routes, function ($route) use ($name) {
			return $route->getName() === $name;
		} );

		return count( $routes ) > 0;
	}

	public function url ( string $name, $params = [] )
	{
		$this->createAltoRoutes();

		$matchedRoute = null;

		foreach ( $this->routes as $route ) {
			if ( $route->getName() === $name ) {
				$matchedRoute = $route;
			}
		}

		if ( $matchedRoute ) {
			$paramConstraints = $matchedRoute->getParamConstraints();

			foreach ( $params as $key => $value ) {
				$regex = $paramConstraints[ $key ] ?? false;

				if ( $regex ) {
					if ( ! preg_match( '/' . $regex . '/', $value ) ) {
						throw new RouteParamFailedConstraintException(
							'Value `' . $value . '` for param `' . $key . '` fails constraint `' . $regex . '`'
						);
					}
				}
			}
		}

		try {
			return $this->altoRouter->generate( $name, $params );
		}
		catch ( Exception $e ) {
			throw new NamedRouteNotFoundException( $name, 0 );
		}
	}

	public function group ( $prefix, $callback ) : RouterBase
	{
		$group = new RouteGroup( $prefix, $this );

		call_user_func( $callback, $group );

		return $this;
	}

}
