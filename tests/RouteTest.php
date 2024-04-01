<?php

namespace WPHTMX\Router\Test;

use PHPUnit\Framework\TestCase;
use WPHTMX\Router\Exceptions\RouteNameRedefinedException;
use WPHTMX\Router\Route;
use WPHTMX\Router\RouterBase;

class RouteTest extends TestCase
{
    /** @test */
    public function a_route_can_be_named()
    {
        $router = new RouterBase;

        $this->assertFalse($router->has('test'));
        $route = $router->get('test/123', function () {})->name('test');
        $this->assertTrue($router->has('test'));
    }

    /** @test */
    public function name_function_is_chainable()
    {
        $router = new RouterBase;

        $this->assertInstanceOf(Route::class, $router->get('test/123', function () {})->name('test'));
    }

    /** @test */
    public function a_route_can_not_be_renamed()
    {
        $this->expectException(RouteNameRedefinedException::class);

        $router = new RouterBase;

        $route = $router->get('test/123', function () {})->name('test1')->name('test2');
    }
}
