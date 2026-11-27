# CommonCalendar

A WordPress plugin for library events, room reservations, and staff scheduling, built by librarians for librarians. Originally developed by SMTI for McArthur Library.

Need help or have a feature request? Contact Corie at cleclair@southernmainetech.org or cleclair@mcarthurlibrary.org

## Quick install

**Requirements:** WordPress 6.0 or later and PHP 7.4 or later. Newsletter PDF generation also requires the PHP DOM, mbstring, and GD extensions and the bundled Composer dependencies.

1. Download the installable CommonCalendar ZIP, then in WordPress go to **Plugins > Add New > Upload Plugin**, upload it, and activate **CommonCalendar**. Alternatively, copy the `community-calendar-study-rooms` folder into `wp-content/plugins/` and activate it.
2. Open **CommonCalendar > Settings** to add rooms, configure hours and booking rules, and choose your email notification settings.
3. Give staff WordPress accounts with the Editor role, or grant their role the `manage_calendar_system` capability. Administrators and Editors receive the capability on activation.
4. Add `[calendar_event_calendar]` to a WordPress page or shortcode block and publish it. See [Shortcodes](#shortcodes) for other displays.
5. Use **CommonCalendar > Room Approvals** for pending room requests and **Event Registrations** to review event sign-ups. The **Home** dashboard links to event management.

The plugin creates its database tables on activation. Deactivating it does not delete events, registrations, rooms, or bookings.

## Features

### Public calendars and website displays

- **Unified calendar:** Show published events and public room bookings together, or use the event-only and room-focused shortcodes. Visitors can open event details and request a room directly from the calendar.
- **Day, Week, and Month views:** Switch views and move between periods using the calendar toolbar. On narrow screens, days are stacked for easier reading and room requests.
- **Filters:** Filter events by category, recurring events, or named series. Selecting both event types shows matches for either; leaving both unchecked shows all event types. Category and event-type filters can be combined; room bookings remain visible in combined calendars. The **Filter by category** heading is hidden by default and can be restored in **Settings > Calendar display**.
- **Upcoming event cards:** Display the next three to five published events with images, dates, and a details button.
- **Printing:** Print the selected calendar view or, in Month and Week views, a chronological list of published events for the selected period. The event list respects category filters and excludes room bookings. Printing opens a new window, so pop-ups must be allowed.
- **Calendar subscriptions:** Subscribe to the published-event iCalendar feed in Google Calendar, Apple Calendar, or Outlook. The feed excludes room bookings, registrations, and staff entries; refresh timing is controlled by the calendar app, and subscribing does not register anyone for an event.
- **Display settings:** Choose the week start, initial month, event category labels, category colors, room colors, and staff booking-type colors.
- **Privacy-aware display:** Public room entries never reveal patron contact details. Hide room names, booking status, or all room bookings from logged-out visitors. When room names are hidden, public bookings use the default room color.
- **Theme integration:** Calendars and forms are native WordPress shortcodes that can be placed in pages, posts, or shortcode blocks. Calendar pages send no-cache headers because staff controls depend on login status; clear page, host, or CDN caches after updating.

### Events, recurrence, and series

- **Event management:** Staff can create, edit, publish, and draft events; add descriptions, categories, images from the WordPress Media Library, registration limits, rooms, and equipment. Event images appear on upcoming-event cards, listings, and event details.
- **Configurable categories:** Add, rename, and remove event categories. Categories still used by saved events are retained.
- **Recurring events:** Repeat an event daily, weekly, or monthly, ending on a date up to two years away or after up to 100 occurrences. Each occurrence has its own event and registrations.
- **Recurring edits:** Edit one occurrence, this and future occurrences, or all occurrences. Chosen fields, including room and equipment, are applied to the selected occurrences; dates shift by the corresponding amounts and registrations stay with their original occurrences. Room or equipment conflicts reject the entire group edit. Deleting an occurrence deletes only that occurrence.
- **Named series:** Group individually scheduled events under a named series, independently of recurrence. Published series events appear together in event details, including past events; drafts remain private.
- **Linked rooms:** Reserve an active room for an event, including multi-day events. Conflicting room reservations for newly created recurring occurrences are skipped and reported; those event occurrences are still created.

### Event registration and waitlists

- **Online registration:** Collect attendee names and email addresses, prevent duplicate registrations for the same event and email, and use site-wide and event-specific capacity limits and registration cutoffs.
- **Per-event control:** Enable or disable registration for each event and optionally show attendee counts and capacity publicly. Site-wide registration is enabled by default, while new events do not enable registration by default unless that setting is changed. Public counts are enabled by default for new events; existing events retain their prior visibility setting on upgrade.
- **Custom forms:** Customize instructions, labels, privacy notice, button text, and result messages. Add up to 20 default or event-specific fields: short text, paragraph text, email, phone, dropdown, or checkbox. Fields can be required.
- **Staff review:** View registrations and custom answers under **Event Registrations**. Draft upcoming events are included there; past events remain accessible through the event manager.
- **Series sign-ups:** Attendees can select eligible events in a named series or select all. The current event is always selected; additional events must be open and have matching registration questions. Results show successes and individual failures; additional events are not automatically waitlisted.
- **Recurring sign-ups:** Staff can allow attendees to register for all upcoming occurrences of a recurring event. Unavailable, full, or question-mismatched occurrences are reported individually.
- **Waitlists:** Visitors can join a full event's waitlist using the same registration details. When a place opens before registration closes, the earliest waiting person is registered and emailed. Staff can cancel registrations and waitlist entries; patrons cannot cancel themselves.
- **Confirmations and reminders:** Optional immediate confirmations and 24-hour reminders are configured separately under **Settings > Event emails**. A registration remains saved if its confirmation cannot be sent, and the attendee is told to contact staff. Reminders require attendee opt-in and run through WordPress's hourly scheduled task; disabling the global reminder setting also disables previously opted-in reminders. Failed reminder sends are retried before the event; successful sends are not repeated. The hourly task also reconciles waitlists, even when reminders are disabled.
- **Registration alerts:** Send staff alerts to the staff notification address and optionally specify up to 10 event-specific addresses for registration updates.

### Room requests and bookings

- **Public requests:** Visitors choose an available room, date, and time and can include an optional reason. Public requests are for a single day and use the site's timezone.
- **Approval workflow:** Requests require staff approval by default; settings can instead confirm valid requests immediately. Review pending requests under **Room Approvals**, approve or deny them, and optionally include a comment in the requester's email.
- **Availability and conflicts:** Room forms show a live conflict warning, and availability is checked again on save. Overlapping pending requests and approved bookings are treated as conflicts; public requests cannot override them.
- **Booking rules:** Configure the maximum advance window, minimum notice, allowed time increment, and optional maximum public-request duration. A duration of zero means no maximum; staff bookings are exempt.
- **Opening hours:** Configure weekly hours and special dates, including annually recurring exceptions. A specific-date exception takes precedence over an annual one. Public requests follow the selected day's hours, and closed days do not show a request button.
- **Time choices:** Room-request dropdowns use 15-minute intervals and also enforce any stricter configured public increment. Existing staff bookings at other times remain editable.
- **Staff bookings:** Staff can create private confirmed bookings and may book outside opening hours with a warning. A staff double-booking requires acknowledgment; for a recurring public event created from the room form, that acknowledgment covers only the first occurrence.
- **Room directory:** Add rooms, set capacities and colors, and mark rooms unavailable for new reservations. Rooms with booking history or linked staff entries cannot be deleted; mark them unavailable instead.
- **Linked reservations:** Events and staff-calendar entries can reserve rooms for their scheduled time. Multi-day reservations are split into daily segments and block overlapping public requests.

### Equipment and staff scheduling

- **Equipment inventory:** Add equipment, set quantities, and mark items unavailable for new reservations. Existing future reservations prevent reducing a quantity below the amount already reserved at the same time.
- **Equipment reservations:** Attach equipment to room requests, staff room bookings, events, or staff entries, even if an event or staff entry has no room. Pending and approved reservations count against inventory. An equipment conflict prevents the entire save, including a recurring-event series.
- **Room equipment requests:** Under **Settings > Room booking rules**, admins can turn equipment requests on or off for public and staff room booking forms. When enabled, equipment is in a collapsed section. Existing reservations remain in place when disabled; the separate event and staff time editors are unaffected.
- **Private staff calendar:** Manage meetings, sick time, time off, and other configurable booking types in a separate calendar visible only to authorized staff. Filter entries by type, add optional private notes, and use 15-minute time choices; existing entries at other times remain editable.
- **Personal tasks:** Each authorized staff user can enable their own task sidebar under **CommonCalendar > Settings > My tasks**, without administrator assignment. The setting is off by default and affects only that account. Once enabled, use the checkmark button in the dashboard, on calendars, or in the staff app. Add notes, a due date, and multiple events, and mark tasks complete. Tasks are private to the account; disabling the setting hides the checkmark everywhere without deleting tasks. Deleted events are no longer available to attach.
- **Staff controls:** Authorized staff can add, edit, and delete entries from the calendar or dashboard. Event, room-booking, and staff-entry creation or editing requires staff initials; the most recent initials are visible only to staff. Public room requests do not require initials.
- **Staff dashboard:** The Home page summarizes upcoming events, room requests, active rooms, and staff entries, including today's remaining and in-progress items. **Rooms & Events** shows the room calendar alongside upcoming events.

### Email notifications and approvals

- **Notification options:** Enable staff room-request alerts, room-requester receipts, event-registration confirmations, staff registration alerts, and event reminders independently. Registration confirmations are enabled by default for new settings; the other optional notifications are off by default. Existing saved preferences are preserved.
- **Room-request recipients:** Enter up to 10 notification addresses under **Settings > Email notifications**. Each receives a separate email. If the list is empty, the staff notification address is used; otherwise only the listed addresses receive room alerts. Staff event alerts and the test email continue to use the staff notification address.
- **Approve and deny links:** Staff emails for pending requests can include HTTPS links that work without a WordPress login. Opening a link displays a confirmation page with an optional requester comment; the request changes only after confirmation, and normal conflict checks and requester notifications still apply.
- **Link settings and security:** Turn the email links on or off and choose a validity period in days or **Never expires**. Links require an HTTPS WordPress admin URL and are enabled for seven days by default. Turning them off pauses previously sent links; re-enabling restores those that have not expired. Duration changes apply only to new links. Anyone holding or forwarded a link can make a decision, so send notifications only to trusted recipients. A never-expiring link remains usable until the request is decided or deleted. If links cannot be created, the staff email directs recipients to WordPress Room Approvals.
- **Custom templates:** Edit each notification's subject and plain-text message. Messages are sent as styled HTML, with labeled lines presented as form-style details. Templates support `{subject}`, `{message}`, and the additional placeholders listed for each email. Approval and denial templates must contain `{message}` or `{comment}` so staff comments are delivered.
- **Sender, logo, and testing:** Configure a plugin-only sender address and place the WordPress site logo at the top or bottom of emails. Leave the sender blank to use the WordPress default. Save settings before using **Send test email**, which sends to the staff notification address.
- **Cancellations:** Staff can include a message when deleting an event that has not ended or cancelling a public or staff-created private room booking. Event attendees, waitlisted people, or the room contact receive the applicable notice. Deleting an event also deletes its registrations and linked room reservation. Cancellation messages are not saved. Templates can use `{message}` and `{comment}`; standard details and any otherwise omitted staff message are appended when necessary. Waitlist and status emails use WordPress mail regardless of optional submission-confirmation settings.
- **Delivery:** Email uses the website's WordPress mail configuration. Configure and test your host's mail service, sender authorization, and spam filtering; WordPress accepting an email does not guarantee inbox delivery. Logo images may be blocked by email clients. Delivery failures are logged and, for staff actions, shown in a notice. Reminder and waitlist timing depends on site visits or a server cron triggering WordPress scheduled tasks.

### Staff communication

- **Administrator-assigned access:** Under **CommonCalendar > Settings > Communication**, administrators check or uncheck **Enable communication** for a calendar staff account, then click that account's **Save access** button. Existing assignments are preserved when updating. This section is visible only to administrators; personal tasks remain self-enabled under **My tasks**. Administrators with calendar access can always use and manage communication. Removing module or calendar access blocks further reads, posts, and attachment downloads without deleting previous messages.
- **Shared channels:** Administrators create named channels under **CommonCalendar > Communication**. All assigned staff can read and post in every channel. This release does not include private channels, direct messages, threads, unread badges, or voice/video calls.
- **On-page chatbox:** The **Communication** button beside the task checkmark and the staff app's Communication tab open a chatbox without leaving the calendar. The dashboard Communication page also provides this button. It is available even when personal tasks are disabled. Close the chatbox with its close button or Escape.
- **Load on demand:** Calendar pages render only an empty chatbox shell; channels and messages are requested after opening it. Closing it stops refresh polling and cancels pending reads. The initial message request loads only the latest 50 messages, without attachment contents. Files download separately when clicked.
- **Message history:** Messages are saved in WordPress and refresh every ten seconds only while the chatbox is open and the page is visible. Load older messages in batches of 50; reading history pauses automatic refresh until **Refresh messages** returns to the latest conversation. Reopening the chatbox or refreshing also loads newly created channels. Staff can delete their own messages; administrators can delete any message. Deleting a message also deletes its attachment.
- **Private attachments:** Attach one PDF, JPG, PNG, or plain-text file of up to 5 MB per message, subject to the host's upload limits. Files are stored in the plugin database, not the public Media Library, and download access is checked each time. Database backups include attachments, and base64 storage adds roughly one-third to file sizes. Do not post patron information; this is an internal coordination tool, not a confidential records system.

### Internal documentation wiki

- **Administrator-controlled module:** Enable the wiki under **CommonCalendar > Settings > Wiki**. It is off by default. When enabled, **CommonCalendar > Wiki** appears in the WordPress dashboard for calendar staff. All calendar staff can read and edit every wiki page and manage folders. Turning it off hides the menu and blocks the editor, folder management, and file downloads without deleting anything.
- **Dashboard only:** The wiki has no calendar button, public shortcode, public page, or staff-app tab. Its documents are not included in public site searches or exposed through WordPress REST endpoints.
- **Pages and editing:** Use **New page** to write documentation with WordPress's Visual/Text editor: headings, lists, links, formatted text, and HTML. Save or publish the page, then read it in the wiki's document view. Publishing a wiki page does not make it public. **Manage pages and revisions** opens the native page list and editors; WordPress revision history and its usual editing-lock behavior apply. Revision availability follows the site's WordPress configuration.
- **Nested folders:** Use **Manage folders** to create folders and select parent folders. Assign pages using the editor's Wiki folders panel; a page can belong to multiple folders. The wiki browser filters by folder (including its descendants) and searches page titles and text, with 20 pages per list page.
- **Private files:** The editor's **Private attachments** panel accepts PDF, JPG, PNG, GIF, WebP, plain text, DOC/DOCX, XLS/XLSX, and PPT/PPTX files, up to 5 MB each and subject to host limits. Attach one file per save and repeat to add more. Files are stored privately in the plugin database rather than the public Media Library. The native public-media upload button is disabled in wiki editors. Download links can be copied into documentation; they remain usable only while the reader has wiki access. Files are downloaded separately, not embedded as inline images.
- **Removal and storage:** Select **Remove on save** to delete attachments. Trashing a page blocks its file downloads but preserves files for restoration; permanently deleting it removes its attachments. Attachment errors are reported separately because WordPress may already have saved the document. Backups include wiki documents, folders, revisions, and files; base64 file storage adds roughly one-third to file sizes. Automatic calendar retention does not remove wiki documentation. Do not store patron or other confidential personal information.

### Reports, newsletters, and privacy

- **Reports and CSV:** Preview date-filtered room, equipment, and event reports and download separate CSV files. Dates are inclusive and use the site's timezone. Room and equipment reports show approved reservations, including private staff usage without personal contact details. Event reports include published and draft events overlapping the range and registration counts. Equipment usage predating the feature cannot be reconstructed.
- **Monthly newsletter PDF:** Under **Settings > Newsletter**, choose a month and download a PDF of published events overlapping that month, including dates, times, descriptions, images, and the WordPress site logo. If no logo is set, the site name is used. Drafts, registrations, and room bookings are excluded. Images must be locally accessible to WordPress; unavailable images are reported.
- **WordPress privacy tools:** **Tools > Export Personal Data** and **Tools > Erase Personal Data** include CommonCalendar records associated with the requested email address. Erasure anonymizes matching room bookings and registrations while retaining counts, and removes matching waitlist entries.
- **Optional retention:** Set a retention period in days, or leave it at zero to disable automatic cleanup. Older room bookings and registrations are anonymized after the booking or event ends; older waitlist entries are removed. Cleanup runs in batches through WordPress scheduled tasks and permanently removes contact details, request reasons, and registration answers.
- **Permissions:** Only users with `manage_calendar_system` can view staff schedules or manage calendar content. The staff login link uses the normal WordPress login; there is no separate plugin password system.
- **Time display:** Calendars, forms, reports, newsletters, and emails use 12-hour AM/PM display independently of the WordPress time-format setting. Opening hours are entered with AM or PM; stored times and calendar exports remain machine-readable.

## Shortcodes

Add these to a page, post, or shortcode block:

| Shortcode | Display |
| --- | --- |
| `[calendar_event_calendar]` | Unified calendar of published events and pending or approved public room bookings. |
| `[calendar_room_calendar]` | Compatibility alias for the unified calendar. |
| `[calendar_events]` | Published events, event details, and registration forms. |
| `[calendar_upcoming_events]` | Upcoming event cards; defaults to three. Use `count="4"` or `count="5"` to show more, up to five. |
| `[calendar_rooms]` | Room bookings and upcoming published events. Private bookings and event-linked room reservations are not shown as separate room entries. |
| `[calendar_combined]` | Combined events and public room bookings calendar. |
| `[calendar_staff_calendar]` | Staff-only monthly calendar. Visitors without staff access cannot view schedule details. |
| `[calendar_staff_app]` | Dedicated installable staff and room calendar app page; see [Staff calendar app](#staff-calendar-app). |
| `[calendar_system]` | Unified calendar, upcoming event listings, and registration forms. |
| `[calendar_room_request]` | Public study-room request form. |

Use only one calendar shortcode on a page. The calendar subscription URL is available on the public calendar and under **Settings > Calendar shortcodes**.

## Staff calendar app

The staff app is an online-only progressive web app (PWA). It provides the existing staff and room calendars, personal tasks when enabled, and Communication when assigned, without the WordPress dashboard or site navigation. The room calendar also shows published events, just like `[calendar_rooms]`. Bookings, messages, permissions, and settings remain in WordPress.

1. Install or update the plugin, then confirm the website uses HTTPS and WordPress **Settings > Permalinks** uses a permalink structure that gives pages trailing-slash URLs (such as **Post name**).
2. Create and publish a new, public, non-password-protected page, not the site home page. Put `[calendar_staff_app]` on that page by itself. Use a dedicated page that is not a parent of other pages. Do not put other content or calendar shortcodes on it: the app replaces the page's normal theme and content.
3. Open the published page on each device and sign in with a WordPress account that has `manage_calendar_system` access. Visitors without access cannot open either calendar in the app. Exclude the app page and its query-string URLs from any page, host, or CDN cache.
4. On iPhone or iPad, open the app page in Safari, tap **Share**, then **Add to Home Screen**. On Android, open it in Chrome and use the browser menu's **Install app** or **Add to Home screen**. Desktop Chrome or Edge may offer **Install app** in the address bar or browser menu. Browser wording varies.

Open the installed icon to use the app. It requires an internet connection and a working WordPress site; it does not store staff or room calendar data for offline use. If offline, it displays a connection message rather than accepting bookings. Staff can sign out from the calendar; WordPress sessions and permissions still apply.

## Privacy considerations

An anonymized future reservation still occupies its room or event place; staff should cancel it separately if the person wants to free that place. Export and erasure cannot identify personal information entered in another person's free-text responses or remove information from emails, backups, logs, or other plugins. Review the site's privacy policy and retention requirements before enabling automatic cleanup.

Personal tasks and their notes are included in WordPress personal data exports and erasure requests for the account email. The automatic booking and registration retention cleanup does not remove tasks.

Communication exports include the account's messages, channel names, attachment filenames, and timestamps, but not attachment file contents. Download files separately while the account has communication access. WordPress erasure removes that account's messages and attached files; automatic booking and registration retention does not remove communication history. Erasure does not remove copies quoted by other users, downloaded files, or backups.

When room bookings are hidden from logged-out visitors, signed-in users can still see public bookings, and room requests and availability checks continue to work. Clear any page or CDN caches after changing this setting.

## Updates

Back up the site and database before updating. To update manually, upload the new ZIP under **Plugins > Add New > Upload Plugin** and select **Replace current with uploaded**. Keep the `community-calendar-study-rooms` folder name in the ZIP; plugin data is stored in the database.

Install the first CommonCalendar release manually when upgrading from CommonsCal: older versions expect the old plugin name and ZIP filename during automatic updates.

Once [the GitHub repository](https://github.com/cleclair-sudo/CommonsCal) is public, an active CommonCalendar installation also checks its latest published GitHub release during WordPress's normal plugin update checks. Install updates from **Plugins** or **Dashboard > Updates**, or enable WordPress auto-updates. The repository must be public and the site must be able to reach GitHub. Use **Dashboard > Updates > Check again** to check again. If the check fails, WordPress displays a warning; uploading a ZIP remains available.

For each update, increment the final version component and create an updated plugin ZIP. For each GitHub release, tag a stable version such as `v0.09.71`, publish the release (not a draft or prerelease), and attach a ZIP named `CommonCalendar-0.09.71.zip`. Its single top-level folder must be `community-calendar-study-rooms` and contain `community-calendar-study-rooms.php` with the same version and Update URI. The ZIP must contain the complete plugin, not GitHub's automatically generated source archive. Do not reuse a release version; WordPress only offers newer versions. The first version with GitHub update support must itself be installed by ZIP.

## Developer notes

`community-calendar-study-rooms.php` is the WordPress plugin entry point; keep it at the plugin folder root. Feature code is organized under `includes/`:

- `core.php` and `updates.php`: database setup, settings, shared helpers, registration-field definitions, and GitHub release checks.
- `frontend-forms.php`, `calendars.php`, and `ical.php`: public and staff forms, calendar displays, shortcodes, and event subscriptions.
- `admin-settings.php` and `admin-management.php`: settings, dashboard screens, and staff management actions.
- `room-validation.php`, `room-actions.php`, and `room-hours.php`: room availability, conflicts, booking actions, and opening hours.
- `events.php`, `event-series.php`, `registrations.php`, and `waitlist.php`: event scheduling, series, registration, reminders, and waitlist promotion.
- `equipment.php` and `reports.php`: equipment inventory and reservations, report previews, and CSV exports.
- `privacy.php` and `newsletter.php`: privacy tools and monthly newsletter PDF export.
- `tasks.php` and `communication.php`: private personal tasks, administrator-assigned shared staff messaging, and protected attachments.
- `wiki.php` and `files.php`: dashboard-only documentation, nested folders, native WordPress editing, and shared private-file handling.
- Dashboard styles are in `assets/calendar-system-admin.css`.

After changing PHP dependencies, run `composer install --no-dev` and include the resulting `vendor/` folder in the plugin ZIP.
