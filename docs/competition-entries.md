# Competition entries

A competition entry belongs to exactly one competition. The competition foreign key is enforced by the database, and the public submission endpoint always takes the competition from the route rather than trusting a submitted `competition_id`.

Entries retain the entrant's submitted name and email as a historical snapshot. `customer_id` is an optional link to an existing customer whose email exactly matches the submitted email; public entrants do not need to be customers and the submission flow never creates a customer automatically. Deleting a customer clears this optional link without deleting the entry. The competition remains the authoritative parent.

The public form accepts entries only while the competition is within its configured start/end dates and below its maximum entry count. Email addresses may be used only once per competition, including across repeated public submissions. A new entry records privacy consent and starts in `pending_review`. Authorized staff can manage entries in Filament, associate a customer when appropriate, and set the review status to `pending_review`, `approved`, or `rejected`. Filament authorization remains enforced by the resource's existing HexaLite gates.

Competition entry submission is part of the existing public-form flow; this change does not introduce a second public endpoint or create customer records as a side effect.
