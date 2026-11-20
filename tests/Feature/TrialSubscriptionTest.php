<?php

namespace Tests\Feature;

use App\Business;
use App\Http\Middleware\EnforceActiveSubscription;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\TrialSubscriptionUtil;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Superadmin\Entities\Package;
use Tests\TestCase;

class TrialSubscriptionTest extends TestCase
{
    protected $originalDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.default');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('package_id');
            $table->date('start_date')->nullable();
            $table->date('trial_end_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('package_price', 22, 4);
            $table->longText('package_details');
            $table->unsignedInteger('created_id');
            $table->string('paid_via')->nullable();
            $table->string('payment_transaction_id')->nullable();
            $table->string('status')->default('waiting');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('system', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->text('value')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('sqlite');
        config(['database.default' => $this->originalDatabase]);
        DB::setDefaultConnection($this->originalDatabase);

        parent::tearDown();
    }

    public function test_trial_subscription_uses_configured_package_entitlements_and_trial_days()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00'));

        $business = new Business();
        $business->id = 41;
        $user = new User();
        $user->id = 72;
        $package = new Package([
            'name' => 'Full Access Trial',
            'location_count' => 0,
            'user_count' => 0,
            'product_count' => 0,
            'invoice_count' => 0,
            'trial_days' => 14,
            'price' => 99.99,
            'custom_permissions' => ['manufacturing_module' => 1, 'woocommerce_module' => 1],
        ]);
        $package->id = 5;

        $subscription = app(TrialSubscriptionUtil::class)->createForBusiness($business, $user, $package);

        $this->assertSame('2026-11-19', $subscription->start_date->toDateString());
        $this->assertSame('2026-12-02', $subscription->end_date->toDateString());
        $this->assertSame('2026-12-02', $subscription->trial_end_date);
        $this->assertSame('approved', $subscription->status);
        $this->assertSame('trial', $subscription->paid_via);
        $this->assertSame(0.0, (float) $subscription->package_price);
        $this->assertSame(1, $subscription->package_details['manufacturing_module']);
        $this->assertSame(1, $subscription->package_details['woocommerce_module']);
    }

    public function test_expired_subscription_blocks_api_writes_but_allows_reads_and_renewal()
    {
        $moduleUtil = Mockery::mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('isSubscribed')->with(41)->andReturn(false);
        $this->app->instance(ModuleUtil::class, $moduleUtil);

        $user = $this->makeBusinessUser();

        $writeResponse = $this->runMiddleware($user, 'POST', '/connector/api/purchase', 'store', true);
        $this->assertSame(402, $writeResponse->getStatusCode());
        $this->assertTrue($writeResponse->getData(true)['subscription_expired']);

        $readResponse = $this->runMiddleware($user, 'GET', '/connector/api/purchase', 'index', true);
        $this->assertSame(200, $readResponse->getStatusCode());

        $renewalResponse = $this->runMiddleware($user, 'POST', '/subscription/5/confirm', 'confirm', true);
        $this->assertSame(200, $renewalResponse->getStatusCode());
    }

    public function test_expired_subscription_blocks_legacy_get_mutation_routes()
    {
        $moduleUtil = Mockery::mock(ModuleUtil::class);
        $moduleUtil->shouldReceive('isSubscribed')->with(41)->andReturn(false);
        $this->app->instance(ModuleUtil::class, $moduleUtil);

        $user = $this->makeBusinessUser();

        $response = $this->runMiddleware($user, 'GET', '/products/activate/1', 'activate', true);

        $this->assertSame(402, $response->getStatusCode());
    }

    private function runMiddleware(User $user, string $method, string $path, string $action, bool $expectsJson)
    {
        $server = $expectsJson ? ['HTTP_ACCEPT' => 'application/json'] : [];
        $request = Request::create($path, $method, [], [], [], $server);
        $controller = self::class.'@'.$action;
        $route = new Route([$method], ltrim($path, '/'), [
            'uses' => $controller,
            'controller' => $controller,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        return app(EnforceActiveSubscription::class)->handle($request, function () {
            return response('allowed', 200);
        });
    }

    private function makeBusinessUser(): User
    {
        $user = new class extends User {
            public function can($abilities, $arguments = [])
            {
                return false;
            }
        };
        $user->business_id = 41;

        return $user;
    }
}
