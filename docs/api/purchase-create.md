# Create Purchase API

This guide covers the Connector API for creating purchases. The API follows the
web purchase workflow for supplier/location checks, purchase lines, taxes,
discounts, payment lines, documents, and stock updates. It accepts a concise
JSON `products` array rather than the web form's `purchases` field structure.

## Endpoint and access

```text
POST /connector/api/purchase
```

Send a valid API bearer token. The user must have `purchase.create`, an active
business subscription, and access to the selected active location. Supplier,
product, variation, tax, and payment-account IDs must belong to or be accessible
within that business.

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```

Use `multipart/form-data` when attaching a purchase document. The document must
match one of the configured document MIME types and the configured upload-size
limit.

## Request fields

| Field | Type | Rules and behavior |
| --- | --- | --- |
| `contact_id` | integer | Required supplier (or supplier/customer) contact ID in the authenticated business. |
| `location_id` | integer | Required active business location accessible to the authenticated user. |
| `transaction_date` | date | Optional; defaults to the current date and time. |
| `status` | string | `received`, `pending`, `ordered`, or `draft`; defaults to `received`. Stock is increased only for `received`. Draft purchases cannot include payments. |
| `ref_no` | string | Optional, maximum 191 characters. Omitted or blank references are generated using the business purchase sequence. |
| `exchange_rate` | number | Optional positive exchange rate; defaults to `1`. Purchase line prices, shipping, tax, discounts, expenses, and total are stored in the business currency using this rate. |
| `tax_id` | integer | Optional purchase-level tax rate selectable in this business's web purchase form. Tax rates marked for tax-group-only use are not accepted; tax groups are supported. |
| `discount_type` | string | `fixed` or `percentage`; defaults to `fixed`. |
| `discount_amount` | number | Optional non-negative discount in purchase currency. A percentage cannot exceed 100%, and the discount cannot exceed the purchase subtotal. |
| `shipping_charges` | number | Optional non-negative shipping charge in purchase currency. |
| `shipping_details`, `additional_notes` | string | Optional text, maximum 5,000 characters each. |
| `pay_term_number`, `pay_term_type` | integer, string | Optional supplier terms. Send both together; type is `days` or `months`. |
| `additional_expense_key_1..4` | string | Optional labels for up to four additional expenses. |
| `additional_expense_value_1..4` | number | Optional non-negative expense amounts in purchase currency. |
| `custom_field_1..4` | string | Optional purchase custom fields. |
| `shipping_custom_field_1..5` | string | Optional shipping custom fields. |
| `document` | file | Optional purchase document; use multipart form data. |

### Purchase lines

`products` is required and must contain at least one line. Quantities are in
the product's **base unit**. Products and variations must belong together and
to the authenticated business.

| Field | Type | Rules and behavior |
| --- | --- | --- |
| `product_id` | integer | Required business-owned product ID. |
| `variation_id` | integer | Required variation ID belonging to `product_id`. |
| `quantity` | number | Required and greater than zero. Must be a whole number when the product's base unit disallows decimals. |
| `unit_cost_before_discount` | number | Required, non-negative unit cost in purchase currency before line discount. |
| `discount_percent` | number | Optional line discount from 0 to 100; defaults to 0. |
| `purchase_line_tax_id` | integer | Optional tax rate selectable in this business's web purchase form. Tax rates marked for tax-group-only use are not accepted; tax groups are supported. |
| `lot_number` | string | Optional lot number, maximum 191 characters. |
| `mfg_date`, `exp_date` | date | Optional manufacture and expiration dates. |

For each line, the API calculates the discounted unit cost and line tax. It
then applies the purchase discount, purchase-level tax, shipping, and
additional expenses to calculate the total.

### Payments

`payments` is optional. Each payment needs `amount` and `method`. Payment
amounts are in the business base currency; their sum cannot exceed the purchase
total in that currency. Methods must be enabled for the selected location, or
be `advance`. Advance payments cannot specify `account_id`.

| Field | Type | Rules and behavior |
| --- | --- | --- |
| `amount` | number | Required and greater than zero. |
| `method` | string | Required location-enabled method or `advance`. Supported values are `cash`, `card`, `cheque`, `bank_transfer`, `other`, `advance`, and `custom_pay_1` through `custom_pay_7`; custom methods must be enabled for the selected location. |
| `paid_on` | date | Optional payment date; defaults to the current date and time. |
| `note` | string | Optional payment note, maximum 1,000 characters. |
| `account_id` | integer | Optional active account accessible to the user. Requires `account.access`. |
| `transaction_no_1..7` | string | Optional transaction reference for the corresponding `custom_pay_1..7` method. |

## Example JSON request

```json
{
  "contact_id": 12,
  "location_id": 1,
  "transaction_date": "2026-10-25",
  "status": "received",
  "exchange_rate": 1,
  "discount_type": "fixed",
  "discount_amount": 5,
  "shipping_charges": 3,
  "additional_expense_key_1": "Freight",
  "additional_expense_value_1": 2,
  "products": [
    {
      "product_id": 10,
      "variation_id": 18,
      "quantity": 5,
      "unit_cost_before_discount": 12.5,
      "discount_percent": 5,
      "purchase_line_tax_id": 3,
      "lot_number": "LOT-001",
      "mfg_date": "2026-01-01",
      "exp_date": "2027-01-01"
    }
  ],
  "payments": [
    {
      "amount": 10,
      "method": "cash",
      "paid_on": "2026-10-25",
      "note": "Initial payment"
    }
  ]
}
```

## Example with a document

```bash
curl --request POST 'https://YOUR_POS_HOST/connector/api/purchase' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --form 'contact_id=12' \
  --form 'location_id=1' \
  --form 'status=received' \
  --form 'products[0][product_id]=10' \
  --form 'products[0][variation_id]=18' \
  --form 'products[0][quantity]=5' \
  --form 'products[0][unit_cost_before_discount]=12.5' \
  --form 'document=@./supplier-invoice.pdf'
```

## Response

Success returns **201 Created** with the purchase ID/reference, supplier and
location, totals, product lines, payment lines, and purchase status. Purchases
with `status: "received"` update inventory through the same purchase-line
utility used by the web workflow.

## Errors

| HTTP status | Meaning |
| --- | --- |
| `401 Unauthorized` | Missing or invalid API token. |
| `403 Forbidden` | Missing `purchase.create` permission, inactive subscription, inaccessible location, or missing `account.access` for an account payment. |
| `404 Not Found` | Business record or requested business-owned resource was not found. |
| `422 Unprocessable Entity` | Invalid fields, foreign-business IDs, disabled payment method, unsupported account/location assignment, or invalid totals. |

Validation errors use Laravel's JSON format when `Accept: application/json` is
sent. Authorization errors use:

```json
{
  "error": {
    "message": "The error message."
  }
}
```

## Integration notes

- To change the status of an existing purchase or append additional payments,
  see the [Update Purchase API](./purchase-update.md). Updates preserve existing
  payment history; they do not replace payment records.
- Look up suppliers with `GET /connector/api/contactapi`, products with
  `GET /connector/api/product`, and available locations with
  `GET /connector/api/business-location`.
- Payment account options are available from `GET /connector/api/payment-accounts`
  only to users with `account.access`; the API returns account IDs and names
  limited to the user's permitted locations.
- Send line quantities in the product base unit. This endpoint does not accept
  the web form's sub-unit or purchase-order line fields.
- A received purchase updates stock. Pending, ordered, and draft purchases do
  not increase stock at creation.
