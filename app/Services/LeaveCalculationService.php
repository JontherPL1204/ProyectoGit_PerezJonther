<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\WorkScheduleDay;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class LeaveCalculationService
{
    /**
     * @return array{units:int, rows:list<array<string,mixed>>}
     */
    public function calculate(
        EmployeeProfile $employee,
        LeaveType $leaveType,
        string $startDate,
        string $endDate,
        ?string $startTime = null,
        ?string $endTime = null,
    ): array {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
        }

        if ($leaveType->unit === 'MINUTES') {
            return $this->calculateMinutes($employee, $start, $startTime, $endTime);
        }

        return $this->calculateDays($employee, $start, $end);
    }

    /**
     * @return array{units:int, rows:list<array<string,mixed>>}
     */
    private function calculateDays(EmployeeProfile $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = [];
        $units = 0;
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $scheduleDay = $this->scheduleDayFor($employee, $cursor);
            $holiday = $this->holidayFor($employee, $cursor);
            $isWorkingDay = (bool) ($scheduleDay?->is_working_day);
            $isHoliday = $holiday !== null;
            $computedUnits = $isWorkingDay && ! $isHoliday ? 1 : 0;
            $units += $computedUnits;

            $rows[] = [
                'work_date' => $cursor->toDateString(),
                'is_working_day' => $isWorkingDay,
                'is_holiday' => $isHoliday,
                'computed_units' => $computedUnits,
                'note' => $isHoliday ? $holiday->name : ($isWorkingDay ? 'Dia computable' : 'Dia no laborable'),
            ];

            $cursor = $cursor->addDay();
        }

        return ['units' => $units, 'rows' => $rows];
    }

    /**
     * @return array{units:int, rows:list<array<string,mixed>>}
     */
    private function calculateMinutes(
        EmployeeProfile $employee,
        CarbonImmutable $date,
        ?string $startTime,
        ?string $endTime,
    ): array {
        if (! $startTime || ! $endTime) {
            throw new InvalidArgumentException('Los permisos por horas requieren hora de inicio y fin.');
        }

        $startsAt = CarbonImmutable::parse($date->toDateString().' '.$startTime);
        $endsAt = CarbonImmutable::parse($date->toDateString().' '.$endTime);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('La hora de fin debe ser posterior a la hora de inicio.');
        }

        $scheduleDay = $this->scheduleDayFor($employee, $date);
        $holiday = $this->holidayFor($employee, $date);

        if (! $scheduleDay?->is_working_day || $holiday) {
            throw new InvalidArgumentException('El permiso por horas debe estar dentro de un dia laborable.');
        }

        $minutes = $startsAt->diffInMinutes($endsAt);

        if ($minutes > $scheduleDay->work_minutes) {
            throw new InvalidArgumentException('Las horas solicitadas superan la jornada efectiva del dia.');
        }

        return [
            'units' => $minutes,
            'rows' => [[
                'work_date' => $date->toDateString(),
                'is_working_day' => true,
                'is_holiday' => false,
                'computed_units' => $minutes,
                'note' => 'Permiso horario',
            ]],
        ];
    }

    private function scheduleDayFor(EmployeeProfile $employee, CarbonImmutable $date): ?WorkScheduleDay
    {
        $assignment = $employee->scheduleAssignments()
            ->with('workSchedule.days')
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $date->toDateString());
            })
            ->latest('valid_from')
            ->first();

        return $assignment?->workSchedule?->days
            ->firstWhere('weekday', (int) $date->isoWeekday());
    }

    private function holidayFor(EmployeeProfile $employee, CarbonImmutable $date): ?Holiday
    {
        $assignment = $employee->calendarAssignments()
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $date->toDateString());
            })
            ->latest('valid_from')
            ->first();

        if (! $assignment) {
            return null;
        }

        return Holiday::where('holiday_calendar_id', $assignment->holiday_calendar_id)
            ->whereDate('holiday_date', $date->toDateString())
            ->first();
    }
}
