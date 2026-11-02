@php
    $canInvite = auth()->user()->can('access.members.invite');
    $canUpdate = auth()->user()->can('access.members.update');
    $validDays = \App\Services\Identity\InvitationService::VALID_DAYS;
@endphp
<x-layouts.app title="Users">
    <x-ui.page-header title="Users" description="Everyone who can sign in to this school, and what they can do.">
        <x-slot:actions>
            @can('access.roles.manage')
                <x-ui.button :href="route('school.access.roles.index')" variant="secondary" icon="shield-check">Roles</x-ui.button>
            @endcan
            @if ($canInvite)
                <x-ui.button icon="user-plus" @click="$dispatch('open-modal', 'invite-user')">Invite user</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('invitation_url'))
        <x-ui.alert tone="success" title="Invitation link" class="mb-6" dismissible>
            <p class="mb-3">An email was sent. If it does not arrive, share this one-time link yourself (valid for {{ $validDays }} days):</p>
            <div class="flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 break-all rounded-lg bg-surface-muted px-3 py-2 font-mono text-[13px]">{{ session('invitation_url') }}</code>
                <x-ui.copy :text="session('invitation_url')" label="Copy link" />
            </div>
        </x-ui.alert>
    @endif

    <x-ui.filters :reset="route('school.access.members.index')" placeholder="Search by name or email" />

    <x-ui.card :padding="false" class="mt-4">
        @if ($members->isEmpty())
            <x-ui.empty icon="users" title="No users found" description="Try a different search, or invite someone." />
        @else
            <x-ui.table caption="School users">
                <x-slot:head>
                    <x-ui.th>User</x-ui.th>
                    <x-ui.th>Roles</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th>Last active</x-ui.th>
                    @if ($canUpdate)<th class="col-actions"><span class="sr-only">Actions</span></th>@endif
                </x-slot:head>

                @foreach ($members as $member)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$member->user->name" size="sm" />
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-ink">{{ $member->user->name }}</p>
                                    <p class="truncate text-sm text-ink-faint">{{ $member->user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($member->roles as $role)
                                    <x-ui.badge :tone="$role->slug === 'school_admin' ? 'brand' : 'neutral'">{{ $role->name }}</x-ui.badge>
                                @endforeach
                            </div>
                        </td>
                        <td><x-ui.badge :tone="$member->status->tone()" dot>{{ $member->status->label() }}</x-ui.badge></td>
                        <td class="text-sm text-ink-soft">{{ $member->last_active_at?->diffForHumans() ?? 'Never' }}</td>
                        @if ($canUpdate)
                            <td class="col-actions">
                                <div class="flex justify-end gap-1">
                                    <x-ui.button variant="ghost" size="sm" icon="pencil-square" @click="$dispatch('open-modal', 'roles-{{ $member->id }}')">Roles</x-ui.button>
                                    @if ($member->user_id !== auth()->id())
                                        @if ($member->status->value === 'active')
                                            <x-ui.confirm :action="route('school.access.members.suspend', $member)" method="POST"
                                                          :title="'Suspend '.$member->user->name.'?'"
                                                          message="They will be unable to use this school until you restore access."
                                                          confirm="Suspend" tone="warning">
                                                <x-ui.button variant="ghost" size="sm" icon="no-symbol">Suspend</x-ui.button>
                                            </x-ui.confirm>
                                        @else
                                            <x-form.form :action="route('school.access.members.activate', $member)" class="inline">
                                                <x-ui.button type="submit" variant="ghost" size="sm" icon="check-circle">Restore</x-ui.button>
                                            </x-form.form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($members->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $members->links() }}</div>
        @endif
    </x-ui.card>

    @if ($invitations->isNotEmpty())
        <x-ui.card title="Invitations" :description="'Links are single-use and expire after '.$validDays.' days.'" :padding="false" class="mt-6">
            <x-ui.table caption="Invitations" dense>
                <x-slot:head>
                    <x-ui.th>Email</x-ui.th>
                    <x-ui.th>Invited by</x-ui.th>
                    <x-ui.th>Sent</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    @if ($canInvite)<th class="col-actions"><span class="sr-only">Actions</span></th>@endif
                </x-slot:head>
                @foreach ($invitations as $invitation)
                    @php $label = $invitation->statusLabel(); @endphp
                    <tr>
                        <td class="font-medium text-ink">{{ $invitation->email }}</td>
                        <td class="text-ink-soft">{{ $invitation->inviter?->name ?? '—' }}</td>
                        <td class="text-ink-soft">{{ $invitation->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td>
                        <td><x-ui.badge :tone="$label === 'Pending' ? 'info' : ($label === 'Accepted' ? 'success' : 'neutral')" dot>{{ $label }}</x-ui.badge></td>
                        @if ($canInvite)
                            <td class="col-actions">
                                @if ($invitation->isPending())
                                    <x-ui.confirm :action="route('school.access.invitations.revoke', $invitation)" method="DELETE"
                                                  title="Revoke this invitation?" message="The link will stop working immediately." confirm="Revoke">
                                        <x-ui.button variant="ghost" size="sm" icon="x-mark">Revoke</x-ui.button>
                                    </x-ui.confirm>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($canInvite)
        <x-ui.modal name="invite-user" title="Invite a user" description="They get an email with a link to create their login." max-width="lg" :show="$errors->has('email') || $errors->has('roles')">
            <x-form.form :action="route('school.access.members.invite')" id="invite-form" class="space-y-5">
                <x-form.input name="email" label="Email address" type="email" required autocomplete="off" />
                <x-form.field label="Roles" name="roles" required hint="Choose at least one. A person can hold several roles.">
                    <div class="grid gap-1 sm:grid-cols-2">
                        @foreach ($roles as $role)
                            <x-form.checkbox name="roles[]" :value="$role->id" :label="$role->name" />
                        @endforeach
                    </div>
                </x-form.field>
            </x-form.form>
            <x-slot:footer>
                <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'invite-user')">Cancel</x-ui.button>
                <x-ui.button type="submit" form="invite-form" icon="paper-airplane">Send invitation</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($canUpdate)
        @foreach ($members as $member)
            <x-ui.modal :name="'roles-'.$member->id" :title="'Roles for '.$member->user->name" max-width="md">
                <x-form.form :action="route('school.access.members.roles', $member)" method="PUT" :id="'roles-form-'.$member->id" class="space-y-1">
                    @foreach ($roles as $role)
                        <x-form.checkbox name="roles[]" :value="$role->id" :label="$role->name" :checked="$member->roles->contains('id', $role->id)" />
                    @endforeach
                </x-form.form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'roles-{{ $member->id }}')">Cancel</x-ui.button>
                    <x-ui.button type="submit" :form="'roles-form-'.$member->id">Save roles</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endforeach
    @endif
</x-layouts.app>
