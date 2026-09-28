<?php

namespace App\Console\Commands;

use App\Repositories\AccountRepository;
use App\Services\AccountService;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    protected $signature = 'user:make-admin {email}';

    protected $description = 'Promote an existing user to the admin role';

    public function handle(AccountRepository $accountRepository, AccountService $accountService): int
    {
        $user = $accountRepository->findByEmail((string) $this->argument('email'));

        if ($user === null) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        $accountService->promoteToAdmin($user);

        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
