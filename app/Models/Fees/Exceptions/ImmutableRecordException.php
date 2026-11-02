<?php

namespace App\Models\Fees\Exceptions;

use LogicException;

/** Thrown when code tries to edit or delete a posted fee record. A bug, never a user error. */
final class ImmutableRecordException extends LogicException {}
