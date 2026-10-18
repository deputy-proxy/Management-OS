# Public forms inventory

Public forms are dedicated web views and controllers, separate from Filament. They use CSRF protection, request validation, a per-IP rate limit, a honeypot field, and required privacy consent. Consent timestamps are stored on the resulting record.

| Model | Public route | Audience and submission workflow | Owner |
| --- | --- | --- | --- |
| CustomerLead | `/public/enquiries` | Prospective customers submit contact details and interests; new leads enter the review/follow-up pipeline with status `new`. | Customer management |
| CompetitionEntry | `/public/competitions/{competition}/entries/create` | Entrants submit a title and entry content to an active, non-expired competition; entries remain internal for review. | Competition management |
| NewsletterSubscriber | `/public/newsletter/subscribe` | Visitors request newsletter subscription; duplicate email submissions are idempotent. | Marketing |
| ProductReview | `/public/reviews/create` | Existing customers submit a review for a supported course, camp, workshop, or event. Customer association is derived from the email on an existing customer record; reviews await internal moderation. | Product management |

## Security and behavior

- Public endpoints do not expose Filament forms or accept IDs/statuses, conversion fields, contract files, customer foreign keys, or other protected attributes from request input.
- Customer lead source, status, contact timestamp, full name, and consent timestamp are server-derived.
- Competition IDs come from the route, and future/expired competitions and competitions at their configured entry limit reject submissions. Attachments are optional for public submissions.
- Product review type is allowlisted; the referenced record must exist, rating is 1–5, and customer association comes from the existing customer record rather than submitted IDs.
- Requests are limited to five per minute per IP and include a honeypot field. Public file uploads are not accepted.
- Consent timestamps are persisted on each submitted record. Newsletter submissions do not attach an arbitrary customer ID; review submissions require the submitted email to match an existing customer record.
