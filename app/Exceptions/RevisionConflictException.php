<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * §5 Revision Contract — optimistic lock mismatch (HTTP 409 equivalent for Livewire).
 */
final class RevisionConflictException extends RuntimeException
{
    public function __construct(
        public string $resource,
        public int $id,
        public int $currentRevision,
        public int $clientSentRevision,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('pos.revision_conflict'));
    }
}
