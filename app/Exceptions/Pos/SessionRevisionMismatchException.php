<?php

namespace App\Exceptions\Pos;

use RuntimeException;

final class SessionRevisionMismatchException extends RuntimeException
{
    public function __construct(
        public int $sessionId,
        public int $currentRevision,
        public int $clientSentRevision,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('pos.revision_conflict'));
    }
}
