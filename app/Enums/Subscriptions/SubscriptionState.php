<?php

namespace App\Enums\Subscriptions;

/** The five subscription states of FR-805. Computed from dates by SubscriptionStatus, never stored as truth. */
enum SubscriptionState: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Grace = 'grace';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ExpiringSoon => 'Expiring soon',
            self::Grace => 'Grace period',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
        };
    }

    /** Badge tone understood by <x-ui.badge>. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::ExpiringSoon => 'warning',
            self::Grace => 'warning',
            self::Expired => 'danger',
            self::Suspended => 'neutral',
        };
    }

    /** Full access (reads and writes). Expiring soon and grace only show a warning. */
    public function allowsWrites(): bool
    {
        return in_array($this, [self::Active, self::ExpiringSoon, self::Grace], true);
    }

    /** Whether school users should see a warning banner. */
    public function needsAttention(): bool
    {
        return $this !== self::Active;
    }

    /** @return array<string, string> value => label, for selects */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
