<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\AuthManager;
use App\Auth\Data\LoginDTO;
use App\Auth\Data\RegisterDTO;
use App\User\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthManagerTest extends TestCase
{
    private AuthManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app->make(AuthManager::class);
    }

    public function test_register(): void
    {
        Event::fake();
        $email = str_random(10).'@'.str_random(5).'.com';

        $result = $this->manager->register(new RegisterDTO($email, 'en-IN', ['agreement' => true]));

        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($result->wasRecentlyCreated);
        $this->assertSame($email, $result->email);
        $this->assertNotNull($result->username);
        $this->assertNull($result->password);
        $this->assertSame(config('localization.default_language'), $result->language);

        Event::assertDispatched(Login::class);
    }

    public function test_login_by_email(): void
    {
        $user = User::factory()->create([
            'email' => $email = str_random(10).'@'.str_random(5).'.com',
            'password' => $password = str_random(),
        ]);

        $result = $this->manager->login(new LoginDTO($email, $password));

        $this->assertTrue($user->is($result));
    }

    public function test_login_by_username(): void
    {
        $user = User::factory()->create([
            'username' => $username = str_random(),
            'password' => $password = str_random(),
        ]);

        $result = $this->manager->login(new LoginDTO($username, $password));

        $this->assertTrue($user->is($result));
    }
}
