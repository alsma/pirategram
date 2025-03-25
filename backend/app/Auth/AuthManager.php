<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Events\UserRegistered;
use App\Auth\Events\UserRegistering;
use App\Exceptions\LocalizedException;
use App\Localization\LocalizationManager;
use App\User\Data\CreateUserDTO;
use App\User\Models\User;
use App\User\UserManager;
use App\User\UsernameGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthManager
{
    public function __construct(
        private readonly LocalizationManager $localizationManager,
        private readonly UsernameGenerator $usernameGenerator,
        private readonly UserManager $userManager,
    ) {}

    public function register(string $email, string $language, array $options): User
    {
        return transaction(function () use ($email, $language, $options) {
            $emailTaken = User::lockForUpdate()->ofEmail($email)->exists();
            if ($emailTaken) {
                throw new LocalizedException('email_taken');
            }

            if (!($options['agreement'] ?? false)) {
                throw new LocalizedException('agreement_not_accepted');
            }

            $username = $this->usernameGenerator->generateUsername();
            $language = $this->localizationManager->matchLanguage($language);

            $user = $this->userManager->createUser(new CreateUserDTO($username, $email, null, $language));

            event(new UserRegistering($user));
            transaction_committed(function () use ($user): void {
                Auth::setUser($user);
                event(new UserRegistered($user));
                $user->refresh();
            });

            return $user;
        });
    }

    public function login(string $identity, string $password): User
    {
        $user = User::lockForUpdate()->ofEmail($identity)->first();
        if (!$user) {
            $user = User::lockForUpdate()->ofUsername($identity)->first();
        }

        if (!($user instanceof User && Hash::check($password, $user->password))) {
            throw new LocalizedException('invalid_login_or_password');
        }

        return $user;
    }
}
