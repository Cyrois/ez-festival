<?php

namespace App\Services;

use App\Mail\TeamInvitationMail;
use App\Models\LoginInvitation;
use App\Models\Person;
use App\Models\User;
use App\Support\TeamPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LoginInvitationService
{
    public function send(Person $person, User $actor): void
    {
        $token = Str::random(64);

        $user = DB::transaction(function () use ($person, $token): User {
            $person = Person::query()->lockForUpdate()->findOrFail($person->id);
            abort_unless($person->can_log_in, 422);

            $user = $this->loginFor($person);
            abort_if($user->has_set_password, 422);

            $user->loginInvitation()->delete();
            $user->loginInvitation()->create([
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);

            return $user;
        });

        Mail::to($user->email)->send(new TeamInvitationMail(
            $person,
            $actor->name,
            route('team-invitations.show', $token),
        ));
    }

    public function cancel(Person $person): void
    {
        $person->user?->loginInvitation()->delete();
    }

    public function canResend(Person $person): bool
    {
        return $person->can_log_in
            && $person->user !== null
            && ! $person->user->has_set_password
            && $person->user->loginInvitation()->exists();
    }

    public function hasEverSetPassword(Person $person): bool
    {
        return (bool) $person->user?->has_set_password;
    }

    public function isValid(string $token): bool
    {
        return LoginInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->whereHas('user.person', fn ($query) => $query->where('can_log_in', true))
            ->exists();
    }

    public function setPassword(string $token, string $password): bool
    {
        return DB::transaction(function () use ($token, $password): bool {
            $invitation = LoginInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || $invitation->expires_at->isPast()) {
                return false;
            }

            $user = User::query()->lockForUpdate()->findOrFail($invitation->user_id);
            if (! $user->person?->can_log_in) {
                return false;
            }

            $user->forceFill([
                'password' => $password,
                'has_set_password' => true,
                'must_change_password' => false,
            ])->save();
            $invitation->delete();

            return true;
        });
    }

    public function generateTemporaryPassword(Person $person): string
    {
        return DB::transaction(function () use ($person): string {
            $person = Person::query()->lockForUpdate()->findOrFail($person->id);
            abort_unless($person->can_log_in, 422, __('settings.team.login.must_be_enabled'));

            $password = TeamPassword::temporary();
            $user = $this->loginFor($person);
            $user->forceFill([
                'password' => $password,
                'must_change_password' => true,
            ])->save();
            $user->loginInvitation()->delete();

            return $password;
        });
    }

    private function loginFor(Person $person): User
    {
        return $person->user()->firstOrCreate([], [
            'password' => null,
            'has_set_password' => false,
            'must_change_password' => false,
        ]);
    }
}
