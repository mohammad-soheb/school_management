<?php

namespace App\Services\Identity;

use App\Enums\Identity\MembershipStatus;
use App\Mail\InvitationMail;
use App\Models\Identity\Invitation;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Models\Students\Guardian;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public const VALID_DAYS = 7;

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Invite someone to the ACTIVE school with the given roles. Returns the invitation and the one-time link
     * (also emailed; shown to the inviter too because mail may not be configured yet). Only the token's SHA-256
     * is stored. A newer invitation to the same address replaces any pending one.
     *
     * @param  list<int>  $roleIds
     * @return array{invitation: Invitation, url: string}
     */
    public function invite(string $email, array $roleIds, User $inviter, ?Guardian $guardian = null): array
    {
        $email = Str::lower(trim($email));
        $this->context->requireId();

        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === [] || Role::query()->whereIn('id', $roleIds)->count() !== count($roleIds)) {
            throw ValidationException::withMessages(['roles' => 'Choose at least one valid role.']);
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null && SchoolMembership::query()->where('user_id', $existing->getKey())->exists()) {
            throw ValidationException::withMessages(['email' => 'This person already has access to the school.']);
        }

        $result = DB::transaction(function () use ($email, $roleIds, $inviter, $guardian): array {
            Invitation::query()->pending()->where('email', $email)->update(['revoked_at' => now()]);

            $token = Str::random(48);

            $invitation = Invitation::create([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'role_ids' => $roleIds,
                'guardian_id' => $guardian?->getKey(),
                'invited_by' => $inviter->getKey(),
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]);

            $this->audit->record('invitation.sent', $invitation, new: ['email' => $email, 'role_ids' => $roleIds], actor: $inviter);

            return ['invitation' => $invitation, 'url' => route('invitations.show', ['token' => $token])];
        });

        // Delivery problems must not undo the invitation: the inviter can still copy the link from the screen.
        try {
            Mail::to($email)->send(new InvitationMail($result['invitation'], $result['url'], $this->context->requireSchool()->name));
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    /** @return \Illuminate\Support\Collection<int, Invitation> the school's most recent invitations, newest first */
    public function recent(int $limit = 30): \Illuminate\Support\Collection
    {
        return Invitation::query()->with('inviter')->latest('id')->limit($limit)->get();
    }

    /** Look an invitation up by its plain token, from any school (the invitee has no school context yet). */
    public function findPending(string $token): ?Invitation
    {
        $invitation = $this->context->withoutTenant(
            fn () => Invitation::query()->with('school')->where('token_hash', hash('sha256', $token))->first(),
        );

        return $invitation?->isPending() ? $invitation : null;
    }

    /**
     * Accept an invitation: creates the login (or reuses $existing, whose email must match) and the school membership
     * with the invited roles, and links a guardian profile when the invitation was for a parent.
     *
     * @param  array{name?: string, password?: string}  $data
     */
    public function accept(Invitation $invitation, array $data, ?User $existing = null): User
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['token' => 'This invitation is no longer valid.']);
        }

        if ($existing !== null && Str::lower($existing->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages(['email' => 'Sign in with the email address the invitation was sent to.']);
        }

        return $this->context->run($invitation->school, function () use ($invitation, $data, $existing): User {
            return DB::transaction(function () use ($invitation, $data, $existing): User {
                $user = $existing ?? User::create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => $data['password'],
                ]);

                if ($existing === null) {
                    // Following the emailed link proves the address belongs to the invitee.
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $membership = SchoolMembership::create([
                    'user_id' => $user->getKey(),
                    'status' => MembershipStatus::Active,
                    'invited_by' => $invitation->invited_by,
                    'joined_at' => now(),
                ]);
                $membership->syncRoles($invitation->role_ids);

                if ($invitation->guardian_id !== null) {
                    Guardian::query()->whereKey($invitation->guardian_id)->update(['user_id' => $user->getKey()]);
                }

                $invitation->forceFill(['accepted_at' => now(), 'accepted_user_id' => $user->getKey()])->save();

                $this->audit->record('invitation.accepted', $invitation, new: ['user_id' => $user->getKey()], actor: $user);

                return $user;
            });
        });
    }

    public function revoke(Invitation $invitation, User $actor): void
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be revoked.']);
        }

        $invitation->forceFill(['revoked_at' => now()])->save();
        $this->audit->record('invitation.revoked', $invitation, actor: $actor);
    }
}
