# Panel search result titles

Filament resources backed by application models use the model's `title` attribute as their record title. The `HasPanelSearchTitle` concern supplies that attribute without requiring a database migration or a relationship query.

An existing non-empty `title` column is returned unchanged so domain workflows keep their current semantics. Otherwise, title selection uses the first non-empty, already-loaded scalar field in this order: `name`, `full_name`, `question`, `action`, `subject`, `code`, `contract_code`, `invoice_number`, `identifier`, `label`; when none exists, it combines `first_name` and `last_name`, then falls back to the model's humanized class name. For generated labels, a persisted record's primary key is appended to distinguish records with the same display value. The accessor does not read email, addresses, notes, or other potentially sensitive fields and does not traverse relationships, avoiding accidental disclosure and N+1 queries.

Resources continue to rely on their existing authorization and global-search configuration. The title is a display label, not a permission grant or a new searchable database column.
