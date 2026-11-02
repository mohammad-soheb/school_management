<?php

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

/** Thrown when a write would put a row into a school other than the active one, or move a row between schools. */
class CrossTenantWrite extends RuntimeException
{
}
