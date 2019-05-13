<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 1.0.0
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Middleware;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\IdentityInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;
use Firebase\JWT\JWT;
use TestApp\Application;
use TestApp\Http\TestRequestHandler;

class AuthenticationMiddlewareTest extends TestCase
{
    /**
     * Fixtures
     */
    public $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);
        $this->application = new Application('config');
    }

    public function testApplicationAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->application);
        $expected = 'identity';
        $actual = $middleware->getConfig("identityAttribute");
        $this->assertEquals($expected, $actual);

        $response = $middleware->process($request, $handler);

        /** @var AuthenticationService $service */
        $service = $handler->request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);

        $this->assertTrue($service->identifiers()->has('Password'));
        $this->assertTrue($service->authenticators()->has('Form'));
    }

    public function testProviderAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $provider = $this->createMock(AuthenticationServiceProviderInterface::class);
        $provider
            ->method('getAuthenticationService')
            ->willReturn($this->service);

        $middleware = new AuthenticationMiddleware($provider);
        $expected = 'identity';
        $actual = $middleware->getConfig("identityAttribute");
        $this->assertEquals($expected, $actual);

        $response = $middleware->process($request, $handler);

        /** @var AuthenticationService $service */
        $service = $handler->request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertSame($this->service, $service);

        $this->assertTrue($service->identifiers()->has('Password'));
        $this->assertTrue($service->authenticators()->has('Form'));
    }

    /**
     * test middleware call with custom identity attribute
     *
     * @return void
     */
    public function testApplicationAuthenticationCustomIdentityAttribute()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->application, [
            'identityAttribute' => 'customIdentity',
        ]);

        $expected = 'customIdentity';
        $actual = $middleware->getConfig("identityAttribute");
        $this->assertEquals($expected, $actual);

        $response = $middleware->process($request, $handler);

        /** @var AuthenticationService $service */
        $service = $handler->request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);

        $this->assertTrue($service->identifiers()->has('Password'));
        $this->assertTrue($service->authenticators()->has('Form'));
    }

    public function testApplicationAuthenticationRequestResponse()
    {
        $request = ServerRequestFactory::fromGlobals();
        $handler = new TestRequestHandler();

        $service = $this->createMock(AuthenticationServiceInterface::class);

        $service->method('authenticate')
            ->willReturn($this->createMock(ResultInterface::class));

        $application = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAuthenticationService', 'middleware'])
            ->getMock();

        $application->expects($this->once())
            ->method('getAuthenticationService')
            ->with($request)
            ->willReturn($service);

        $middleware = new AuthenticationMiddleware($application);

        $response = $middleware->process($request, $handler);
    }

    public function testInvalidSubject()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Subject must be an instance of `Authentication\AuthenticationServiceInterface` or `Authentication\AuthenticationServiceProviderInterface`, `stdClass` given.');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware(new \stdClass());
        $response = $middleware->process($request, $handler);
    }

    /**
     * testSuccessfulAuthentication
     *
     * @return void
     */
    public function testSuccessfulAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('identity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
    }

    /**
     * testSuccessfulAuthentication with custom identity attribute
     *
     * @return void
     */
    public function testSuccessfulAuthenticationWithCustomIdentityAttribute()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service, [
            'identityAttribute' => 'customIdentity',
        ]);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('customIdentity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
    }

    /**
     * testSuccessfulAuthenticationApplicationHook
     *
     * @return void
     */
    public function testSuccessfulAuthenticationApplicationHook()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->application);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('identity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
    }

    /**
     * testNonSuccessfulAuthentication
     *
     * @return void
     */
    public function testNonSuccessfulAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'invalid', 'password' => 'invalid']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('identity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertNull($identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertFalse($service->getResult()->isValid());
    }

    /**
     * test non-successful auth with a challenger
     *
     * @return void
     */
    public function testNonSuccessfulAuthenticationWithChallenge()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath', 'SERVER_NAME' => 'localhost'],
            [],
            ['username' => 'invalid', 'password' => 'invalid']
        );
        $handler = new TestRequestHandler();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.HttpBasic',
            ],
        ]);

        $middleware = new AuthenticationMiddleware($service);

        $response = $middleware->process($request, $handler);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('WWW-Authenticate'));
        $this->assertSame('', $response->getBody()->getContents());
    }

    /**
     * test unauthenticated errors being bubbled up when not caught.
     *
     * @return void
     */
    public function testUnauthenticatedNoRedirect()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => false,
        ]);

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);
        $response = $middleware->process($request, $handler);
    }

    /**
     * test unauthenticated errors being converted into redirects when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirect()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login',
        ]);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * test unauthenticated errors being converted into redirects with a query param when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithQuery()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login?redirect=http%3A%2F%2Flocalhost%2Ftestpath', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * test unauthenticated errors being converted into redirects with a query param when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithExistingQuery()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login?hello=world',
            'queryParam' => 'redirect',
        ]);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login?hello=world&redirect=http%3A%2F%2Flocalhost%2Ftestpath', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * test unauthenticated errors being converted into redirects with a query param when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithFragment()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login?hello=world#frag',
            'queryParam' => 'redirect',
        ]);

        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            '/users/login?hello=world&redirect=http%3A%2F%2Flocalhost%2Ftestpath#frag',
            $response->getHeaderLine('Location')
        );
        $this->assertSame('', (string)$response->getBody());
    }

    /**
     * test unauthenticated errors being converted into redirects when configured, with a different URL base
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithBase()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $uri = $request->getUri();
        $uri->base = '/base';
        $request = $request->withUri($uri);
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login?redirect=http%3A%2F%2Flocalhost%2Fbase%2Ftestpath', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * testJwtTokenAuthorizationThroughTheMiddlewareStack
     *
     * @return void
     */
    public function testJwtTokenAuthorizationThroughTheMiddlewareStack()
    {
        $data = [
            'sub' => 3,
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry',
        ];

        $token = JWT::encode($data, 'secretKey');

        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
                'Authentication.JwtSubject',
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Jwt' => [
                    'secretKey' => 'secretKey',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $token]
        );

        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('identity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
        $this->assertEquals($data, $identity->getOriginalData()->getArrayCopy());
    }

    /**
     * testCookieAuthorizationThroughTheMiddlewareStack
     *
     * @return void
     */
    public function testCookieAuthorizationThroughTheMiddlewareStack()
    {
        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Cookie',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            [
                'username' => 'mariano',
                'password' => 'password',
                'remember_me' => true,
            ]
        );

        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);

        $this->assertStringContainsString('CookieAuth=%5B%22mariano%22', $response->getHeaderLine('Set-Cookie'));
    }
}
