<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\MethodNotAllowed;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\RouteMatch;
use App\Http\Router;
use App\Http\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $routes = new RouteCollection();
        $handler = static fn(Request $request, Session $session): Response => Response::json([]);
        $routes->script('health')->get(null, $handler);
        $routes->script('admin')->post('/login', $handler)->get('/session', $handler);
        $routes->script('admin-auth')->get('/session', $handler)->post('/logout', $handler);
        $routes->script('admin/samples/analytics')->get(null, $handler);
        $routes->script('admin/samples')->get(null, $handler);
        $routes->script('facade-materials')->get(null, $handler)->put('/{id}', $handler);
        $routes->script('cart/index')->get(null, $handler)->delete(null, $handler);
        $routes->script('system/db-maintenance')->post('/create', $handler)->get('/download/{file}', $handler);
        $this->router = new Router($routes);
    }

    public static function urls(): array
    {
        return [
            ['', 'health', ''],
            ['health', 'health', ''],
            ['api/admin/session', 'admin', '/session'],
            ['api/admin/login', 'admin', '/login'],
            ['backend/api/admin-auth/session.php', 'admin-auth', '/session'],
            ['backend/api/admin-auth/logout.php', 'admin-auth', '/logout'],
            ['api/admin/samples/analytics', 'admin/samples/analytics', ''],
            ['backend/api/admin/samples/analytics.php', 'admin/samples/analytics', ''],
            ['backend/api/admin/samples.php', 'admin/samples', ''],
            ['backend/api/facade-materials.php/12', 'facade-materials', '/12'],
            ['api/facade-materials/12', 'facade-materials', '/12'],
            ['backend/api/cart/index.php', 'cart/index', ''],
            ['api/cart/index', 'cart/index', ''],
            ['backend/api/system/db-maintenance/create', 'system/db-maintenance', '/create'],
        ];
    }

    #[DataProvider('urls')]
    public function testResolvesLegacyUrlForms(string $path, string $script, string $pathInfo): void
    {
        self::assertSame([$script, $pathInfo], $this->router->resolve($path));
    }

    public function testUnknownPathsAreNotResolved(): void
    {
        self::assertNull($this->router->resolve('api/unknown'));
        self::assertNull($this->router->resolve('backend/api/cart/samples.php'));
        self::assertNull($this->router->resolve('textures/x.png'));
    }

    public function testMatchExtractsParams(): void
    {
        $match = $this->router->match($this->request('PUT', 'backend/api/facade-materials.php/7'));

        self::assertInstanceOf(RouteMatch::class, $match);
        self::assertSame(['id' => '7'], $match->params);
        self::assertSame('/7', $match->pathInfo);
    }

    public function testWrongMethodIs405(): void
    {
        self::assertInstanceOf(MethodNotAllowed::class, $this->router->match($this->request('POST', 'api/cart/index')));
        self::assertInstanceOf(MethodNotAllowed::class, $this->router->match($this->request('DELETE', 'api/admin/session')));
    }

    public function testUnknownSubPathOfKnownScriptIsNotMatched(): void
    {
        self::assertNull($this->router->match($this->request('GET', 'api/admin/whatever')));
    }

    private function request(string $method, string $path): Request
    {
        return new Request($method, $path, [], [], [], [], [], '', '127.0.0.1');
    }
}
