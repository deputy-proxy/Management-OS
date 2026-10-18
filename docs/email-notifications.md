# Email notifications

## Event catalog

| Event key | Trigger | Recipients | Content |
| --- | --- | --- | --- |
| `comment.created` | A comment is created through Relaticle Comments | The record owner and comment subscribers, excluding the commenter | Record title, comment text, and a link to the admin area |

Email is sent only to application users who explicitly enabled the `email` channel for the event in `notification_preferences`. A missing preference means disabled. This table is the persistence contract for preference UI work tracked in issue #169.

## Delivery behavior

The event handler creates one delivery row per recipient and event, then dispatches a queued job after the database transaction commits. A unique database key prevents duplicate rows when the same event is replayed. A delivery marked sent is not sent again. Queue attempts are limited to three with increasing backoff; terminal failures are recorded in `notification_deliveries.last_error` and logged. Email transport is outside the business transaction.

## Content and privacy

The email uses the application's current locale and includes only the record title, comment body, and admin link. Blade escapes user-controlled content. Delivery timestamps use the application timezone. No attachments or additional record data are included.

## Preferences

Preferences are opt-in. The job checks the preference immediately before sending so a user who disables email after enqueueing will not receive the message.
