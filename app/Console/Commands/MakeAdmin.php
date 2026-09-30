<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PersonService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email}';

    protected $description = 'Grant admin access to an existing login';

    public function handle(PersonService $people): int
    {
        $email = $people->normalizeEmail($this->argument('email'));
        $user = $email === null
            ? null
            : User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null || $user->person_id === null) {
            $this->error(__('commands.make_admin.not_found', ['email' => $email ?? trim($this->argument('email'))]));

            return self::FAILURE;
        }

        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $person = $user->person()->lockForUpdate()->firstOrFail();

            if (! $user->is_admin) {
                $user->forceFill(['is_admin' => true])->save();
            }

            if (! $person->can_log_in) {
                $person->update(['can_log_in' => true]);
            }
        });

        $this->info(__('commands.make_admin.success', ['email' => $email]));

        return self::SUCCESS;
    }
}
