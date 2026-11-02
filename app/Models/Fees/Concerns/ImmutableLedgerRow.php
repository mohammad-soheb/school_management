<?php

namespace App\Models\Fees\Concerns;

use App\Models\Fees\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Model;

/**
 * Posted financial rows are insert-only (FR-415, §17.4). Updates and deletes through Eloquent throw; corrections are
 * new rows (reversals, adjustments, opening balances). A model may allow a narrow, one-way change by overriding
 * allowsLedgerUpdate() (a payment's posted -> reversed transition, a pending adjustment's decision).
 */
trait ImmutableLedgerRow
{
    public static function bootImmutableLedgerRow(): void
    {
        static::updating(function (Model $model): void {
            /** @var Model&self $model */
            if (! $model->allowsLedgerUpdate()) {
                throw new ImmutableRecordException(sprintf('%s #%s is posted and cannot be changed. Record a correction instead.', class_basename($model), $model->getKey()));
            }
        });

        static::deleting(function (Model $model): void {
            throw new ImmutableRecordException(sprintf('%s #%s is a financial record and can never be deleted.', class_basename($model), $model->getKey()));
        });
    }

    protected function allowsLedgerUpdate(): bool
    {
        return false;
    }
}
