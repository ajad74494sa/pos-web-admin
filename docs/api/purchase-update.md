# Update Purchase API

Use this endpoint to change an existing purchase's status, append supplier
payments, or do both in one request. It does not edit purchase lines or replace
existing payment records.

## Endpoint

```text
PUT or PATCH /connector/api/purchase/{purchase_id}
```

Send a valid API bearer token and `Accept: application/json`. A status change
requires `purchase.update` or `purchase.update_status`. Adding payments requires
`purchase.payments`; specifying an account also requires `account.access`.
The purchase must belong to the authenticated business and be at a location
accessible to the user.

## Request fields

| Field | Type | Rules and behavior |
| --- | --- | --- |
| `status` | string | Optional: `received`, `pending`, `ordered`, or `draft`. Changing status adjusts stock and purchase/sale mappings using the web purchase workflow. A purchase with any payment cannot be changed to `draft`. |
| `payments` | array | Optional list of new payments to append. Existing payments are preserved. Omit this field to leave payments unchanged. |
| `payments.*.amount` | number | Required and greater than zero; in the business base currency. The new payments cannot exceed the purchase's remaining balance. |
| `payments.*.method` | string | Required location-enabled method or `advance`. Supported values are `cash`, `card`, `cheque`, `bank_transfer`, `other`, `advance`, and `custom_pay_1` through `custom_pay_7`; custom methods must be enabled for the purchase location. |
| `payments.*.paid_on` | date | Optional payment date; defaults to the current date and time. |
| `payments.*.note` | string | Optional note, maximum 1,000 characters. |
| `payments.*.account_id` | integer | Optional active account available to the user's permitted locations. Requires `account.access`; not allowed with `advance`. |
| `payments.*.transaction_no_1..7` | string | Optional transaction reference for the corresponding `custom_pay_1..7` method. |

Status changes also honor the business's configured transaction edit period.
Appending payments remains available according to the user's payment
permission and the purchase's remaining balance.

This endpoint intentionally does not edit or delete existing payment entries.
Payment corrections should use the existing audited web workflow; a payment
history replacement is not supported by this API.

## Change status

```json
{
  "status": "received"
}
```

Changing a purchase to `received` adds its quantities to stock. Changing it
from `received` to another status removes those quantities. The API also runs
the existing purchase/sale mapping adjustment logic.

## Append a payment

```json
{
  "payments": [
    {
      "amount": 25.5,
      "method": "bank_transfer",
      "paid_on": "2026-11-19",
      "note": "Supplier payment"
    }
  ]
}
```

## Change status and append a payment

```json
{
  "status": "received",
  "payments": [
    {
      "amount": 25.5,
      "method": "cash"
    }
  ]
}
```

## Response

Success returns **200 OK** with the updated purchase, including its current
`status`, calculated `payment_status`, and complete payment list.

## Errors

| HTTP status | Meaning |
| --- | --- |
| `401 Unauthorized` | Missing or invalid API token. |
| `403 Forbidden` | Missing status/payment/account permission or inaccessible purchase location. |
| `404 Not Found` | Purchase does not exist in the authenticated business or accessible locations. |
| `422 Unprocessable Entity` | Invalid status/payment fields, disabled payment method, inaccessible account, draft/payment conflict, purchase outside the status edit period, or payment exceeds the remaining balance. |
