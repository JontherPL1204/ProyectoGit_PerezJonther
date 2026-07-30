<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbsenceCalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user->employeeProfile()->firstOrFail();
        $month = $this->monthFrom($request->query('mes'));
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $gridStart = $monthStart->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonInterface::SUNDAY);

        $leaveRequests = LeaveRequest::with(['employeeProfile.user', 'leaveType'])
            ->where('organization_id', $user->organization_id)
            ->whereIn('status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_PENDING_CANCELLATION,
                LeaveRequest::STATUS_APPROVED,
            ])
            ->whereDate('start_date', '<=', $gridEnd->toDateString())
            ->whereDate('end_date', '>=', $gridStart->toDateString())
            ->when(! $user->isAdmin(), fn ($query) => $query->where('employee_profile_id', $profile->id))
            ->orderBy('start_date')
            ->get();

        $holidays = $this->holidaysFor($request, $gridStart, $gridEnd);

        $days = collect(CarbonPeriod::create($gridStart, '1 day', $gridEnd))
            ->map(function ($date) use ($holidays, $leaveRequests, $monthStart): array {
                $day = CarbonImmutable::parse($date);

                return [
                    'date' => $day,
                    'is_current_month' => $day->month === $monthStart->month,
                    'requests' => $leaveRequests
                        ->filter(fn (LeaveRequest $leaveRequest) => $day->betweenIncluded($leaveRequest->start_date, $leaveRequest->end_date))
                        ->values(),
                    'holidays' => $holidays
                        ->filter(fn (Holiday $holiday) => $holiday->holiday_date->isSameDay($day))
                        ->values(),
                ];
            });

        return view('calendar.index', [
            'days' => $days,
            'month' => $monthStart,
            'monthLabel' => $this->monthLabel($monthStart),
            'nextMonth' => $monthStart->addMonth()->format('Y-m'),
            'previousMonth' => $monthStart->subMonth()->format('Y-m'),
            'weekdayLabels' => ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'],
        ]);
    }

    private function monthFrom(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];

            if (checkdate($month, 1, $year)) {
                return CarbonImmutable::create($year, $month, 1)->startOfMonth();
            }
        }

        return CarbonImmutable::instance(now())->startOfMonth();
    }

    private function holidaysFor(Request $request, CarbonImmutable $gridStart, CarbonImmutable $gridEnd)
    {
        $user = $request->user();

        $query = Holiday::with('calendar')
            ->whereDate('holiday_date', '>=', $gridStart->toDateString())
            ->whereDate('holiday_date', '<=', $gridEnd->toDateString());

        if ($user->isAdmin()) {
            return $query
                ->whereHas('calendar', fn ($calendarQuery) => $calendarQuery
                    ->where('organization_id', $user->organization_id)
                    ->where('is_active', true))
                ->orderBy('holiday_date')
                ->get();
        }

        $profile = $user->employeeProfile()->firstOrFail();
        $calendarIds = $profile->calendarAssignments()
            ->whereDate('valid_from', '<=', $gridEnd->toDateString())
            ->where(function ($assignmentQuery) use ($gridStart): void {
                $assignmentQuery->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $gridStart->toDateString());
            })
            ->pluck('holiday_calendar_id');

        return $query
            ->whereIn('holiday_calendar_id', $calendarIds)
            ->orderBy('holiday_date')
            ->get();
    }

    private function monthLabel(CarbonImmutable $month): string
    {
        $names = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return $names[$month->month].' '.$month->year;
    }
}
