import { driver } from 'driver.js';

// Every event page shares this one tab bar; context-navigation.blade.php marks
// the current tab with aria-current="page", so this single step works everywhere
// without needing a unique element per page.
const EVENT_TABS_STEP = {
    element: '[data-tour="event-tabs"] [aria-current="page"]',
    popover: {
        title: "You're here",
        description: 'This bar always shows where you are inside the event, and lets you jump straight to any other section — Attendance, Attendees, Staff, Forms, Food, Rooms, Reports, Messages, Billing, Settings.',
        side: 'bottom',
    },
};

const TOURS = {
    dashboard: [
        { popover: { title: 'Welcome to Asah Apex Attendance', description: "Here's a quick, 60-second look around your new workspace. Click Next to continue, or Skip anytime." } },
        { element: '[data-tour="stats"]', popover: { title: 'Your numbers at a glance', description: 'Events, participants, registrations and check-ins update here live.', side: 'bottom' } },
        { element: '[data-tour="create-event"]', popover: { title: 'Start with an event', description: 'Everything happens inside an event — registration, check-in, badges, billing. Create your first one here.', side: 'bottom' } },
        { element: '[data-tour="nav-events"]', popover: { title: 'Events', description: 'All your events live here. Open one to manage its registration form, attendance, badges, rooms and food.', side: 'right' } },
        { element: '[data-tour="nav-billing"]', popover: { title: 'Billing', description: "There's no subscription — once you finalize an event, pay its bill here. It's priced per confirmed attendee, plus any advanced features you choose for that event.", side: 'right' } },
        { element: '[data-tour="nav-organization"]', popover: { title: 'Organization', description: 'Add your logo and set up your email/SMS sender identity so messages to your attendees look like they come from you.', side: 'right' } },
        { popover: { title: "You're all set", description: 'Every page has its own short tour like this one — replay it anytime from the help button next to your profile.' } },
    ],

    'events-index': [
        { popover: { title: 'Your events', description: 'Every event you run lives here, with its own registration, attendance, billing and more.' } },
        { element: '[data-tour="events-index-create"]', popover: { title: 'Create an event', description: 'Start here whenever you need a new event workspace.', side: 'bottom' } },
        { element: '[data-tour="events-index-list"]', popover: { title: 'Your event schedule', description: 'Open any event to take attendance, or edit its details.', side: 'top' } },
    ],

    'event-attendance': [
        { popover: { title: 'Event home base', description: "This is where check-in happens. It's the page you'll use most while the event is running." } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-attendance-scanner"]', popover: { title: 'QR scanner', description: 'Open this on the device at your door to scan attendee QR codes and mark them present.', side: 'bottom' } },
        { element: '[data-tour="event-attendance-stats"]', popover: { title: 'Daily attendance', description: 'Live counts for whichever day is currently selected.', side: 'right' } },
        { element: '[data-tour="event-attendance-manual"]', popover: { title: 'Manual registry', description: "Search for someone and mark them present by hand — useful if a QR code won't scan.", side: 'left' } },
    ],

    'event-attendees': [
        { popover: { title: 'Attendees', description: 'Everyone registered for this event, whether they signed up themselves or you added them.' } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-attendees-actions"]', popover: { title: 'Export and badges', description: 'Download the attendee list, or jump into the badge studio to design and print badges.', side: 'bottom' } },
        { element: '[data-tour="event-attendees-manual"]', popover: { title: 'Add someone by hand', description: "Register an attendee directly, without them using the public form.", side: 'bottom' } },
        { element: '[data-tour="event-attendees-filter"]', popover: { title: 'Search and filter', description: 'Find anyone by name, phone, email or status.', side: 'bottom' } },
    ],

    'event-staff-checkin': [
        { popover: { title: 'Event staff check-in', description: 'Separate from attendee attendance — staff are checked in once, when they collect their badge.' } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-staff-checkin-scanner"]', popover: { title: 'Staff scanner', description: 'Scan a staff badge QR code to check that person in.', side: 'bottom' } },
        { element: '[data-tour="event-staff-checkin-list"]', popover: { title: "Who's assigned here", description: "Staff need to be imported and assigned to this event first, from the Event Staff page.", side: 'top' } },
    ],

    'staff-roster': [
        { popover: { title: 'Event staff roster', description: 'Manage the people who support your events without giving them a login — ushers, security, catering, and so on.' } },
        { element: '[data-tour="staff-roster-import"]', popover: { title: 'Import a roster', description: 'Upload a spreadsheet and assign everyone in it to one or more events at once.', side: 'bottom' } },
        { element: '[data-tour="staff-roster-manage"]', popover: { title: 'Manage by event', description: 'Check staff in, see who has arrived, and print their badges — per event.', side: 'top' } },
    ],

    'event-forms': [
        { popover: { title: 'Forms', description: 'Everything about how people register, confirm, and give feedback for this event.' } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-forms-links"]', popover: { title: 'Registration and confirmations', description: 'Configure the public registration page, or import contacts and send confirmations for people you added by hand.', side: 'bottom' } },
        { element: '[data-tour="event-forms-create"]', popover: { title: 'Custom forms', description: 'Build a survey or feedback form and share it with attendees.', side: 'bottom' } },
        { element: '[data-tour="event-forms-share"]', popover: { title: 'Share your registration link', description: 'Copy this link or its QR code and share it however you reach your attendees.', side: 'bottom' } },
        { element: '[data-tour="event-forms-settings"]', popover: { title: 'Registration settings', description: 'Registration stays off until you enable it here — you can also require manager approval for each signup.', side: 'top' } },
    ],

    'event-food': [
        { popover: { title: 'Food distribution', description: 'Track meals and refreshments so each attendee collects their portion once.' } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-food-create"]', popover: { title: 'Create a distribution', description: 'Set up a serving session — a lunch or a snack pack — with how many portions are available.', side: 'bottom' } },
        { element: '[data-tour="event-food-actions"]', popover: { title: 'Vouchers and reports', description: 'Print vouchers ahead of time, or check the food report to see what has been served.', side: 'bottom' } },
    ],

    'event-rooms': [
        { popover: { title: 'Rooms', description: 'Give attendees a place to stay — build your inventory, then assign rooms automatically or by hand.' } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-rooms-settings"]', popover: { title: 'Turn on accommodation', description: 'Enable it here, and optionally let attendees pick their own room until a cutoff time.', side: 'bottom' } },
        { element: '[data-tour="event-rooms-inventory"]', popover: { title: 'Add locations and rooms', description: 'Add a building, then its rooms — one at a time, in bulk, or by importing a CSV.', side: 'top' } },
        { element: '[data-tour="event-rooms-assign"]', popover: { title: 'Assign rooms', description: 'Run the automatic assignment for everyone who needs a bed, or preview the result first.', side: 'top' } },
    ],

    'event-reports': [
        { popover: { title: 'Reports', description: 'Attendance and summary numbers for this event, broken down however you need.' } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-reports-filter"]', popover: { title: 'Filter the registry', description: 'Narrow the report down by category, gender, or area.', side: 'bottom' } },
        { element: '[data-tour="event-reports-export"]', popover: { title: 'Export', description: 'Download as Excel or PDF, or print directly from the browser.', side: 'bottom' } },
    ],

    'event-messages': [
        { popover: { title: 'Messages', description: 'Send your own custom email and SMS to attendees, with smart routing between the two.' } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-messages-compose"]', popover: { title: 'Compose', description: 'Write a message, pick recipients, and send it now or schedule it for later.', side: 'right' } },
        { element: '[data-tour="event-messages-filters"]', popover: { title: 'Drafts, scheduled and sent', description: 'Everything you write is saved here, whether it went out yet or not.', side: 'right' } },
    ],

    'event-billing': [
        { popover: { title: 'Billing', description: "There's no subscription — this event is billed per confirmed attendee, plus any advanced features you choose." } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-billing-estimate"]', popover: { title: 'Live estimate', description: 'This updates as people register. Nothing is charged until you finalize it.', side: 'bottom' } },
        { element: '[data-tour="event-billing-features"]', popover: { title: 'Advanced features', description: 'Optional extras like custom messages or the badge studio, priced per event. Standard attendance and check-in are always free.', side: 'top' } },
        { element: '[data-tour="event-billing-charge"]', popover: { title: 'Your bill', description: "Once finalized, pay here. After the event, no-shows are automatically refunded.", side: 'bottom' } },
    ],

    'event-settings': [
        { popover: { title: 'Event settings', description: "Change this event's basic details, or delete it entirely." } },
        EVENT_TABS_STEP,
        { element: '[data-tour="event-settings-form"]', popover: { title: 'Event details', description: 'Title, dates, description, logo and flyer.', side: 'right' } },
        { element: '[data-tour="event-settings-danger"]', popover: { title: 'Danger zone', description: 'Deleting an event removes all its registrations, attendance and report data permanently.', side: 'top' } },
    ],

    billing: [
        { element: '[data-tour="billing-explainer"]', popover: { title: 'How billing works', description: "There's no subscription — each event is billed per confirmed attendee once it's finalized. Open an event's own Billing tab to pick features, finalize, and pay." } },
        { element: '[data-tour="billing-events-table"]', popover: { title: 'Your event bills', description: 'Every bill across all your events, in one place.', side: 'top' } },
    ],

    organization: [
        { element: '[data-tour="organization-branding"]', popover: { title: 'Your identity', description: 'Your logo and organization name appear across the manager workspace and on attendee-facing pages.' } },
        { element: '[data-tour="organization-messaging"]', popover: { title: 'Send as your organization', description: 'Set your own sender name and email address so messages to attendees look like they come from you, not the platform.', side: 'top' } },
    ],

    team: [
        { popover: { title: 'Team & access', description: 'Managers, ushers, and other staff accounts for your company.' } },
        { element: '[data-tour="team-add-member"]', popover: { title: 'Add a team member', description: 'Create a login for a manager or usher and assign them to specific events.', side: 'bottom' } },
    ],

    'merge-duplicates': [
        { popover: { title: 'Merge duplicates', description: 'If the same person was registered twice under slightly different details, merge them here.' } },
        { element: '[data-tour="merge-duplicates-search"]', popover: { title: 'Find and compare', description: 'Search for a name, phone, or email, select two matching records, and choose which one should survive. Their history moves to the one you keep.', side: 'bottom' } },
    ],
};

function startTour(tourKey, onFinish) {
    const tourSteps = TOURS[tourKey];

    if (! tourSteps) {
        return;
    }

    // The sidebar nav is off-canvas below the lg breakpoint unless opened, so
    // skip steps that point at it there rather than highlighting something invisible.
    const isDesktopNav = window.innerWidth >= 1024;

    const steps = tourSteps.filter((step) => {
        if (! step.element) {
            return true;
        }
        if (step.element.startsWith('[data-tour="nav-') && ! isDesktopNav) {
            return false;
        }

        return document.querySelector(step.element);
    });

    if (steps.length === 0) {
        return;
    }

    const driverObj = driver({
        showProgress: true,
        allowClose: true,
        overlayColor: 'rgba(7, 20, 38, 0.7)',
        steps,
        onDestroyed: () => {
            if (onFinish) {
                onFinish();
            }
        },
    });

    driverObj.drive();
}

function markTourSeen(tourKey) {
    const url = document.body.dataset.tourCompleteUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (! url || ! token) {
        return;
    }

    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ tour: tourKey }),
    }).catch(() => {});
}

window.startOnboardingTour = () => {
    const tourKey = document.body.dataset.tourKey;
    if (tourKey) {
        startTour(tourKey);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const tourKey = document.body.dataset.tourKey;

    if (tourKey && document.body.dataset.autoStartTour === 'true') {
        startTour(tourKey, () => markTourSeen(tourKey));
    }

    document.querySelectorAll('[data-tour-restart]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (tourKey) {
                startTour(tourKey);
            }
        });
    });
});
