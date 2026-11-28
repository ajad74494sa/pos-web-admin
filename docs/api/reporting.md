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

Sales, sales-payment, and product-sales endpoints return paginated JSON with
`data`, `links`, and `meta`. Sales returns finalized invoices and includes
customer, totals, payment status, and payment method keys. Product sales returns
one row per top-level sale line, with returned quantities deducted from
`sold_quantity` and `subtotal`.

Sales payments returns payment records associated with sales or opening
balances. Parent payments allocated to sales are included once and associated
with the first linked sale. A return payment has `is_return: 1`; callers should
treat its amount as a return rather than a received payment.

Popular products returns the ranked products in `data`, plus `chart.labels` and
`chart.values` matching the web report chart.

Invalid filters return `422 Unprocessable Entity`. Missing or invalid tokens
return `401 Unauthorized`; users lacking the required permission receive
`403 Forbidden`. Validation errors use Laravel's JSON response format.
