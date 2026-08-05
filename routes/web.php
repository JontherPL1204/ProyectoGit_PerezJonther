<?php

use App\Http\Controllers\AbsenceCalendarController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminRuleController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperSupportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\ProfileController;
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
    Route::get('/registrarse', [AuthController::class, 'create'])->name('register');
    Route::post('/registrarse', [AuthController::class, 'store'])->name('register.store');
});

Route::get('/invitaciones/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitaciones/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/perfil/zona-horaria', [ProfileController::class, 'updateTimezone'])->name('profile.timezone.update');
    Route::post('/perfil/zona-horaria/detectar', [ProfileController::class, 'detectTimezone'])->name('profile.timezone.detect');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/calendario', AbsenceCalendarController::class)->name('calendar');
    Route::get('/historial', RequestHistoryController::class)->name('history');
    Route::get('/soporte', DeveloperSupportController::class)->name('developer.support');

    Route::get('/solicitudes/nueva', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
    Route::post('/solicitudes', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::get('/solicitudes/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::post('/solicitudes/{leaveRequest}/cancelar', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
    Route::post('/solicitudes/{leaveRequest}/solicitar-cancelacion', [LeaveRequestController::class, 'requestCancellation'])
        ->name('leave-requests.request-cancellation');
    Route::get('/adjuntos/{requestAttachment}/ver', [AttachmentController::class, 'preview'])->name('attachments.preview');
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
        Route::get('/gestion', [AdminManagementController::class, 'index'])->name('management.index');
        Route::post('/gestion/invitaciones', [AdminManagementController::class, 'storeInvitation'])->name('management.invitations.store');
        Route::post('/gestion/invitaciones/{teamInvitation}/reenviar', [AdminManagementController::class, 'resendInvitation'])->name('management.invitations.resend');
        Route::post('/gestion/invitaciones/{teamInvitation}/revocar', [AdminManagementController::class, 'revokeInvitation'])->name('management.invitations.revoke');
        Route::post('/gestion/puestos', [AdminManagementController::class, 'storePosition'])->name('management.positions.store');
        Route::post('/gestion/puestos/{jobPosition}', [AdminManagementController::class, 'updatePosition'])->name('management.positions.update');
        Route::post('/gestion/puestos/{jobPosition}/eliminar', [AdminManagementController::class, 'destroyPosition'])->name('management.positions.destroy');
        Route::get('/equipo', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/equipo/promover', [AdminUserController::class, 'promoteByEmail'])->name('users.promote');
        Route::post('/equipo/{user}/permisos', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/equipo/{user}/puestos', [AdminManagementController::class, 'updateUserPositions'])->name('users.positions.update');
        Route::get('/reglas', [AdminRuleController::class, 'edit'])->name('rules.edit');
        Route::post('/reglas', [AdminRuleController::class, 'update'])->name('rules.update');
        Route::post('/reglas/tipos', [AdminRuleController::class, 'storeLeaveType'])->name('rules.leave-types.store');
        Route::post('/reglas/tipos/{leaveType}/eliminar', [AdminRuleController::class, 'destroyLeaveType'])->name('rules.leave-types.destroy');
    });
});
