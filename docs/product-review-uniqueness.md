# Product review uniqueness and replacement policy

Management-OS allows one active review per customer and product. Soft-deleting a review removes it from the active review set but preserves the historical row. The customer may then submit a replacement review, which starts in `pending_review`; the old review is not restored or overwritten.

The `product_reviews_product_customer_unique` index enforces this rule in the database. MySQL does not support partial unique indexes, so a nullable virtual generated column (`active_customer_id`) evaluates to `customer_id` for active rows and `NULL` for soft-deleted rows. The unique index covers `(product_type, product_id, active_customer_id)`. Multiple deleted rows can have a `NULL` generated value, while active duplicates collide at the database boundary. The same migration is exercised on SQLite by CI, but production is MySQL and should also be checked against the actual deployed MySQL version before rollout.

The public submission flow checks for an active duplicate for a friendly validation response and translates a matching database uniqueness violation into the same controlled message. The database constraint remains the final guard against concurrent submissions. Other database errors are rethrown rather than mislabeled as duplicate reviews.

The migration checks for active duplicates before replacing the existing constraint. Rollback refuses to restore the previous all-time uniqueness rule if replacement history has created duplicate `(product_type, product_id, customer_id)` combinations; reconcile those records before rollback. Do not delete historical reviews merely to force a rollback.
