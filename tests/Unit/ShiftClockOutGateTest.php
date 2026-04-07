<?php

namespace Tests\Unit;

use App\Support\ShiftClockOutGate;
use PHPUnit\Framework\TestCase;

class ShiftClockOutGateTest extends TestCase
{
    public function test_shift_label_fr_maps_lunch_and_dinner(): void
    {
        $this->assertSame('Midi', ShiftClockOutGate::shiftLabelFr('lunch'));
        $this->assertSame('Soir', ShiftClockOutGate::shiftLabelFr('dinner'));
        $this->assertSame('other', ShiftClockOutGate::shiftLabelFr('other'));
    }

    public function test_missing_clock_out_user_message_contains_french_and_names(): void
    {
        $body = ShiftClockOutGate::missingClockOutUserMessage('lunch', ['Ali', 'Sam']);

        $this->assertStringContainsString('Les employés suivants', $body);
        $this->assertStringContainsString('service Midi', $body);
        $this->assertStringContainsString('Veuillez corriger les présences', $body);
        $this->assertStringContainsString('Ali, Sam', $body);
    }
}
