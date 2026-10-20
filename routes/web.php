<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InactiveAccountController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\MenuManagementController;
use App\Http\Controllers\OrderManagementController;
use App\Http\Controllers\PasswordRecoveryRequestController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VoiceAnnouncementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::post('/reservasi', [StorefrontController::class, 'storeReservation'])
    ->middleware('throttle:10,1')
    ->name('reservasis.store');
Route::post('/pesanan', [StorefrontController::class, 'storeOrder'])
    ->middleware('throttle:10,1')
    ->name('pesanans.store');
Route::get('/pengumuman-suara/aktif', [VoiceAnnouncementController::class, 'publicState'])
    ->middleware('throttle:45,1')
    ->name('voice-announcements.public-state');
Route::get('/pengumuman-suara/aktif/audio', [VoiceAnnouncementController::class, 'publicAudio'])
    ->middleware('throttle:20,1')
    ->name('voice-announcements.public-audio');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [AuthController::class, 'createLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/daftar', [AuthController::class, 'createRegistration'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'createRequestForm'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordRecoveryRequestController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/lupa-kata-sandi/status', [PasswordRecoveryRequestController::class, 'status'])
        ->middleware('throttle:30,1')
        ->name('password.recovery.status');
    Route::get('/reset-kata-sandi/{token}', [PasswordResetController::class, 'createResetForm'])->name('password.reset');
    Route::post('/reset-kata-sandi', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::post('/keluar', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/akun/tidak-aktif', [InactiveAccountController::class, 'show'])
    ->middleware('auth')
    ->name('account.inactive');
Route::get('/akun/status-kepegawaian', [InactiveAccountController::class, 'employmentStatus'])
    ->middleware('auth')
    ->name('account.employment-status');

Route::middleware(['auth', 'role:customer,operator,owner,admin'])->prefix('profil')->group(function (): void {
    Route::get('/', [UserProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/', [UserProfileController::class, 'update'])->name('profile.update');
    Route::delete('/foto', [UserProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::patch('/kata-sandi', [UserProfileController::class, 'updatePassword'])->name('profile.password.update');
});

Route::middleware(['auth', 'role:owner,operator,admin'])->prefix('ruang-kerja')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('role:operator,admin')->prefix('/pengumuman-suara')->name('voice-announcements.')->group(function (): void {
        Route::get('/', [VoiceAnnouncementController::class, 'index'])->name('index');
        Route::post('/', [VoiceAnnouncementController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::post('/{voiceAnnouncement}/putar', [VoiceAnnouncementController::class, 'broadcast'])->name('broadcast');
        Route::post('/hentikan', [VoiceAnnouncementController::class, 'stop'])->name('stop');
        Route::get('/{voiceAnnouncement}/audio', [VoiceAnnouncementController::class, 'audio'])->name('audio');
        Route::delete('/{voiceAnnouncement}', [VoiceAnnouncementController::class, 'destroy'])->name('destroy');
    });
    Route::get('/absensi', [AttendanceController::class, 'index'])
        ->middleware('role:operator,admin')
        ->name('attendance.index');
    Route::get('/absensi/karyawan/{user}/rekap', [AttendanceController::class, 'employeeReport'])
        ->middleware('role:admin')
        ->name('attendance.employees.report');
    Route::get('/absensi/karyawan/{user}/rekap/unduh', [AttendanceController::class, 'exportEmployeeReport'])
        ->middleware('role:admin')
        ->name('attendance.employees.report.export');
    Route::post('/absensi', [AttendanceController::class, 'storeAttendance'])
        ->middleware('role:admin')
        ->name('attendance.store');
    Route::post('/absensi/sanksi', [AttendanceController::class, 'storeDisciplinaryAction'])
        ->middleware('role:admin')
        ->name('attendance.sanctions.store');
    Route::post('/absensi/sanksi/{disciplinaryAction}/kirim', [AttendanceController::class, 'resendWarningLetter'])
        ->middleware('role:admin')
        ->name('attendance.warnings.send');
    Route::get('/reservasi', [DashboardController::class, 'reservations'])
        ->middleware('role:operator,admin')
        ->name('reservations.index');
    Route::patch('/reservasi/{reservasi}/status', [DashboardController::class, 'updateReservationStatus'])
        ->middleware('role:operator,admin')
        ->name('reservations.status.update');
    Route::delete('/reservasi/{reservasi}', [DashboardController::class, 'destroyReservation'])
        ->middleware('role:operator,admin')
        ->name('reservations.destroy');
    Route::get('/laporan', [DashboardController::class, 'report'])
        ->middleware('role:owner,admin')
        ->name('reports.index');
    Route::get('/bahan-baku', [InventoryItemController::class, 'index'])
        ->middleware('role:owner,admin')
        ->name('inventory.index');
    Route::post('/bahan-baku', [InventoryItemController::class, 'store'])
        ->middleware('role:admin')
        ->name('inventory.store');
    Route::put('/bahan-baku/{inventoryItem}', [InventoryItemController::class, 'update'])
        ->middleware('role:admin')
        ->name('inventory.update');
    Route::post('/mutasi-stok', [StockMovementController::class, 'store'])
        ->middleware('role:admin')
        ->name('stock-movements.store');
    Route::get('/pesanan', [OrderManagementController::class, 'index'])
        ->middleware('role:operator,admin')
        ->name('orders.index');
    Route::get('/pesanan/{pesanan}/struk', [OrderManagementController::class, 'receipt'])
        ->middleware('role:operator,admin')
        ->name('orders.receipt');
    Route::patch('/pesanan/{pesanan}/status', [OrderManagementController::class, 'updateStatus'])
        ->middleware('role:operator,admin')
        ->name('orders.status.update');
    Route::get('/pesanan/arsip', [OrderManagementController::class, 'archive'])
        ->middleware('role:operator,admin')
        ->name('orders.archive');
    Route::get('/pesanan/arsip/pratinjau', [OrderManagementController::class, 'previewArchive'])
        ->middleware('role:operator,admin')
        ->name('orders.archive.preview');
    Route::get('/pesanan/arsip/unduh', [OrderManagementController::class, 'exportArchive'])
        ->middleware('role:admin')
        ->name('orders.archive.export');
    Route::get('/akun', [UserManagementController::class, 'index'])
        ->middleware('role:admin')
        ->name('users.index');
    Route::post('/akun/{user}/akses', [UserManagementController::class, 'impersonate'])
        ->middleware('role:admin')
        ->name('users.impersonate.start');
    Route::patch('/akun/{user}/peran', [UserManagementController::class, 'updateRole'])
        ->middleware('role:admin')
        ->name('users.role.update');
    Route::delete('/akun/notifikasi-reset-sandi', [PasswordRecoveryRequestController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('users.password-recovery.destroy');
    Route::delete('/akun/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('users.destroy');
    Route::get('/akun/notifikasi-reset-sandi', [PasswordRecoveryRequestController::class, 'index'])
        ->middleware('role:admin')
        ->name('users.password-recovery.index');
    Route::post('/akun/notifikasi-reset-sandi/{recoveryRequest}/setujui', [PasswordRecoveryRequestController::class, 'approve'])
        ->middleware('role:admin')
        ->name('users.password-recovery.approve');
    Route::post('/akun/notifikasi-reset-sandi/{recoveryRequest}/tolak', [PasswordRecoveryRequestController::class, 'reject'])
        ->middleware('role:admin')
        ->name('users.password-recovery.reject');
    Route::delete('/akun/notifikasi-reset-sandi/{recoveryRequest}', [PasswordRecoveryRequestController::class, 'destroySingle'])
        ->middleware('role:admin')
        ->name('users.password-recovery.destroy-item');
    Route::get('/menu', [MenuManagementController::class, 'index'])
        ->middleware('role:operator,admin')
        ->name('menus.index');
    Route::post('/menu', [MenuManagementController::class, 'store'])
        ->middleware('role:operator,admin')
        ->name('menus.store');
    Route::put('/menu/{menu}', [MenuManagementController::class, 'update'])
        ->middleware('role:operator,admin')
        ->name('menus.update');
    Route::post('/akun', [UserManagementController::class, 'store'])
        ->middleware('role:admin')
        ->name('users.store');
    Route::patch('/akun/{user}/status', [UserManagementController::class, 'toggleActive'])
        ->middleware('role:admin')
        ->name('users.status.update');
    Route::patch('/akun/{user}/kata-sandi', [UserManagementController::class, 'resetPassword'])
        ->middleware('role:admin')
        ->name('users.password.update');
    Route::patch('/akun/{user}/berhentikan', [UserManagementController::class, 'dismiss'])
        ->middleware('role:admin')
        ->name('users.dismiss');
    Route::patch('/akun/{user}/aktifkan-kembali', [UserManagementController::class, 'rehire'])
        ->middleware('role:admin')
        ->name('users.rehire');
});

Route::post('/kembali-ke-administrator', [UserManagementController::class, 'stopImpersonating'])
    ->middleware('auth')
    ->name('users.impersonate.stop');

Route::get('/akun/pesanan', [CustomerOrderController::class, 'index'])
    ->middleware(['auth', 'role:customer'])
    ->name('account.orders.index');
