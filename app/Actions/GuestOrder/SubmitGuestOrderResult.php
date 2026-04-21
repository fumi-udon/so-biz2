<?php

namespace App\Actions\GuestOrder;

final readonly class SubmitGuestOrderResult
{
    public function __construct(
        public int $posOrderId,
    ) {}
}
