# Escrow

> **WITHDRAWN — 2026-10-23. Historical document.**
>
> Escrow no longer exists on this platform. Buyers pay each seller's **own**
> Paystack account, using credentials the seller enters in their dashboard, and
> mypage.ng never holds, releases, refunds or pays out buyer money. There is no
> escrow pool, no release action and no dispute arbitration.
>
> The decision, its consequences and what replaces it are recorded at the top of
> `before lunch.md` under *"Payment architecture — decided 2026-10-23"*.
>
> This file is kept because the code it describes still exists and is being
> removed deliberately rather than forgotten. Read the decision first, then treat
> everything below as a record of why the code looked the way it did.

Money a buyer has paid that is not the seller's yet.

## Why it exists

A buyer paying a stranger online has no way to get the money back if the goods
never arrive. A seller will not ship until they have been paid. Escrow breaks
that deadlock: the platform takes the money at checkout, parks it, and releases
it when a trigger fires.

Without it the only options are "pay first and hope" or "trust the seller", and
neither is a product you can sell to someone who has been burned before.

## The lifecycle

```
checkout → sale settles ──> escrow pool (held)
                              │
       ┌──────────────────────┼──────────────────────┐
       │                      │                      │
  buyer releases         7 days pass          dispute raised
       │                      │                      │
       ▼                      ▼                      ▼
   released              released                frozen
   (seller paid)         (seller paid)      (admin decides only)
                                                     │
                                            ┌────────┴────────┐
                                            ▼                 ▼
                                        released          refunded
```

## The rules that matter

**Money moves in exactly one place.** `EscrowService::move()` is the only method
that transfers anything. Every other path calls it.

**It cannot move twice.** `release()`, `refund()` and `resolve()` all
`lockForUpdate()` the row and then check the status. A replayed webhook or a
double-clicked button returns `false` rather than paying out again.

**A dispute is admin-only.** Once frozen, neither buyer nor seller can move the
money. `release()` refuses anything not `held`, so a buyer cannot quietly undo
their own complaint. This is deliberate: the seller has an incentive to freeze any
order they might lose, and the buyer has an incentive to freeze any order they
might regret. Only a human looking at the evidence should break that tie.

**An escrow row is a record of where the money went, not of what a flag says.**
This is the subtlest rule and it has its own section below.

## Enabling it

Escrow ships dark:

```env
FEATURE_ESCROW=true          # off by default
ESCROW_AUTO_RELEASE_DAYS=7   # how long a buyer's silence holds the money
```

A seller can then be opted in or out individually with a row in `feature_flags`.
`Feature::enabled('escrow', $sellerId)` reads both: config is the platform
default, the row is the exception. Killing it in config wins, so a bad rollout
can be stopped without touching every row.

## Where buyers reach it

`GET /{slug}/escrow/{token}`

No login. The token is an HMAC of the escrow id, so it is unguessable, and it
only ever opens one order. This is a deliberate trade: asking a buyer to create
an account to release their own money is how a hold ends up expiring by accident.

The link is surfaced on the order tracking page, but only after the buyer has
already proved the order is theirs by matching reference **and** email/phone.
That gate is the first line of defence; the token is the second.

## Proving it is you

Order tracking asks for two things. The escrow page asks for one.

A buyer reaches `/{slug}/escrow/{token}` by having the token, and the token is
the whole authorisation — for reading the page *and* for releasing the money.
The token is 24 hex characters of HMAC, so it cannot be guessed; what it cannot
survive is being passed around. It ends up in a WhatsApp thread, a screenshot,
a shared phone, a forwarded email. Anyone holding it can release the money to
the seller.

So the honest summary of the current posture:

| Action | Needs |
|---|---|
| See the order | reference + contact, or the token |
| **Release the money** | **the token** |
| **Raise a dispute** | **the token** |

Two of the three gates that matter are a single factor, and that factor is a
string designed to be shared.

The fix moves the gate from the URL onto the action: a one-time code, delivered
over a second channel to the email and phone already captured at checkout,
required on release and on dispute. The emailed artefact becomes a link to a
code-entry page rather than the release capability itself, so a compromised
inbox yields nothing on its own. Full design, threat model and sequencing are in
[`before lunch.md`](../before%20lunch.md).

Three smaller things are wrong today and are worth knowing regardless of whether
that plan ships:

**The token never expires.** Order and booking links expire after 30 days
(`BuyerLinks::EXPIRES_DAYS`) precisely because a permanent link to somebody's
address and order history is not worth a convenience. The escrow link is a
*write* link and has no such protection.

**`GET /escrow/{token}` is unthrottled.** Release is capped at 10/min and
dispute at 5/min, but the page itself has no limit at all, so a leaked token can
be probed indefinitely. `track-order` is unthrottled too, and it is itself a
reference-and-contact guessing oracle.

**`findEscrow()` loads every escrow on the page** and compares tokens in PHP:

```php
return Escrow::withoutGlobalScopes()
    ->where('store_page_id', $page->getKey())
    ->get()
    ->first(fn (Escrow $escrow) => hash_equals($escrow->releaseToken(), $token));
```

Correct, and `hash_equals` keeps it timing-safe — but it is O(n) in the seller's
whole escrow history, on every page view, forever. Storing the token in an
indexed column makes it one query.

## When a dispute is raised

Frozen means frozen: `release()`, `refund()` and `resolve()` all refuse to move
anything that is not `held`, so the money stays with the platform until a human
decides. That part is right and should not change.

The gaps are around it.

**There is no evidence to look at.** A dispute is a `reason` string, 20–1000
characters, from one side only. The seller has no way to put their case forward
at all — no delivery photo, no courier receipt, no proof the goods ever left
their hands. So the section above says a human breaks the tie "looking at the
evidence", and in practice the evidence is one text box written by the party
with something to gain.

**A dispute is permanent.** `ESCROW_AUTO_RELEASE_DAYS` applies to a `held`
escrow and will not touch a `frozen` one. Nothing is scheduled. A buyer who
disputes and then stops replying holds the seller's money until an admin
notices, and an admin noticing is not a mechanism.

**The plan** gives both sides symmetric evidence — text plus at least one photo
— on private storage with signed URLs, and adds a response deadline so a dispute
resolves itself when a party goes quiet. Neither is a security control; the
second is a fairness one, and it is arguably the more urgent of the two, since
today the seller cannot present a case at all.


## The bug worth remembering

`OpenEscrowAfterPayment` runs off a queue, *after* settlement. It originally
decided whether to open an escrow by asking `Feature::enabled()` — which
describes the present, not the past.

The failure window: a sale settles while escrow is off, so the money goes
straight to the seller. Before the queued job runs, someone switches escrow on.
The job now sees the flag on and writes an escrow row for a sale that was never
escrowed.

The result is a phantom escrow. The row claims ₦4,432.50 is held; the pool holds
₦0. When it auto-releases, the platform pays the seller ₦4,432.50 they already
received, and the escrow wallet goes negative. `canOverdraw()` allows it, because
platform accounts are allowed to overdraw by design — that guard exists for
settlement timing, and it silently swallowed this.

The fix is one line of intent: ask the ledger, not the flag.

```php
private function settledIntoEscrow(Transaction $transaction): bool
{
    return WalletLedger::query()
        ->where('transaction_id', $transaction->getKey())
        ->where('reason', 'sale_to_escrow')
        ->exists();
}
```

`LedgerService` names the movement `sale_to_escrow` when it routes there, so that
row is proof the money went to the pool. The invariant this restores:

> the sum of held escrows can never exceed the balance of the escrow pool.

`tests/Feature/Billing/EscrowTest.php` asserts exactly that, plus the two flag
timings that broke it.

## Tests

- `tests/Feature/Billing/EscrowTest.php` — ledger routing, settle-once, the
  flag-timing cases and the held-rows-matches-the-pool invariant.
- `tests/Feature/Billing/EscrowBuyerTest.php` — the buyer page, token gating,
  cross-seller token rejection, double-click safety, and that a buyer's dispute
  never records the seller as the complainant.

## Related

- [`before lunch.md`](../before%20lunch.md) — the proposed buyer
  authentication and dispute-evidence design: threat model, prioritised
  measures, data model, sequencing and open decisions. Nothing in it is built
  yet.
- `dashboard.orders.escrow-dispute` is currently registered twice, identically,
  at `routes/web.php:148` and `:149`. Harmless today because Laravel matches the
  first, but delete one when that route is next touched.
