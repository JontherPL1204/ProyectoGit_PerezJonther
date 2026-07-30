<?php

use App\Http\Controllers\AbsenceCalendarController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminRuleController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\RequestHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/calendario', AbsenceCalendarController::class)->name('calendar');
    Route::get('/historial', RequestHistoryController::class)->name('history');

    Route::get('/solicitudes/nueva', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
    Route::post('/solicitudes', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::get('/solicitudes/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::post('/solicitudes/{leaveRequest}/cancelar', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
    Route::post('/solicitudes/{leaveRequest}/solicitar-cancelacion', [LeaveRequestController::class, 'requestCancellation'])
        ->name('leave-requests.request-cancellation');
    Route::get('/adjuntos/{requestAttachment}/descargar', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::post('/adjuntos/{requestAttachment}/revisar', [AttachmentController::class, 'markReviewed'])->name('attachments.review');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/solicitudes', [AdminController::class, 'index'])->name('dashboard');
        Route::post('/solicitudes/{leaveRequest}/aprobar', [AdminController::class, 'approve'])->name('requests.approve');
        Route::post('/solicitudes/{leaveRequest}/rechazar', [AdminController::class, 'reject'])->name('requests.reject');
        Route::post('/solicitudes/{leaveRequest}/aceptar-cancelacion', [AdminController::class, 'acceptCancellation'])
            ->name('requests.accept-cancellation');
        Route::post('/solicitudes/{leaveRequest}/rechazar-cancelacion', [AdminController::class, 'rejectCancellation'])
            ->name('requests.reject-cancellation');
        Route::get('/reportes', AdminReportController::class)->name('reports');
        Route::get('/notificaciones', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notificaciones/{notification}/reenviar', [AdminNotificationController::class, 'resend'])->name('notifications.resend');
        Route::get('/reglas', [AdminRuleController::class, 'edit'])->name('rules.edit');
        Route::post('/reglas', [AdminRuleController::class, 'update'])->name('rules.update');
    });
});
