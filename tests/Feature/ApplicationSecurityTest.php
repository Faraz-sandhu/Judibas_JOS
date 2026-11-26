<?php

namespace Tests\Feature;

use App\Http\Middleware\ApplicationSecurity;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApplicationSecurityTest extends TestCase
{
    private function requestFor(string $path, string $method = 'POST', ?string $sessionId = null): Request
    {
        $request = Request::create($path, $method);
        $session = app('session')->driver();
        $session->setId($sessionId ?? bin2hex(random_bytes(20)));
        $request->setLaravelSession($session);
        return $request;
    }

    public function test_security_headers_preserve_attachment_policy(): void
    {
        $middleware = new ApplicationSecurity;
        $response = $middleware->handle($this->requestFor('/products', 'GET'), fn () => response('ok'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("base-uri 'self'", $response->headers->get('Content-Security-Policy'));
        $policy = "sandbox; default-src 'none'";
        $response = $middleware->handle($this->requestFor('/products', 'GET'), fn () => response('ok', 200, ['Content-Security-Policy' => $policy]));
        $this->assertSame($policy, $response->headers->get('Content-Security-Policy'));
    }

    public function test_repeated_product_writes_are_blocked_before_controller_runs(): void
    {
        foreach (['pms/api/tasks', 'accounting/api/journals', 'daymark/api/reports', 'focus-to-do/api/tasks', 'cv-bank/jobs', 'admin/api/users'] as $path) {
            $request = $this->requestFor('/'.$path);
            $middleware = new ApplicationSecurity;
            for ($i = 0; $i < 120; $i++) {
                $this->assertSame(200, $middleware->handle($request, fn () => response('ok'))->getStatusCode());
            }
            try {
                $middleware->handle($request, function () { $this->fail('A throttled write reached the controller.'); });
                $this->fail('Expected throttling for '.$path);
            } catch (ThrottleRequestsException $exception) {
                $this->assertSame(429, $exception->getStatusCode());
                $this->assertArrayHasKey('Retry-After', $exception->getHeaders());
            }
            app('cache')->flush();
        }
    }

    public function test_reads_and_realtime_authorization_do_not_consume_write_budget(): void
    {
        $middleware = new ApplicationSecurity;
        foreach ([['/pms/api/hierarchy', 'GET'], ['/accounting/api/notifications/realtime/auth', 'POST'], ['/communication/api/presence', 'POST']] as [$path, $method]) {
            $request = $this->requestFor($path, $method);
            for ($i = 0; $i < 125; $i++) {
                $this->assertSame(200, $middleware->handle($request, fn () => response('ok'))->getStatusCode());
            }
        }
    }

    public function test_expensive_exports_have_a_separate_budget(): void
    {
        $middleware = new ApplicationSecurity;
        $request = $this->requestFor('/accounting/api/reports?export=csv', 'GET');
        for ($i = 0; $i < 20; $i++) {
            $middleware->handle($request, fn () => response('csv'));
        }
        $this->expectException(ThrottleRequestsException::class);
        $middleware->handle($request, function () { $this->fail('A throttled export reached the report generator.'); });
    }

    public function test_one_actor_exhausting_budget_does_not_block_another_actor(): void
    {
        $middleware = new ApplicationSecurity;
        $first = $this->requestFor('/accounting/api/journals');
        for ($i = 0; $i < 120; $i++) {
            $middleware->handle($first, fn () => response('ok'));
        }
        $second = $this->requestFor('/accounting/api/journals');
        $this->assertSame(200, $middleware->handle($second, fn () => response('ok'))->getStatusCode());
    }
}
