# MEZ-57 — Group Purchases and Company Accounts Contract

## Scope

MEZ-57 provides the shared backend contract consumed by KORA App Passager and KORA OPS Web. It does not implement the final mobile or guichet screens. The MVP supports bus reservations, direct and ordered multi-segment itineraries, personal payers and company-credit payers.

## Group rules

The group booking settings are stored in `group_booking_settings` and start at a minimum of 5 and a maximum of 10 passengers. Both values are server-controlled. A quote or hold above the configured maximum is rejected atomically. All passengers in a group must use the same direct trip or the same ordered itinerary, including the same connections.

`purchase_type=group` is captured when the reservation is created and `group_size` stores the original transaction size. The active passenger count is derived from passenger/ticket status; cancellation does not rewrite the original group classification.

## Pricing contract

Dynamic pricing remains responsible for the per-leg fare and coefficient. The group pricing service then aggregates all legs and passengers:

```text
O/D base fare
→ dynamic pricing adjustment
→ aggregate all passengers and legs
→ automatic group discount
→ final total
```

The group discount is a dedicated, percentage-only rule in `group_discount_rules`. It is not a dynamic-pricing rule and is not a code-based promotion. The initial seeded rule is active at 10 passengers and 10%. A group of 5–9 is valid but receives no discount by default. Only one active rule may cover an effective period. Rule changes affect new quotes only.

Quotes and reservations snapshot `subtotal_amount`, `dynamic_adjustment_amount`, `group_discount`, `total_amount`, currency and `passenger_allocations`. Amounts are whole FCFA. Allocation uses floor division plus deterministic remainder distribution, so allocations always sum exactly to the final total.

## Reservation ownership

`reservations.user_id` is replaced because the old field mixed the operator who created a reservation with its payer. The replacement is:

```text
created_by_user_id
payer_user_id       nullable
payer_type          personal|company
company_account_id  nullable
purchase_type       individual|group
group_size          nullable
```

`created_by_user_id` identifies the authenticated creator. `payer_user_id` identifies a personal payer. A company payer uses `company_account_id`. API responses expose UUIDs, never internal numeric identifiers.

Passenger phone is required for group/company passenger capture, normalized before persistence, and used to link an existing identity record. Automatic account creation remains the MEZ-55 identity service responsibility; this booking module does not duplicate registration logic.

## Company credit

Company accounts support `company`, `ngo` and `embassy` types, an active/inactive lifecycle, a monthly credit limit and active members. Membership is scoped to the company and the user. Only one active referent is allowed per account.

Credit is calculated for the calendar-month `period_key`:

```text
available = monthly limit - confirmed debits + confirmed refunds - active holds
```

There is no monthly carry-over. A company credit hold is created during the seat hold transaction while the company row is locked. Insufficient credit rolls back the entire reservation and seat hold. Payment confirmation converts the hold into one immutable debit. Expired or cancelled holds release reserved credit. Refunds append a compensating transaction and never edit the original debit.

`company_credit` is a distinct payment method and does not create a cash-session movement. Payment and credit conversion are idempotent through the payment idempotency key and unique reservation/hold relationships.

## API extensions

The existing endpoints remain the integration surface:

```text
POST /api/v1/bus/quotes
POST /api/v1/bus/itinerary-quotes
POST /api/v1/bus/holds
POST /api/v1/bus/itinerary-holds
POST /api/v1/bookings/{reservation_uuid}/passengers
POST /api/v1/payments/initiate
```

Hold requests may include:

```json
{
  "payer": {
    "type": "company",
    "company_account_uuid": "..."
  }
}
```

Amounts and discount values are never accepted from the client. The server reads the immutable quote snapshot, validates payer membership/company status, and calculates all totals.

Protected management endpoints expose company account/member management and group discount/settings management. Finance fields such as the credit limit, available credit and ledger remain restricted to users with the relevant Finance permissions; mobile and guichet users receive only the scoped account selection data.

## Cancellation

Full cancellation marks all active passengers, segments and tickets cancelled/refunded while preserving the reservation snapshot. Individual cancellation marks only the selected passenger and their segments/ticket. Its refund uses the frozen passenger allocation; remaining passengers keep their original prices and the group discount is not clawed back. Repeated cancellation calls are no-ops and all operations are logged.

## Verification reference

The self-contained behavior prototype is committed on branch `prototype/mez-57-group-pricing-rules` at commit `3ad3e4a` in `prototype/mez-57-group-pricing.html`. It covers the 4/5/9/10/11 passenger boundaries, disabled discounts, multi-segment aggregation, company-credit reserve/convert/expiry and partial cancellation reconciliation.
