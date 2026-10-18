# Notification settings

## Defaults and authorization

Notification preferences are per user and opt-in. A missing preference is disabled for both `in_app` and `email`. Preferences never grant permission to read a model or record. The settings page builds its event catalog from registered Filament resources and their HexaLite `*.index` permission. A user sees CRUD events only for models their current role can view. Every delivery checks current role access again, so a stale preference stops applying when access is removed. Preferences for currently hidden events are retained but do not authorize delivery.

Users manage their own preferences at **System → Notification Settings**. The save operation accepts only event keys present in the current user's server-generated catalog and only the `in_app` and `email` channels. Settings are not editable for another user through this page.

## Supported events

Each registered application model resource that defines an `*.index` gate supports `model.{resource-key}.created`, `model.{resource-key}.updated`, and `model.{resource-key}.deleted`. The model key is derived from the resource's index permission, for example `model.course.created`. Recipients are users whose current role grants that resource's index permission and who explicitly enabled the matching channel.

`comment.created` covers new comments on records the recipient can access. The existing owner/subscriber recipient rule remains in place and excludes the commenter. Comment notification access is checked against the specific commentable model before delivery.

## Delivery and retries

Eloquent model events are scheduled after the surrounding transaction commits. Each recipient, event occurrence, and channel has one `notification_deliveries` row protected by a unique database constraint. In-app notifications are sent to the Filament database and broadcast channels only when enabled; email deliveries use a queued job with three attempts and backoff. A delivery marked `sent` is not emitted again. Email preferences and model access are checked again immediately before sending; disabled or unauthorized deliveries are marked `skipped`. Failures are recorded in `last_error` and logged.

Model-event email content contains the event label, record display title, and a link to the resource list. It does not serialize the model or include arbitrary field values. Comment emails use the existing `CommentCreatedMail` template and contain the record title, comment text, and admin link.

## Extension guidance

New registered resources with a HexaLite index gate automatically appear in the CRUD event catalog. Domain-specific events must be added to the catalog and delivered through `NotificationPreferenceService`, with recipient access checked against the relevant model. Do not call Filament database/broadcast notifications or send email directly for a supported event, because doing so bypasses preferences and delivery deduplication.
