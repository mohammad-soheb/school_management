<?php

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

/** Thrown when tenant-owned data is touched with no active school. Failing closed beats returning every school's rows. */
class TenantContextMissing extends RuntimeException
{
    public function __construct(string $message = 'No active school context. Tenant data cannot be accessed.')
    {
        parent::__construct($message);
    }
}
