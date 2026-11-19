# KORA Parcels intercity delivery and shared payments

The post-MVP delivery covers parcels between KORA hubs on a direct Bus departure. Pickup and home delivery depend on the ride-hailing phase. Direct deposit at a partner point is in scope; collection by a third party is not.

The Parcels module owns intake requests, custody and assignment history. The departure remains an object of the Bus module. A return leg reuses the transport operations with its own assignment.

## Deposit locations

A deposit location is a `parcel_hub`: one commercial entity bound to one loading stop. The commercial entity is either an internal agency (`kind=agency`) or a partner point (`kind=partner_point`, backed by `kora_points.kora_point_id`, one hub per partner point). A partner point never duplicates an agency record: its agency is the partner agency already bound to the KORA point.

Deposit locations are archived with `archived_at`, never deleted. Foreign keys stay restrictive so existing parcels keep their history.

## Routing rule

Structural reachability is evaluated at configuration time: both stops must belong to the same line and the origin stop must precede the destination stop in line order. Operational availability is a separate, non-persisted calculation over `scheduled` departures inside `operations.parcelRoutingHorizonDays`.

A partner point whose stop is not served in the correct order by an eligible departure is not proposed and intake is rejected. No implicit transfer to an agency, no substitute hub and no virtual stop are ever inferred. Serving such a point requires an explicit transfer capability, which is out of scope and would need its own ADR.

The deposit hub is exactly the origin hub of the selected corridor. `parcels.selling_agency_id` is a frozen snapshot of the deposit hub agency taken at intake and is never recalculated.

Parcel charges are distinct payable objects linked by foreign key to payments and invoices. They reuse the existing providers, webhooks, cash registers and fiscal services. Weighing precedes payment and every confirmed payment settles a single charge. Any confirmation received after the quote expires or is replaced opens a reconciliation.

The label QR identifies the parcel for logistics scans. The withdrawal code is distinct, verified online and consumed on physical handover.
