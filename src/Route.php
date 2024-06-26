<?php

namespace WPHTMX\Router;

use InvalidArgumentException;
use WPHTMX\Router\Exceptions\RouteClassStringControllerNotFoundException;
use WPHTMX\Router\Exceptions\RouteClassStringMethodNotFoundException;
use WPHTMX\Router\Exceptions\RouteClassStringParseException;
use WPHTMX\Router\Exceptions\RouteNameRedefinedException;

class Route
{
	private $uri;
	private $methods = [];
	private $action;
	private $name;
	private $paramConstraints = [];

	public function __construct ( array $methods, string $uri, $action )
	{
		$this->methods = $methods;
		$this->setUri( $uri );
		$this->setAction( $action );
	}

	private function setUri ( $uri )
	{
		$this->uri = rtrim( $uri, ' /' );
	}

	private function setAction ( $action )
	{
		// Check if this looks like it could be a class/method string
		if ( ! is_callable( $action ) && is_string( $action ) ) {
			$action = $this->convertClassStringToClosure( $action );
		}

		$this->action = $action;
	}

	private static function convertClassStringToClosure ( $string )
	{
		@list( $className, $method ) = explode( '@', $string );

		if ( ! isset( $className ) || ! isset( $method ) ) {
			throw new RouteClassStringParseException( 'Could not parse route controller from string: `' . $string . '`' );
		}

		if ( ! class_exists( $className ) ) {
			throw new RouteClassStringControllerNotFoundException( 'Could not find route controller class: `' . $className . '`' );
		}

		if ( ! method_exists( $className, $method ) ) {
			throw new RouteClassStringMethodNotFoundException( 'Route controller class: `' . $className . '` does not have a `' . $method . '` method' );
		}

		return function ($params = null, $request = null) use ($className, $method) {
			$controller = new $className;
			return $controller->$method( $params, $request );
		};
	}

	public function getUri ()
	{
		return $this->uri;
	}

	public function getMethods ()
	{
		return $this->methods;
	}

	public function getAction ()
	{
		return $this->action;
	}

	public function name ( string $name )
	{
		if ( isset( $this->name ) ) {
			throw new RouteNameRedefinedException();
		}

		$this->name = $name;

		return $this;
	}

	public function where ()
	{
		$args = func_get_args();

		if ( count( $args ) === 0 ) {
			throw new InvalidArgumentException();
		}

		if ( is_array( $args[0] ) ) {
			foreach ( $args[0] as $key => $value ) {
				$this->paramConstraints[ $key ] = $value;
			}
		}
		else {
			$this->paramConstraints[ $args[0] ] = $args[1];
		}

		return $this;
	}

	public function getParamConstraints ()
	{
		return $this->paramConstraints;
	}

	public function getName ()
	{
		return $this->name;
	}
}
