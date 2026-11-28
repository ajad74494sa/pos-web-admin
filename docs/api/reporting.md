# Report APIs

The Connector report endpoints expose the sales, sales-payment, product-sales,
and popular-products reports available in the POS web app. Each endpoint
requires an API bearer token and applies the authenticated user's business and
permitted-location scope.

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

## Endpoints

| Report | Endpoint | Required permission |
| --- | --- | --- |
| Sales | `GET /connector/api/reports/sales` | `sell.view`, `sell.create`, `direct_sell.access`, or `view_own_sell_only` |
| Sales payments | `GET /connector/api/reports/sales-payments` | `purchase_n_sell_report.view` |
| Product sales | `GET /connector/api/reports/product-sales` | `purchase_n_sell_report.view` |
| Popular products | `GET /connector/api/reports/popular-products` | `trending_product_report.view` |

## Filters

All filters are optional query parameters.

| Parameter | Used by | Description |
| --- | --- | --- |
| `start_date`, `end_date` | All | Inclusive date bounds in `YYYY-MM-DD` format. Sales and product sales use transaction date; sales payments use payment date; popular products use sale date. |
| `location_id` | All | Filter to a permitted business location. |
| `customer_id` | Sales, sales payments, product sales | Filter by customer/contact ID. |
| `payment_status` | Sales | Filter by `due`, `partial`, `paid`, or `overdue`. |
| `payment_method` | Sales payments | Filter by payment method key, such as `cash`, `card`, or `cheque`. |
| `category_id`, `brand_id`, `variation_id` | Product sales | Filter product sale lines. |
| `category_id`, `sub_category_id`, `brand_id`, `unit_id`, `product_type` | Popular products | Filter popular-product ranking. `product_type` is `single`, `variable`, or `combo`. |
| `per_page` | Sales, sales payments, product sales | Page size from 1 to 100; defaults to 10. |
| `limit` | Popular products | Number of ranked products from 1 to 100; defaults to 5. |

Date bounds may be supplied individually. If both are supplied, `end_date`
must be on or after `start_date`.

## Examples

```bash
curl --get 'https://YOUR_POS_HOST/connector/api/reports/sales' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --data-urlencode 'start_date=2026-10-01' \
  --data-urlencode 'end_date=2026-10-31' \
  --data-urlencode 'location_id=1' \
  --data-urlencode 'per_page=25'
```

```bash
curl --get 'https://YOUR_POS_HOST/connector/api/reports/sales-payments' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --data-urlencode 'payment_method=cash' \
  --data-urlencode 'start_date=2026-10-01'
```

```bash
curl --get 'https://YOUR_POS_HOST/connector/api/reports/product-sales' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --data-urlencode 'category_id=4' \
  --data-urlencode 'start_date=2026-10-01' \
  --data-urlencode 'end_date=2026-10-31'
```

```bash
curl --get 'https://YOUR_POS_HOST/connector/api/reports/popular-products' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --data-urlencode 'start_date=2026-10-01' \
  --data-urlencode 'end_date=2026-10-31' \
  --data-urlencode 'limit=10'
```

## Responses

The sales, sales-payments, and product-sales endpoints return Laravel's
paginated JSON shape. Pagination fields are at the top level; there is no
`meta` object:

```json
{
  "current_page": 1,
  "data": [],
  "first_page_url": "https://YOUR_POS_HOST/connector/api/reports/sales?page=1",
  "from": null,
  "last_page": 1,
  "last_page_url": "https://YOUR_POS_HOST/connector/api/reports/sales?page=1",
  "links": [],
  "next_page_url": null,
  "path": "https://YOUR_POS_HOST/connector/api/reports/sales",
  "per_page": 10,
  "prev_page_url": null,
  "to": null,
  "total": 0
}
```

### Sales

Each `data` item represents a finalized invoice. `customer` contains `id`,
`name`, `supplier_business_name`, and `contact_id`. `payment_methods` is an
array of payment method keys.

```json
{
  "id": 123,
  "location_id": 1,
  "customer": {
    "id": 45,
    "name": "Example Customer",
    "supplier_business_name": null,
    "contact_id": "CUST-0045"
  },
  "invoice_no": "INV-0123",
  "transaction_date": "2026-10-25 10:30:00",
  "total_before_tax": "100.0000",
  "discount_amount": "0.0000",
  "tax_amount": "5.0000",
  "final_total": "105.0000",
  "payment_status": "paid",
  "payment_methods": ["cash"]
}
```

### Sales payments

Each `data` item includes `id`, `amount`, `is_return`, `method`, `paid_on`,
`payment_ref_no`, `transaction_no`, `transaction_id`, `invoice_no`,
`customer_id`, `customer_name`, and `customer_business_name`.
Parent payments allocated to sales are included once and associated with the
first linked sale. A return payment has `is_return: 1`; callers should treat its
amount as a return rather than a received payment.

### Product sales

Each `data` item represents one top-level sale line. Returned quantities are
deducted from `sold_quantity`; `subtotal` is the net quantity multiplied by
`unit_sale_price`. Each item contains `id`, `transaction_id`, `invoice_no`,
`transaction_date`, `location_id`, `customer_id`, `customer_name`, `product_id`,
`product_name`, `sku`, `product_variation`, `variation_name`, `sub_sku`, `unit`,
`unit_price`, `unit_sale_price`, `item_tax`, `sold_quantity`, and `subtotal`.

### Popular products

Popular products is not paginated. It returns ranked products in `data`, plus
`chart.labels` and `chart.values` matching the web report chart. Product items
contain `total_unit_sold`, `product`, `unit`, and `sku`.

## Errors

Invalid filters return `422 Unprocessable Entity` using Laravel's validation
response format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "start_date": ["The start date does not match the format Y-m-d."]
  }
}
```

Missing or invalid tokens return `401 Unauthorized`. Users lacking the
required permission receive `403 Forbidden`:

```json
{
  "error": {
    "message": "Unauthorized action."
  }
}
```
