<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Data\LoginDTO;
use App\Auth\Data\RegisterDTO;
use App\Auth\Events\UserRegistered;
use App\Auth\Events\UserRegistering;
use App\Exceptions\LocalizedException;
use App\Localization\LocalizationManager;
use App\User\Data\CreateUserDTO;
use App\User\Models\User;
use App\User\UserManager;
use App\User\UsernameGenerator;
use Illuminate\Support\Facades\Auth;

class AuthManager
{
    public function __construct(
        private readonly LocalizationManager $localizationManager,
        private readonly UsernameGenerator $usernameGenerator,
        private readonly UserManager $userManager,
    ) {}

    public function register(RegisterDTO $data): User
    {
        return transaction(function () use ($data) {
            $emailTaken = User::lockForUpdate()->ofEmail($data->email)->exists();
            if ($emailTaken) {
                throw new LocalizedException('email_taken');
            }

            if (!($data->options['agreement'] ?? false)) {
                throw new LocalizedException('agreement_not_accepted');
            }

            $username = $this->usernameGenerator->generateUsername();
            $language = $this->localizationManager->matchLanguage($data->language);

            $user = $this->userManager->createUser(new CreateUserDTO($username, $data->email, null, $language));

            event(new UserRegistering($user));
            transaction_committed(function () use ($user): void {
                Auth::login($user);
                event(new UserRegistered($user));
            });

            return $user;
        });
    }

    public function login(LoginDTO $data): User
    {
        return transaction(function () use ($data) {
            $isLoggedIn = Auth::attempt([
                str_contains($data->identity, '@') ? 'email' : 'username' => $data->identity,
                'password' => $data->password,
            ]);

            if (!$isLoggedIn) {
                throw new LocalizedException('invalid_login_or_password');
            }

            return Auth::user();
        });
    }

    public function logout(): void
    {
        Auth::logout();
    }
}
