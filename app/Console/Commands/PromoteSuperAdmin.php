<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteSuperAdmin extends Command
{
    protected $signature = 'society:promote-super-admin {email : Email address of an existing society administrator}';

    protected $description = 'Promote an existing society administrator to super admin';

    public function handle(): int
    {
        $user = User::withoutGlobalScopes()
            ->where('email', strtolower($this->argument('email')))
            ->where('role', 'admin')
            ->where('status', 'active')
            ->whereHas('society', fn ($query) => $query->where('status', 'active'))
            ->first();

        if (! $user) {
            $this->error('No existing society administrator was found with that email address.');

            return self::FAILURE;
        }

        if (! $this->confirm("Promote {$user->full_name} ({$user->email}) to super admin?")) {
            $this->info('Promotion cancelled.');

            return self::SUCCESS;
        }

        $user->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->info("{$user->email} is now a super admin.");

        return self::SUCCESS;
    }
}
