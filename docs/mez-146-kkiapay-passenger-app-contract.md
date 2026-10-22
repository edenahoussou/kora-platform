# MEZ-146 — KKiaPay Contract Expected by the Passenger App

## Scope

What `apps/kora-app-passenger` needs from the backend to collect a bus
payment through KKiaPay. It does not describe the backend implementation.
Written from the existing KORA payment layer and from the KKiaPay
integration already running in ALLO STOP V1.

KKiaPay is a gateway, like FedaPay: it routes money to MTN, Moov or
Celtiis, and it also accepts bank cards. FedaPay is already consumed by
the app as a **provider** inside the `mobile_money` method. KKiaPay is
expected to arrive the same way.

## 1. Catalogue

`GET /api/v1/payments/methods` must announce KKiaPay. The app accepts
either shape and hides its placeholder card as soon as one appears:

- a provider with `code: "kkiapay"` inside the `mobile_money` method, or
- a method with `code: "kkiapay"`.

The provider shape is preferred unless KKiaPay is meant to carry bank
cards, which `mobile_money` does not cover. A separate method would then
need its own screen, and that is a product decision, not a technical one.

The provider entry must carry the public configuration the SDK needs:

```json
{
  "code": "kkiapay",
  "label": "KKiaPay",
  "enabled": true,
  "networks": ["mtn_momo", "moov_flooz", "celtiis_cash"],
  "requires": ["phone"],
  "interaction": "sdk",
  "verification": "server_verify",
  "polling_interval_seconds": 10,
  "public_key": "<KKiaPay public key>",
  "sandbox": true
}
```

`public_key` and `sandbox` come from the server, never from the app
bundle: the key differs between staging and production, and a key
compiled into a release cannot be rotated without shipping a new build.
Only the **public** key travels. The secret key stays server-side — it is
what signs the verification in step 3.

`interaction: "sdk"` tells the app the payment is collected in-process
rather than by a USSD push or a redirect. The existing values
(`push`, `push_or_redirect`) stay valid for FedaPay.

## 2. Collection

The app runs the KKiaPay SDK with the amount, the passenger's phone,
name and email, and the public key above. The SDK returns a terminal
status and, on success, a `transactionId`.

Nothing is trusted at this point. A client-side SDK result is a claim,
not a payment: the ticket is issued only after step 3.

## 3. Confirmation

The app needs one endpoint that takes the reservation and the KKiaPay
transaction, verifies it against KKiaPay with the secret key, and
returns the payment in the shape the app already reads
(`PaymentAttempt`):

```http
POST /api/v1/payments/kkiapay/confirm
{
  "reservation_uuid": "...",
  "transaction_id": "...",
  "idempotency_key": "..."
}
```

Requirements:

- **Idempotent.** The app may send the same confirmation twice — the SDK
  callback and the polling fallback can both fire, and the passenger may
  reopen the app on the payment screen. The second call must return the
  same payment, not create or charge a second one.
- **Authoritative amount.** The server compares the amount KKiaPay
  reports against the reservation. A mismatch is a failure, not a
  success.
- **Same response shape** as `POST /payments/initiate`, so the app's
  existing states (pending, succeeded, failed, refused, expired) and its
  polling on `GET /payments/{uuid}` keep working unchanged.

## 4. Resuming

The passenger can close the app while KKiaPay is open, or lose the
network before the confirmation lands. The app must be able to ask, for
a reservation, whether it is already paid — otherwise it would offer to
pay again a reservation that is settled, and charge twice.

`GET /payments/{uuid}` already serves this once a payment exists. What
is missing is the path from a **reservation** to its payment when the
app never received one, which happens exactly when the confirmation call
was lost.

## 5. Failure vocabulary

The app shows the server's message when it has one. It needs to tell
apart, at minimum:

- the passenger cancelled — no payment, no error shown;
- the operator refused (insufficient balance, wrong PIN) — the passenger
  retries;
- the transaction is unknown or already consumed — support case, the
  `provider_reference` is shown so it can be quoted.

## Out of scope

Collecting a card payment through KKiaPay, if it is modelled as its own
method, needs a dedicated screen. Not covered here.
