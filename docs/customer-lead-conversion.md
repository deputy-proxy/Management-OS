# Customer lead conversion

The Customer Leads table provides a **Turn into Customer** action for qualified leads. Conversion requires all three permissions: customer_lead.update, customer.create, and customer_contract.create.

## Required confirmation and data

The operator must provide the customer type, CNP/CUI, address, contract code, start and end dates, and a PDF of the signed contract, then explicitly confirm that the document is signed. The lead supplies the customer name, email, and phone. The conversion action does not infer legal identity fields or contract terms from the lead because those fields do not exist on the lead model. A customer with matching email and CNP/CUI is reused rather than duplicated; conflicting identity details or a matching soft-deleted customer block conversion for reconciliation.

## Atomic state transition

A successful conversion creates a customer and its signed contract in one database transaction, links the contract to the source lead, and updates the lead to converted. The new customers.phone, customer_leads.converted_customer_id, customer_leads.converted_at, customer_contracts.source_customer_lead_id, and customer_contracts.signed_at fields preserve the contact detail and conversion audit trail.

A lead can have at most one conversion contract, enforced by a unique constraint on customer_contracts.source_customer_lead_id. The action is hidden for converted leads, and the conversion service rechecks the state inside a transaction to reject repeated submissions. Existing customer contracts remain valid; their new source-lead and signed-at fields are nullable.

If contract creation fails, the transaction rolls back the customer creation and leaves the lead unchanged.