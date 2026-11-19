# Product Create and Update API

This guide documents the Connector API endpoint for creating a product. The
endpoint follows the product fields and variation structure used by the web
product creation form.

## Endpoint

```text
POST /connector/api/product
```

The route is registered in
[the Connector API routes](../../Modules/Connector/Routes/api.php) and handled
by [the product API controller](../../Modules/Connector/Http/Controllers/Api/ProductController.php).

## Authentication and permissions

Send a valid API access token as a bearer token. The authenticated user must
belong to a business, have the `product.create` permission, and have an active
subscription with available product quota.

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```

The business and creator are taken from the authenticated user. Do not send
`business_id` or `created_by`.

## Request format

Use JSON for requests without files. Use `multipart/form-data` when uploading
an image or brochure. Numeric values must be ordinary numbers (for example,
`12.50`), not locale-formatted values (for example, `1.250,00`).

Every request requires these common fields:

| Field | Type | Rules |
| --- | --- | --- |
| `name` | string | Required; maximum 191 characters. |
| `type` | string | Required; `single`, `variable`, or `combo`. |
| `unit_id` | integer | Required; a non-deleted base unit belonging to the authenticated business. |
| `barcode_type` | string | Required; one of `C39`, `C128`, `EAN-13`, `EAN-8`, `UPC-A`, `UPC-E`, `ITF-14`. |
| `tax_type` | string | Required; `inclusive` or `exclusive`. |

### Optional common fields

| Field | Type | Rules and behavior |
| --- | --- | --- |
| `sku` | string | Maximum 191 characters. If omitted or blank, generated using the business SKU prefix and product ID. |
| `brand_id` | integer | Must be a non-deleted brand in the authenticated business. |
| `category_id` | integer | Must be a non-deleted product category in the authenticated business. |
| `sub_category_id` | integer | Must be a product sub-category under the selected `category_id`. |
| `tax` | integer | Tax-rate ID belonging to the authenticated business. |
| `enable_stock` | boolean | Defaults to `true`. |
| `alert_quantity` | number | Minimum `0`; defaults to `0`. |
| `not_for_selling` | boolean | Defaults to `false`. |
| `enable_sr_no` | boolean | Defaults to `false`. |
| `weight` | number | Minimum `0`. |
| `product_description` | string | Optional product description. |
| `sub_unit_ids` | integer array | Optional unit IDs belonging to the authenticated business. |
| `secondary_unit_id` | integer | Optional base unit ID belonging to the authenticated business. |
| `warranty_id` | integer | Optional warranty ID belonging to the authenticated business. |
| `expiry_period` | number | Optional; saved only when product expiry is enabled for the business and `expiry_period_type` is also provided. |
| `expiry_period_type` | string | `months` or `days`. |
| `preparation_time_in_minutes` | integer | Minimum `0`. |
| `product_custom_field1` … `product_custom_field20` | string | Optional; maximum 191 characters each. |
| `product_locations` | integer array | Optional active location IDs the authenticated user can access. If omitted and the user has exactly one accessible active location, that location is assigned automatically. Otherwise no locations are assigned. |
| `product_racks` | object | Optional location-ID keyed object; each entry may contain `rack`, `row`, and `position` strings (maximum 191 characters). Locations must be active and accessible to the user. |
| `has_module_data` | boolean | Set to `true` when sending fields consumed by an installed module's product-save hook. Module-specific field names and rules depend on that module. |

To look up valid IDs, use the Connector read APIs such as
`GET /connector/api/unit`, `GET /connector/api/brand`,
`GET /connector/api/taxonomy`, `GET /connector/api/tax`,
`GET /connector/api/business-location`, and `GET /connector/api/variation`.
These endpoints also require authentication.

## Single product

For `type: "single"`, all five price fields below are required and must be
non-negative except `profit_percent`, which only needs to be numeric.

| Field | Meaning |
| --- | --- |
| `single_dpp` | Default purchase price excluding tax. |
| `single_dpp_inc_tax` | Default purchase price including tax. |
| `profit_percent` | Profit percentage. |
| `single_dsp` | Default selling price excluding tax. |
| `single_dsp_inc_tax` | Default selling price including tax. |

Example:

```json
{
  "name": "Coffee",
  "type": "single",
  "unit_id": 1,
  "barcode_type": "C128",
  "tax_type": "exclusive",
  "sku": "COFFEE-001",
  "brand_id": 2,
  "category_id": 4,
  "tax": 1,
  "enable_stock": true,
  "alert_quantity": 5,
  "product_locations": [1],
  "single_dpp": 10,
  "single_dpp_inc_tax": 11,
  "profit_percent": 50,
  "single_dsp": 15,
  "single_dsp_inc_tax": 16.5
}
```

## Variable product

For `type: "variable"`, send at least one `product_variation` group and at
least one variation value in each group. Each group needs either a `name` or a
valid `variation_template_id`. When a template ID is supplied, `name` may be
omitted.

Each variation value requires `value`, `default_purchase_price`,
`dpp_inc_tax`, `profit_percent`, `default_sell_price`, and
`sell_price_inc_tax`. `sub_sku` is optional and is generated when omitted.

`sku_type` is optional:

| Value | Behavior |
| --- | --- |
| `with_out_variation` | Generated variation SKU uses the product SKU and a sequence. This is the default. |
| `with_variation` | Generated variation SKU uses the product SKU and the variation value. |

Example:

```json
{
  "name": "Coffee Beans",
  "type": "variable",
  "unit_id": 1,
  "barcode_type": "C128",
  "tax_type": "exclusive",
  "sku": "BEANS",
  "product_locations": [1],
  "sku_type": "with_out_variation",
  "product_variation": [
    {
      "name": "Roast",
      "variations": [
        {
          "value": "Light",
          "sub_sku": "BEANS-L",
          "default_purchase_price": 8,
          "dpp_inc_tax": 8,
          "profit_percent": 50,
          "default_sell_price": 12,
          "sell_price_inc_tax": 12
        },
        {
          "value": "Dark",
          "sub_sku": "BEANS-D",
          "default_purchase_price": 9,
          "dpp_inc_tax": 9,
          "profit_percent": 50,
          "default_sell_price": 13.5,
          "sell_price_inc_tax": 13.5
        }
      ]
    }
  ]
}
```

To use an existing variation template, replace `"name": "Roast"` with
`"variation_template_id": 3`. The ID must belong to the authenticated business.

## Combo product

For `type: "combo"`, component variation IDs, quantities, and unit IDs are
parallel arrays: entries at the same numeric index belong together. Every
component variation must belong to the authenticated business, every quantity
must be greater than zero, and every unit must belong to that business.

The total and selling prices are required inputs; calculate them in the client
according to the selected components and applicable tax.

| Field | Type | Rules |
| --- | --- | --- |
| `composition_variation_id` | integer array | Required; component variation IDs. |
| `quantity` | number array | Required; every value must be greater than `0`. |
| `unit` | integer array | Required; component unit IDs. |
| `item_level_purchase_price_total` | number | Required; minimum `0`. |
| `purchase_price_inc_tax` | number | Required; minimum `0`. |
| `profit_percent` | number | Required. |
| `selling_price` | number | Required; minimum `0`. |
| `selling_price_inc_tax` | number | Required; minimum `0`. |

Example:

```json
{
  "name": "Coffee and Pastry Combo",
  "type": "combo",
  "unit_id": 1,
  "barcode_type": "C128",
  "tax_type": "exclusive",
  "product_locations": [1],
  "composition_variation_id": [15, 23],
  "quantity": [1, 2],
  "unit": [1, 1],
  "item_level_purchase_price_total": 8,
  "purchase_price_inc_tax": 8.8,
  "profit_percent": 50,
  "selling_price": 12,
  "selling_price_inc_tax": 13.2
}
```

## File uploads

When using `multipart/form-data`, include ordinary product fields alongside
these optional file fields:

| Field | Purpose |
| --- | --- |
| `image` | Product image. Required only if the business setting requires product images. |
| `product_brochure` | Product brochure/document; must match one of the configured document MIME types. |
| `variation_images[]` | Images for a single product's variation. |
| `variation_images_{groupIndex}_{valueIndex}[]` | Images for a variable product value; for example, `variation_images_0_0[]` is the first value in the first group. |

Product and variation photos must be JPEG, PNG, GIF, WebP, or BMP and may not
exceed the configured image size limit (5 MB by default). SVG is not accepted.
Product brochures are restricted to the configured document MIME types and
the configured document size limit.

For array values in a multipart request, use bracket notation, for example
`product_variation[0][variations][0][value]`.

Multipart single-product example:

```bash
curl --request POST 'https://YOUR_POS_HOST/connector/api/product' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --form 'name=Coffee' \
  --form 'type=single' \
  --form 'unit_id=1' \
  --form 'barcode_type=C128' \
  --form 'tax_type=exclusive' \
  --form 'single_dpp=10' \
  --form 'single_dpp_inc_tax=11' \
  --form 'profit_percent=50' \
  --form 'single_dsp=15' \
  --form 'single_dsp_inc_tax=16.5' \
  --form 'product_locations[]=1' \
  --form 'image=@./coffee.jpg'
```

## Example cURL request

```bash
curl --request POST 'https://YOUR_POS_HOST/connector/api/product' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "name": "Coffee",
    "type": "single",
    "unit_id": 1,
    "barcode_type": "C128",
    "tax_type": "exclusive",
    "single_dpp": 10,
    "single_dpp_inc_tax": 11,
    "profit_percent": 50,
    "single_dsp": 15,
    "single_dsp_inc_tax": 16.5,
    "product_locations": [1]
  }'
```

## Success response

The endpoint returns **201 Created** with the same product resource structure
used by the Connector product read API. The resource contains the saved
product, its variation groups and variations, related unit/category/brand/tax
objects, image URL, and assigned locations.

Representative response:

```json
{
  "data": {
    "id": 123,
    "name": "Coffee",
    "business_id": 10,
    "type": "single",
    "sku": "POS0123",
    "enable_stock": true,
    "image_url": "https://YOUR_POS_HOST/img/default.png",
    "product_variations": [
      {
        "id": 456,
        "name": "DUMMY",
        "variations": [
          {
            "id": 789,
            "name": "DUMMY",
            "sub_sku": "POS0123",
            "default_purchase_price": "10.0000",
            "dpp_inc_tax": "11.0000",
            "default_sell_price": "15.0000",
            "sell_price_inc_tax": "16.5000",
            "variation_location_details": [],
            "media": [],
            "discounts": []
          }
        ]
      }
    ],
    "unit": {
      "id": 1,
      "actual_name": "Pieces",
      "short_name": "Pc(s)"
    },
    "product_locations": [
      {
        "id": 1,
        "name": "Main Store"
      }
    ]
  }
}
```

The actual resource includes additional product and relation fields. Foreign
key columns such as `unit_id`, `category_id`, `brand_id`, `tax`, and
`tax_type` are omitted by the existing product resource formatter.

## Edit and update a product

Use the existing show endpoint to fetch a product and its current values before
displaying the edit form:

```text
GET /connector/api/product/{product_id}
```

Update a product with either `PUT` or `PATCH`:

```text
PUT /connector/api/product/{product_id}
PATCH /connector/api/product/{product_id}
```

The authenticated user must have the `product.update` permission. Product IDs
are scoped to the user's business. The product type cannot be changed through
this endpoint. Send only fields to change; omitted fields retain their current
values. Send `Accept: application/json`. Use JSON when no photo/document is
being uploaded and `multipart/form-data` when uploading files.

Common product fields use the same names and validation rules as the create
endpoint. In addition, update accepts the following:

| Field | Type | Behavior |
| --- | --- | --- |
| `image` | file | Optional replacement photo; omitting it keeps the current photo. Uses the same allowed image types and size limit as create. |
| `product_brochure` | file | Optional additional brochure/document. |
| `product_locations` | integer array | If provided, replaces the product's locations with active locations accessible to the user. Other existing locations the user cannot access are preserved. |
| `single_dpp`, `single_dpp_inc_tax`, `single_dsp`, `single_dsp_inc_tax` | number | For single products, each provided price updates that variation price. |
| `profit_percent` | number | For single/combo products, updates the variation margin; for variable products it is supplied per variation value. |
| `item_level_purchase_price_total`, `purchase_price_inc_tax`, `selling_price`, `selling_price_inc_tax` | number | For combo products, update the corresponding combo variation prices. |
| `composition_variation_id`, `quantity`, `unit` | arrays | For combo products, optionally replace component variations. These parallel arrays must use matching indexes and business-owned IDs. |

For variable products, use the product and variation IDs from the GET response.
When editing existing values, include every product variation group and every
existing variation value so the API can preserve them safely:

```json
{
  "name": "Coffee Beans - Updated",
  "product_variation_edit": {
    "12": {
      "name": "Roast",
      "variations_edit": {
        "34": {
          "value": "Light",
          "sub_sku": "BEANS-L",
          "default_purchase_price": 8,
          "dpp_inc_tax": 8,
          "profit_percent": 50,
          "default_sell_price": 12,
          "sell_price_inc_tax": 12
        },
        "35": {
          "value": "Dark",
          "sub_sku": "BEANS-D",
          "default_purchase_price": 9,
          "dpp_inc_tax": 9,
          "profit_percent": 50,
          "default_sell_price": 13.5,
          "sell_price_inc_tax": 13.5
        }
      }
    }
  }
}
```

The numeric object keys (`12`, `34`, `35`) are IDs from the existing product
resource, not array positions. New values can be added under an existing group
using its `variations` array with the same fields as the variable-product
create request. This endpoint does not delete existing variation groups or
values.

Example multipart update that replaces the product photo:

```bash
curl --request PATCH 'https://YOUR_POS_HOST/connector/api/product/123' \
  --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  --header 'Accept: application/json' \
  --form 'name=Coffee - New Label' \
  --form 'image=@./coffee-new.jpg'
```

On success, update returns **200 OK** with the updated product resource in the
same format as GET. A product from another business returns `404`; missing
`product.update` permission or an inaccessible location returns `403`.

## Errors

| HTTP status | Meaning |
| --- | --- |
| `401 Unauthorized` | Missing or invalid API token. |
| `403 Forbidden` | Missing `product.create` or `product.update` permission; inactive subscription or exhausted product quota on create; or inaccessible location (and inaccessible rack location on create). |
| `404 Not Found` | The authenticated business record or requested business-owned product could not be found. |
| `422 Unprocessable Entity` | Invalid or missing fields, IDs from another business, a sub-category that does not belong to the selected category, or combo arrays with mismatched indexes. |

Validation errors use Laravel's JSON validation format when
`Accept: application/json` is sent. Permission, subscription, quota, and
location-access errors use:

```json
{
  "error": {
    "message": "The error message."
  }
}
```

## Notes

- Creating a product does not create opening stock. Add opening inventory
  separately using the application's stock workflow.
- The endpoint is not idempotent. Retrying a successful POST creates another
  product; clients should avoid automatic retries without checking the result.
- Do not assume SKU uniqueness unless the calling application enforces it.
