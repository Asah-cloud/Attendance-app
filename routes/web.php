<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SummaryReportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

// 1. Dashboard Redirect
Route::get('/', function () {
    return redirect()->route('events.index');
})->middleware(['auth', 'verified'])->name('dashboard');

// 2. PUBLIC SCANNING ROUTES (No Login Required)
Route::get('/scan/{event}', [AttendanceController::class, 'publicScan'])->name('attendance.scan');
Route::post('/scan/{event}/check', [AttendanceController::class, 'processPublicScan'])->name('attendance.check');

// 3. AUTHENTICATED ROUTES
Route::middleware(['auth'])->group(function () {

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- SHARED ROUTES (Admins & Markers) ---
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}/attendance', [AttendanceController::class, 'show'])->name('events.attendance');
    Route::patch('/events/{event}/update-day', [EventController::class, 'updateDay'])->name('events.update-day');

    // Reporting & Exports (Updated to accept {day})
    Route::get('/reports/event/{event}/{day?}', [ReportController::class, 'show'])->name('reports.event');
    Route::get('/reports/event/{event}/excel/{day?}', [ReportController::class, 'exportExcel'])->name('reports.excel');
    Route::get('/reports/event/{event}/csv/{day?}', [ReportController::class, 'exportCsv'])->name('reports.csv');
    
    Route::get('/events/{event}/summary', [SummaryReportController::class, 'index'])->name('reports.summary');
    Route::get('/events/{event}/summary/export', [SummaryReportController::class, 'download'])->name('reports.summary.export');

    // --- ADMIN ONLY ROUTES ---
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('events', EventController::class)->except(['index', 'show']);
        Route::post('/events/{event}/import', [AttendanceController::class, 'import'])->name('users.import');
        Route::get('/admin/register-person', function () {
        return view('auth.register'); // Points to your existing register blade
    })->name('admin.register-person');
        Route::post('/admin/register-person', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
        ->name('admin.register.store');
        
        // Manual Attendance Overrides
        Route::post('events/{event}/attendance', [AttendanceController::class, 'store'])->name('events.attendance.store');
        Route::post('/attendance/mark/{user_id}', [AttendanceController::class, 'markPresent'])->name('attendance.mark');
        Route::delete('/events/{event}/attendance/{user_id}', [AttendanceController::class, 'destroy'])->name('events.attendance.destroy');
    });
});
// routes/web.php

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroy'])->name('admin.users.destroy');
});

require __DIR__.'/auth.php';