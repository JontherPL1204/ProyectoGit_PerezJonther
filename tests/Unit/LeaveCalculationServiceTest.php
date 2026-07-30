<?php

namespace Tests\Unit;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_does_not_consume_vacation_day(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = User::where('email', 'empleado@n-woffu-prime.local')->firstOrFail()->employeeProfile;
        $vacations = LeaveType::where('code', 'VACATIONS')->firstOrFail();
        $calendar = HolidayCalendar::firstOrFail();

        Holiday::create([
            'holiday_calendar_id' => $calendar->id,
            'holiday_date' => '2026-09-09',
            'name' => 'Festivo de prueba',
            'scope' => 'company',
        ]);

        $result = app(LeaveCalculationService::class)->calculate(
            $employee,
            $vacations,
            '2026-09-07',
            '2026-09-11',
        );

        $this->assertSame(4, $result['units']);
    }
}
