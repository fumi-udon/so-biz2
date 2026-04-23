<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FrontendDailyClose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class FrontendDailyCloseInputStabilityTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_dinner_money_input_keeps_in_progress_decimal_text_while_typing(): void
    {
        Livewire::test(FrontendDailyClose::class)
            ->set('data.shift', 'dinner')
            ->set('data.dinner_cash', '0.')
            ->assertSet('data.dinner_cash', '0.')
            ->set('data.dinner_chips', '12,')
            ->assertSet('data.dinner_chips', '12,');
    }

    public function test_calculate_normalizes_selected_shift_money_values_before_validation(): void
    {
        Livewire::test(FrontendDailyClose::class)
            ->set('closeSessionReady', true)
            ->set('responsibleStaffId', 1)
            ->set('data.shift', 'dinner')
            ->set('data.business_date', '')
            ->set('data.dinner_recettes', '10,44')
            ->set('data.dinner_chips', '1.')
            ->set('data.dinner_cash', '2,2')
            ->set('data.dinner_cheque', '')
            ->set('data.dinner_carte', '3.14')
            ->call('calculate')
            ->assertHasErrors(['data.business_date' => ['required']])
            ->assertSet('data.dinner_recettes', '10.4')
            ->assertSet('data.dinner_chips', '1')
            ->assertSet('data.dinner_cash', '2.2')
            ->assertSet('data.dinner_cheque', '0')
            ->assertSet('data.dinner_carte', '3.1');
    }

    public function test_confirm_close_session_gate_accepts_staff_with_level_two(): void
    {
        $shop = $this->makeShop('daily-close-level2');
        $staff = $this->makeApprover($shop, level: 2, pin: '1234');

        Livewire::test(FrontendDailyClose::class)
            ->set('gateShift', 'dinner')
            ->set('gateStaffId', (int) $staff->id)
            ->set('gatePinInput', '1234')
            ->call('confirmCloseSessionGate')
            ->assertHasNoErrors()
            ->assertSet('responsibleStaffId', (int) $staff->id)
            ->assertSet('closeSessionReady', true);
    }

    public function test_confirm_close_session_gate_rejects_staff_with_level_one(): void
    {
        $shop = $this->makeShop('daily-close-level1');
        $staff = $this->makeApprover($shop, level: 1, pin: '1234');

        Livewire::test(FrontendDailyClose::class)
            ->set('gateShift', 'dinner')
            ->set('gateStaffId', (int) $staff->id)
            ->set('gatePinInput', '1234')
            ->call('confirmCloseSessionGate')
            ->assertHasErrors(['gateStaffId'])
            ->assertSeeText('niveau > 1 requis');
    }
}
