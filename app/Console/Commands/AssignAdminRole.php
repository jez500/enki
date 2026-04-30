<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:assign-admin {email? : The email address of the user}')]
#[Description('Assign the admin role to a user')]
class AssignAdminRole extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->askForEmail();

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('No user found with email: '.$email);

            return self::FAILURE;
        }

        if ($user->role === UserRole::Admin) {
            $this->info(sprintf('%s (%s) is already an admin.', $user->name, $email));

            return self::SUCCESS;
        }

        $user->update(['role' => UserRole::Admin]);

        $this->info(sprintf('✓ %s (%s) has been assigned the admin role.', $user->name, $email));

        return self::SUCCESS;
    }

    private function askForEmail(): string
    {
        $emails = User::pluck('email')->all();

        return $this->anticipate('Enter the user email', $emails);
    }
}
