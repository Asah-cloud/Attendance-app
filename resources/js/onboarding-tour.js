import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

function steps() {
    return [
        {
            popover: {
                title: 'Welcome to Asah Apex Attendance',
                description: "Here's a quick, 60-second look around your new workspace. Click Next to continue, or Skip anytime.",
            },
        },
        {
            element: '[data-tour="stats"]',
            popover: {
                title: 'Your numbers at a glance',
                description: 'Events, participants, registrations and check-ins update here live.',
                side: 'bottom',
            },
        },
        {
            element: '[data-tour="create-event"]',
            popover: {
                title: 'Start with an event',
                description: 'Everything happens inside an event — registration, check-in, badges, billing. Create your first one here.',
                side: 'bottom',
            },
        },
        {
            element: '[data-tour="nav-events"]',
            popover: {
                title: 'Events',
                description: 'All your events live here. Open one to manage its registration form, attendance, badges, rooms and food.',
                side: 'right',
            },
        },
        {
            element: '[data-tour="nav-billing"]',
            popover: {
                title: 'Billing',
                description: "There's no subscription — once you finalize an event, pay its bill here. It's priced per confirmed attendee, plus any advanced features you choose for that event.",
                side: 'right',
            },
        },
        {
            element: '[data-tour="nav-organization"]',
            popover: {
                title: 'Organization',
                description: 'Add your logo and set up your email/SMS sender identity so messages to your attendees look like they come from you.',
                side: 'right',
            },
        },
        {
            popover: {
                title: "You're all set",
                description: 'Replay this tour anytime from the help button next to your profile.',
            },
        },
    ];
}

function startTour(onFinish) {
    // The sidebar nav is off-canvas below the lg breakpoint unless opened, so
    // skip steps that point at it there rather than highlighting something invisible.
    const isDesktopNav = window.innerWidth >= 1024;

    const tourSteps = steps().filter((step) => {
        if (! step.element) {
            return true;
        }
        if (step.element.startsWith('[data-tour="nav-') && ! isDesktopNav) {
            return false;
        }

        return document.querySelector(step.element);
    });

    const driverObj = driver({
        showProgress: true,
        allowClose: true,
        overlayColor: 'rgba(7, 20, 38, 0.7)',
        steps: tourSteps,
        onDestroyed: () => {
            if (onFinish) {
                onFinish();
            }
        },
    });

    driverObj.drive();
}

function markTourSeen() {
    const url = document.body.dataset.tourCompleteUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (! url || ! token) {
        return;
    }

    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
    }).catch(() => {});
}

window.startOnboardingTour = () => startTour();

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.autoStartTour === 'true') {
        startTour(markTourSeen);
    }

    document.querySelectorAll('[data-tour-restart]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            startTour();
        });
    });
});
