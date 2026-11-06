<?php
declare(strict_types=1);
namespace Kap\Admin;

use Kap\Teacher\WebError;

/** Safe, field-specific errors displayed only after server-side validation. */
final class FieldError extends WebError
{
    public function __construct(
        public string $field,
        string $message,
        public string $hint,
        int $status = 422
    ) {
        parent::__construct($status, $message);
    }
}
