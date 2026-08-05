<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCalendarAssignment;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Services\OrganizationDataCache;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AbsenceCalendarController extends Controller
{
    public function __construct(private readonly OrganizationDataCache $dataCache) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profileId = $this->currentEmployeeProfileId($request);
        $requestDataVersion = $this->dataCache->requestDataVersion($user->organization_id);
        $month = $this->monthFrom($request->query('mes'));
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $gridStart = $monthStart->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonInterface::SUNDAY);

        $days = Cache::remember(
            $this->calendarCacheKey($user->organization_id, $user->id, $profileId, $user->isAdmin(), $monthStart, $requestDataVersion),
            60,
            fn (): array => $this->calendarDays(
                $request,
                $profileId,
                $monthStart,
                $gridStart,
                $gridEnd,
            ),
        );

        return view('calendar.index', [
            'days' => $this->hydrateDays($days),
            'isAdmin' => $user->isAdmin(),
            'month' => $monthStart,
            'monthLabel' => $this->monthLabel($monthStart),
            'nextMonth' => $monthStart->addMonth()->format('Y-m'),
            'previousMonth' => $monthStart->subMonth()->format('Y-m'),
            'weekdayLabels' => ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'],
        ]);
    }

    private function calendarDays(
        Request $request,
        int $profileId,
        CarbonImmutable $monthStart,
        CarbonImmutable $gridStart,
        CarbonImmutable $gridEnd,
    ): array {
        $user = $request->user();
        $leaveRequests = LeaveRequest::with(['employeeProfile.user', 'leaveType'])
            ->where('organization_id', $user->organization_id)
            ->whereIn('status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_PENDING_CANCELLATION,
                LeaveRequest::STATUS_APPROVED,
            ])
            ->whereDate('start_date', '<=', $gridEnd->toDateString())
            ->whereDate('end_date', '>=', $gridStart->toDateString())
            ->when(! $user->isAdmin(), fn ($query) => $query->where('employee_profile_id', $profileId))
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveRequest $leaveRequest): array => [
                'id' => $leaveRequest->id,
                'status' => $leaveRequest->status,
                'status_label' => $leaveRequest->statusLabel(),
                'start_date' => $leaveRequest->start_date->toDateString(),
                'end_date' => $leaveRequest->end_date->toDateString(),
                'employee_name' => $leaveRequest->employeeProfile->user->name,
                'leave_type_name' => $leaveRequest->leaveType->name,
            ]);

        $holidays = $this->holidaysFor($request, $gridStart, $gridEnd, $profileId)
            ->map(fn (Holiday $holiday): array => [
                'holiday_date' => $holiday->holiday_date->toDateString(),
                'name' => $holiday->name,
            ]);

        return collect(CarbonPeriod::create($gridStart, '1 day', $gridEnd))
            ->map(function ($date) use ($holidays, $leaveRequests, $monthStart): array {
                $day = CarbonImmutable::parse($date);

                return [
                    'date' => $day->toDateString(),
                    'is_current_month' => $day->month === $monthStart->month,
                    'requests' => $leaveRequests
                        ->filter(fn (array $leaveRequest) => $day->betweenIncluded(
                            CarbonImmutable::parse($leaveRequest['start_date']),
                            CarbonImmutable::parse($leaveRequest['end_date']),
                        ))
                        ->values()
                        ->all(),
                    'holidays' => $holidays
                        ->filter(fn (array $holiday) => $holiday['holiday_date'] === $day->toDateString())
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    private function hydrateDays(array $days)
    {
        return collect($days)->map(fn (array $day): array => [
            'date' => CarbonImmutable::parse($day['date']),
            'is_current_month' => $day['is_current_month'],
            'requests' => collect($day['requests'])->map(fn (array $row) => (object) $row),
            'holidays' => collect($day['holidays'])->map(fn (array $row) => (object) $row),
        ]);
    }

    private function calendarCacheKey(int $organizationId, int $userId, int $profileId, bool $isAdmin, CarbonImmutable $month, int $requestDataVersion): string
    {
        return 'calendar:'.$organizationId.':'.$userId.':'.$profileId.':'.($isAdmin ? 'admin' : 'user').':'.$month->format('Y-m').':v2:'.$requestDataVersion;
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

    private function holidaysFor(Request $request, CarbonImmutable $gridStart, CarbonImmutable $gridEnd, int $profileId)
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

        $calendarIds = EmployeeCalendarAssignment::where('employee_profile_id', $profileId)
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
