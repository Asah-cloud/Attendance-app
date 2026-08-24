<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceConfirmationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRegistrationFormController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrganizationBrandingController;
use App\Http\Controllers\ParticipantMergeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicEventRegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SummaryReportController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\SuperAdmin\CompanyHistoryController;
use Illuminate\Support\Facades\Route;

// Public marketing pages
Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/pricing', [OnboardingController::class, 'pricing'])->name('pricing');

Route::get('/events/{event}/register', [PublicEventRegistrationController::class, 'create'])->name('events.register');
Route::post('/events/{event}/register', [PublicEventRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('events.register.store');
Route::get('/registrations/{code}/confirmation', [PublicEventRegistrationController::class, 'confirmation'])->name('registrations.confirmation');
Route::post('/registrations/{code}/cancel', [PublicEventRegistrationController::class, 'cancel'])
    ->middleware('throttle:10,1')
    ->name('registrations.cancel');
Route::get('/check-in/{code}', [AttendanceController::class, 'personalCheckIn'])
    ->middleware('throttle:30,1')
    ->name('attendance.personal');
Route::get('/confirm/{code}', [PublicEventRegistrationController::class, 'showConfirm'])->name('attendance.confirm.show');
Route::post('/confirm/{code}', [PublicEventRegistrationController::class, 'storeConfirm'])
    ->middleware('throttle:10,1')
    ->name('attendance.confirm.store');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'company.active'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('role:manager')->prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('/checkout/{plan}', [BillingController::class, 'checkout'])->name('checkout');
        Route::post('/checkout/{plan}/test-payment', [BillingController::class, 'processTestPayment'])
            ->middleware('throttle:10,1')
            ->name('test-payment');
        Route::patch('/contact', [BillingController::class, 'updateContact'])->name('contact.update');
        Route::post('/cancel-renewal', [BillingController::class, 'cancelRenewal'])->name('cancel');
        Route::post('/resume-renewal', [BillingController::class, 'resumeRenewal'])->name('resume');
    });

    Route::middleware('role:manager')->prefix('organization')->name('organization.')->group(function () {
        Route::get('/branding', [OrganizationBrandingController::class, 'edit'])->name('branding.edit');
        Route::patch('/branding', [OrganizationBrandingController::class, 'update'])->name('branding.update');
        Route::patch('/messaging', [OrganizationBrandingController::class, 'updateMessaging'])->name('messaging.update');
        Route::post('/messaging/email-domain/check', [OrganizationBrandingController::class, 'checkEmailDomain'])->name('messaging.email-domain.check');
    });
});

// 3. AUTHENTICATED ROUTES
Route::middleware(['auth', 'verified', 'company.active'])->group(function () {

    // --- SHARED ROUTES (Admins, Managers & Markers) ---
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}/attendance', [AttendanceController::class, 'show'])->name('events.attendance');
    Route::get('/events/{event}/scanner', [AttendanceController::class, 'scanner'])->name('events.scanner');
    Route::post('/events/{event}/scanner/check-in', [AttendanceController::class, 'scan'])
        ->middleware('throttle:60,1')
        ->name('events.scanner.check-in');
    Route::patch('/events/{event}/update-day', [EventController::class, 'updateDay'])->name('events.update-day');
    // Reporting & Exports
    Route::get('/reports/event/{event}/{day?}', [ReportController::class, 'show'])->name('reports.event');
    Route::get('/reports/event/{event}/excel/{day?}', [ReportController::class, 'exportExcel'])->name('reports.excel');
    Route::get('/reports/event/{event}/csv/{day?}', [ReportController::class, 'exportCsv'])->name('reports.csv');
    Route::get('/reports/event/{event}/pdf/{day?}', [ReportController::class, 'exportPdf'])->name('reports.pdf');
    Route::get('/events/{event}/summary', [SummaryReportController::class, 'index'])->name('reports.summary');
    Route::get('/events/{event}/summary/export', [SummaryReportController::class, 'download'])->name('reports.summary.export');
    Route::get('/events/{event}/summary/pdf', [SummaryReportController::class, 'downloadPdf'])->name('reports.summary.pdf');

    // --- SHARED MANAGEMENT ROUTES (Admins & Managers) ---
    Route::middleware(['role:admin|manager'])->group(function () {
        Route::get('/admin/users', [AdminController::class, 'index'])->name('admin.users.index');
        Route::get('/admin/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroy'])->name('admin.users.destroy');

        // Event resource routes for Managers and Admins
        Route::patch('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
        Route::resource('events', EventController::class)->except(['index', 'show']);
        Route::get('/events/{event}/registration-form', [EventRegistrationFormController::class, 'edit'])->name('events.registration-form.edit');
        Route::get('/events/{event}/registrations', [EventRegistrationFormController::class, 'registrations'])->name('events.registrations.index');
        Route::post('/events/{event}/registrations', [EventRegistrationFormController::class, 'storeRegistration'])->name('events.registrations.store');
        Route::get('/events/{event}/registrations/export', [EventRegistrationFormController::class, 'export'])->name('events.registrations.export');
        Route::patch('/events/{event}/registrations/{registration}/approve', [EventRegistrationFormController::class, 'approve'])->name('events.registrations.approve');
        Route::patch('/events/{event}/registrations/{registration}/reject', [EventRegistrationFormController::class, 'reject'])->name('events.registrations.reject');
        Route::patch('/events/{event}/registrations/{registration}/cancel', [EventRegistrationFormController::class, 'cancelRegistration'])->name('events.registrations.cancel');
        Route::post('/events/{event}/registrations/{registration}/resend', [EventRegistrationFormController::class, 'resend'])->name('events.registrations.resend');
        Route::patch('/events/{event}/registrations/{registration}/participant', [EventRegistrationFormController::class, 'updateParticipant'])->name('events.registrations.participant.update');
        Route::get('/events/{event}/registrations/{registration}/history', [EventRegistrationFormController::class, 'participantHistory'])->name('events.registrations.participant.history');
        Route::get('/events/{event}/badges', [EventRegistrationFormController::class, 'badges'])->name('events.badges');
        Route::patch('/events/{event}/registration-form', [EventRegistrationFormController::class, 'updateSettings'])->name('events.registration-form.update');
        Route::get('/events/{event}/registration-form/print-qr', [EventRegistrationFormController::class, 'printQr'])->name('events.registration-form.print-qr');
        Route::get('/events/{event}/registration-form/download-qr', [EventRegistrationFormController::class, 'downloadQr'])->name('events.registration-form.download-qr');
        Route::post('/events/{event}/registration-fields', [EventRegistrationFormController::class, 'storeField'])->name('events.registration-fields.store');
        Route::patch('/events/{event}/registration-fields/{field}', [EventRegistrationFormController::class, 'updateSystemField'])->name('events.registration-fields.update');
        Route::delete('/events/{event}/registration-fields/{field}', [EventRegistrationFormController::class, 'destroyField'])->name('events.registration-fields.destroy');

        Route::get('/admin/register-person', [RegisteredUserController::class, 'create'])->name('admin.register-person');
        Route::post('/admin/register-person', [RegisteredUserController::class, 'store'])
            ->name('admin.register.store');

        // Participant imports are company-scoped by the event policy, so managers
        // can import into their own events while admins retain global access.
        Route::post('/events/{event}/import', [EventController::class, 'import'])->name('events.import.store');

        // Hard-copy attendees: import a contact list, customize the welcome
        // message, and bulk-send confirmation requests via email/SMS.
        Route::get('/events/{event}/confirmations', [AttendanceConfirmationController::class, 'index'])->name('events.confirmations.index');
        Route::patch('/events/{event}/confirmations/message', [AttendanceConfirmationController::class, 'updateMessage'])->name('events.confirmations.message.update');
        Route::post('/events/{event}/confirmations/import', [AttendanceConfirmationController::class, 'import'])->name('events.confirmations.import');
        Route::post('/events/{event}/confirmations/send', [AttendanceConfirmationController::class, 'send'])->name('events.confirmations.send');
        Route::delete('/events/{event}/confirmations/{registration}', [AttendanceConfirmationController::class, 'remove'])->name('events.confirmations.destroy');

        // Manual Attendance Overrides
        Route::post('events/{event}/attendance', [AttendanceController::class, 'store'])->name('events.attendance.store');
        Route::delete('/events/{event}/attendance/{participant_id}', [AttendanceController::class, 'destroy'])->name('events.attendance.destroy');
    });

    // --- MANAGER ONLY: participant data cleanup (not event-scoped) ---
    Route::middleware(['role:manager'])->prefix('participants/duplicates')->name('participants.duplicates.')->group(function () {
        Route::get('/', [ParticipantMergeController::class, 'index'])->name('index');
        Route::get('/compare', [ParticipantMergeController::class, 'compare'])->name('compare');
        Route::post('/merge', [ParticipantMergeController::class, 'merge'])->name('merge');
    });

    // --- SUPER ADMIN ONLY ROUTES ---
    Route::middleware(['role:admin'])->group(function () {
        // Full CRUD Company Tenant Management Resources
        Route::resource('companies', CompanyController::class)->except('show');

        // Archived (soft-deleted) companies: browse their events/attendees
        // and either restore them or permanently delete them for good.
        Route::get('/companies/history', [CompanyHistoryController::class, 'index'])->name('companies.history.index');
        Route::get('/companies/history/{company}', [CompanyHistoryController::class, 'show'])->name('companies.history.show')->withTrashed();
        Route::post('/companies/history/{company}/restore', [CompanyHistoryController::class, 'restore'])->name('companies.history.restore')->withTrashed();
        Route::delete('/companies/history/{company}', [CompanyHistoryController::class, 'destroy'])->name('companies.history.destroy')->withTrashed();
    });
});

require __DIR__.'/auth.php';
