<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Events\ProductsCreatedOrModified;
use App\Media;
use App\Product;
use App\SellingPriceGroup;
use App\Variation;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Connector\Transformers\CommonResource;
use Modules\Connector\Transformers\ProductResource;
use Modules\Connector\Transformers\VariationResource;

/**
 * @group Product management
 * @authenticated
 *
 * APIs for managing products
 */
class ProductController extends ApiController
{
    protected $productUtil;

    protected $moduleUtil;

    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil)
    {
        parent::__construct();
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * List products
     *
     * @queryParam order_by Values: product_name or newest
     * @queryParam order_direction Values: asc or desc
     * @queryParam brand_id comma separated ids of one or multiple brands
     * @queryParam category_id comma separated ids of one or multiple category
     * @queryParam sub_category_id comma separated ids of one or multiple sub-category
     * @queryParam location_id Example: 1
     * @queryParam selling_price_group (1, 0)
     * @queryParam send_lot_detail Send lot details in each variation location details(1, 0)
     * @queryParam name Search term for product name
     * @queryParam sku Search term for product sku
     * @queryParam per_page Total records per page. default: 10, Set -1 for no pagination Example:10
     * @response {
        "data": [
            {
                "id": 1,
                "name": "Men's Reverse Fleece Crew",
                "business_id": 1,
                "type": "single",
                "sub_unit_ids": null,
                "enable_stock": 1,
                "alert_quantity": "5.0000",
                "sku": "AS0001",
                "barcode_type": "C128",
                "expiry_period": null,
                "expiry_period_type": null,
                "enable_sr_no": 0,
                "weight": null,
                "product_custom_field1": null,
                "product_custom_field2": null,
                "product_custom_field3": null,
                "product_custom_field4": null,
                "image": null,
                "woocommerce_media_id": null,
                "product_description": null,
                "created_by": 1,
                "warranty_id": null,
                "is_inactive": 0,
                "repair_model_id": null,
                "not_for_selling": 0,
                "ecom_shipping_class_id": null,
                "ecom_active_in_store": 1,
                "woocommerce_product_id": 356,
                "woocommerce_disable_sync": 0,
                "image_url": "http://local.pos.com/img/default.png",
                "product_variations": [
                    {
                        "id": 1,
                        "variation_template_id": null,
                        "name": "DUMMY",
                        "product_id": 1,
                        "is_dummy": 1,
                        "created_at": "2018-01-03 21:29:08",
                        "updated_at": "2018-01-03 21:29:08",
                        "variations": [
                            {
                                "id": 1,
                                "name": "DUMMY",
                                "product_id": 1,
                                "sub_sku": "AS0001",
                                "product_variation_id": 1,
                                "woocommerce_variation_id": null,
                                "variation_value_id": null,
                                "default_purchase_price": "130.0000",
                                "dpp_inc_tax": "143.0000",
                                "profit_percent": "0.0000",
                                "default_sell_price": "130.0000",
                                "sell_price_inc_tax": "143.0000",
                                "created_at": "2018-01-03 21:29:08",
                                "updated_at": "2020-06-09 00:23:22",
                                "deleted_at": null,
                                "combo_variations": null,
                                "variation_location_details": [
                                    {
                                        "id": 56,
                                        "product_id": 1,
                                        "product_variation_id": 1,
                                        "variation_id": 1,
                                        "location_id": 1,
                                        "qty_available": "20.0000",
                                        "created_at": "2020-06-08 23:46:40",
                                        "updated_at": "2020-06-08 23:46:40"
                                    }
                                ],
                                "media": [
                                    {
                                        "id": 1,
                                        "business_id": 1,
                                        "file_name": "1591686466_978227300_nn.jpeg",
                                        "description": null,
                                        "uploaded_by": 9,
                                        "model_type": "App\\Variation",
                                        "woocommerce_media_id": null,
                                        "model_id": 1,
                                        "created_at": "2020-06-09 00:07:46",
                                        "updated_at": "2020-06-09 00:07:46",
                                        "display_name": "nn.jpeg",
                                        "display_url": "http://local.pos.com/uploads/media/1591686466_978227300_nn.jpeg"
                                    }
                                ],
                                "discounts": [
                                    {
                                        "id": 2,
                                        "name": "FLAT 10%",
                                        "business_id": 1,
                                        "brand_id": null,
                                        "category_id": null,
                                        "location_id": 1,
                                        "priority": 2,
                                        "discount_type": "fixed",
                                        "discount_amount": "5.0000",
                                        "starts_at": "2021-09-01 11:45:00",
                                        "ends_at": "2021-09-30 11:45:00",
                                        "is_active": 1,
                                        "spg": null,
                                        "applicable_in_cg": 1,
                                        "created_at": "2021-09-01 11:46:00",
                                        "updated_at": "2021-09-01 12:12:55",
                                        "formated_starts_at": " 11:45",
                                        "formated_ends_at": " 11:45"
                                    }
                                ],
                                "selling_price_group": [
                                    {
                                        "id": 2,
                                        "variation_id": 1,
                                        "price_group_id": 1,
                                        "price_inc_tax": "140.0000",
                                        "created_at": "2020-06-09 00:23:31",
                                        "updated_at": "2020-06-09 00:23:31"
                                    }
                                ]
                            }
                        ]
                    }
                ],
                "brand": {
                    "id": 1,
                    "business_id": 1,
                    "name": "Levis",
                    "description": null,
                    "created_by": 1,
                    "deleted_at": null,
                    "created_at": "2018-01-03 21:19:47",
                    "updated_at": "2018-01-03 21:19:47"
                },
                "unit": {
                    "id": 1,
                    "business_id": 1,
                    "actual_name": "Pieces",
                    "short_name": "Pc(s)",
                    "allow_decimal": 0,
                    "base_unit_id": null,
                    "base_unit_multiplier": null,
                    "created_by": 1,
                    "deleted_at": null,
                    "created_at": "2018-01-03 15:15:20",
                    "updated_at": "2018-01-03 15:15:20"
                },
                "category": {
                    "id": 1,
                    "name": "Men's",
                    "business_id": 1,
                    "short_code": null,
                    "parent_id": 0,
                    "created_by": 1,
                    "category_type": "product",
                    "description": null,
                    "slug": null,
                    "woocommerce_cat_id": null,
                    "deleted_at": null,
                    "created_at": "2018-01-03 21:06:34",
                    "updated_at": "2018-01-03 21:06:34"
                },
                "sub_category": {
                    "id": 5,
                    "name": "Shirts",
                    "business_id": 1,
                    "short_code": null,
                    "parent_id": 1,
                    "created_by": 1,
                    "category_type": "product",
                    "description": null,
                    "slug": null,
                    "woocommerce_cat_id": null,
                    "deleted_at": null,
                    "created_at": "2018-01-03 21:08:18",
                    "updated_at": "2018-01-03 21:08:18"
                },
                "product_tax": {
                    "id": 1,
                    "business_id": 1,
                    "name": "VAT@10%",
                    "amount": 10,
                    "is_tax_group": 0,
                    "created_by": 1,
                    "woocommerce_tax_rate_id": null,
                    "deleted_at": null,
                    "created_at": "2018-01-04 02:40:07",
                    "updated_at": "2018-01-04 02:40:07"
                },
                 "product_locations": [
                {
                    "id": 1,
                    "business_id": 1,
                    "location_id": null,
                    "name": "Awesome Shop",
                    "landmark": "Linking Street",
                    "country": "USA",
                    "state": "Arizona",
                    "city": "Phoenix",
                    "zip_code": "85001",
                    "invoice_scheme_id": 1,
                    "invoice_layout_id": 1,
                    "selling_price_group_id": null,
                    "print_receipt_on_invoice": 1,
                    "receipt_printer_type": "browser",
                    "printer_id": null,
                    "mobile": null,
                    "alternate_number": null,
                    "email": null,
                    "website": null,
                    "featured_products": [
                        "5",
                        "71"
                    ],
                    "is_active": 1,
                    "default_payment_accounts": "{\"cash\":{\"is_enabled\":\"1\",\"account\":\"1\"},\"card\":{\"is_enabled\":\"1\",\"account\":\"3\"},\"cheque\":{\"is_enabled\":\"1\",\"account\":\"2\"},\"bank_transfer\":{\"is_enabled\":\"1\",\"account\":\"1\"},\"other\":{\"is_enabled\":\"1\",\"account\":\"3\"},\"custom_pay_1\":{\"is_enabled\":\"1\",\"account\":\"1\"},\"custom_pay_2\":{\"is_enabled\":\"1\",\"account\":\"2\"},\"custom_pay_3\":{\"is_enabled\":\"1\",\"account\":\"3\"}}",
                    "custom_field1": null,
                    "custom_field2": null,
                    "custom_field3": null,
                    "custom_field4": null,
                    "deleted_at": null,
                    "created_at": "2018-01-04 02:15:20",
                    "updated_at": "2020-06-09 01:07:05",
                    "pivot": {
                        "product_id": 2,
                        "location_id": 1
                    }
                }]
            }
        ],
        "links": {
            "first": "http://local.pos.com/connector/api/product?page=1",
            "last": "http://local.pos.com/connector/api/product?page=32",
            "prev": null,
            "next": "http://local.pos.com/connector/api/product?page=2"
        },
        "meta": {
            "current_page": 1,
            "from": 1,
            "path": "http://local.pos.com/connector/api/product",
            "per_page": 10,
            "to": 10
        }
    }
     */
    public function index()
    {
        $user = Auth::user();

        $business_id = $user->business_id;

        $filters = request()->only(['brand_id', 'category_id', 'location_id', 'sub_category_id', 'per_page']);
        $filters['selling_price_group'] = request()->input('selling_price_group') == 1 ? true : false;

        $search = request()->only(['sku', 'name']);

        //order
        $order_by = null;
        $order_direction = null;

        if (! empty(request()->input('order_by'))) {
            $order_by = in_array(request()->input('order_by'), ['product_name', 'newest']) ? request()->input('order_by') : null;
            $order_direction = in_array(request()->input('order_direction'), ['asc', 'desc']) ? request()->input('order_direction') : 'asc';
        }

        $products = $this->__getProducts($business_id, $filters, $search, true, $order_by, $order_direction);

        return ProductResource::collection($products);
    }

    /**
     * Create a product. See docs/api/product-create.md for complete request
     * examples, field rules, file uploads, and response details.
     *
     * @bodyParam name string required Product name. Example: Coffee
     * @bodyParam type string required single, variable, or combo. Example: single
     * @bodyParam unit_id integer required Base unit ID belonging to the authenticated business. Example: 1
     * @bodyParam barcode_type string required C39, C128, EAN-13, EAN-8, UPC-A, UPC-E, or ITF-14. Example: C128
     * @bodyParam tax_type string required inclusive or exclusive. Example: exclusive
     * @bodyParam sku string Optional SKU; generated from the business prefix and product ID if blank. Example: COFFEE-001
     * @bodyParam brand_id integer Optional brand ID belonging to the authenticated business. Example: 2
     * @bodyParam category_id integer Optional product category ID belonging to the authenticated business. Example: 4
     * @bodyParam sub_category_id integer Optional sub-category under category_id. Example: 5
     * @bodyParam tax integer Optional tax rate ID belonging to the authenticated business. Example: 1
     * @bodyParam enable_stock boolean Optional; defaults to true. Example: true
     * @bodyParam alert_quantity number Optional minimum stock alert quantity. Example: 5
     * @bodyParam not_for_selling boolean Optional; defaults to false. Example: false
     * @bodyParam enable_sr_no boolean Optional; defaults to false. Example: false
     * @bodyParam weight number Optional non-negative product weight. Example: 1.25
     * @bodyParam product_description string Optional product description.
     * @bodyParam sub_unit_ids array Optional unit IDs belonging to the authenticated business.
     * @bodyParam secondary_unit_id integer Optional base unit ID belonging to the authenticated business. Example: 2
     * @bodyParam warranty_id integer Optional warranty ID belonging to the authenticated business. Example: 1
     * @bodyParam expiry_period number Optional; saved only when product expiry is enabled for the business. Example: 12
     * @bodyParam expiry_period_type string Optional months or days. Example: months
     * @bodyParam preparation_time_in_minutes integer Optional non-negative preparation time. Example: 5
     * @bodyParam product_locations array Optional active location IDs accessible to the authenticated user.
     * @bodyParam product_racks object Optional location-ID keyed rack, row, and position details.
     * @bodyParam product_custom_field1 string Optional product custom field; fields 1 through 20 are supported.
     * @bodyParam single_dpp number required for single products; minimum 0. Purchase price excluding tax. Example: 10
     * @bodyParam single_dpp_inc_tax number required for single products; minimum 0. Purchase price including tax. Example: 11
     * @bodyParam profit_percent number required for single and combo products. Example: 50
     * @bodyParam single_dsp number required for single products; minimum 0. Selling price excluding tax. Example: 15
     * @bodyParam single_dsp_inc_tax number required for single products; minimum 0. Selling price including tax. Example: 16.5
     * @bodyParam sku_type string Optional variable-product SKU format: with_out_variation or with_variation. Defaults to with_out_variation.
     * @bodyParam product_variation array required for variable products. Each group has a name or variation_template_id and a variations array with value, optional sub_sku, prices, and profit_percent.
     * @bodyParam composition_variation_id array required for combo products. Component variation IDs belonging to the authenticated business.
     * @bodyParam quantity array required for combo products. Positive component quantities with matching indexes.
     * @bodyParam unit array required for combo products. Component unit IDs with matching indexes.
     * @bodyParam item_level_purchase_price_total number required for combo products; minimum 0. Example: 8
     * @bodyParam purchase_price_inc_tax number required for combo products; minimum 0. Example: 8.8
     * @bodyParam selling_price number required for combo products; minimum 0. Example: 12
     * @bodyParam selling_price_inc_tax number required for combo products; minimum 0. Example: 13.2
     * @bodyParam image file Optional product image; required only when configured as required by the business.
     * @bodyParam product_brochure file Optional product brochure/document.
     * @bodyParam has_module_data boolean Optional; set true to invoke installed modules' product-save hooks.
     * @response 201 {"data":{"id":123,"name":"Coffee","business_id":10,"type":"single","sku":"POS0123","image_url":"https://example.test/img/default.png","product_variations":[{"id":456,"name":"DUMMY","variations":[{"id":789,"name":"DUMMY","sub_sku":"POS0123","default_purchase_price":"10.0000","default_sell_price":"15.0000","variation_location_details":[],"media":[],"discounts":[]}]}],"unit":{"id":1,"actual_name":"Pieces","short_name":"Pc(s)"},"product_locations":[{"id":1,"name":"Main Store"}]}}
     * @response 403 {"error":{"message":"You are not authorized to create products."}}
     * @response 422 {"message":"The given data was invalid.","errors":{"name":["The name field is required."]}}
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $businessId = $user->business_id;

        if (! $user->can('product.create')) {
            return response()->json(['error' => ['message' => 'You are not authorized to create products.']], 403);
        }

        if (! $this->moduleUtil->isSubscribed($businessId)) {
            return response()->json(['error' => ['message' => 'The business subscription is inactive.']], 403);
        }

        if (! $this->moduleUtil->isQuotaAvailable('products', $businessId)) {
            return response()->json(['error' => ['message' => 'The product limit for this business has been reached.']], 403);
        }

        $business = Business::findOrFail($businessId);
        $commonSettings = $business->common_settings ?? [];
        $imageRule = ! empty($commonSettings['is_product_image_required']) ? 'required' : 'nullable';
        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $imageMaxKilobytes = (int) floor(config('constants.image_size_limit') / 1024);
        $documentMimeExtensions = config('constants.document_upload_mimes_types', []);
        $documentMimes = array_keys($documentMimeExtensions);
        $documentExtensions = array_map(function ($extension) {
            return ltrim($extension, '.');
        }, array_values($documentMimeExtensions));
        $documentMaxKilobytes = (int) floor(config('constants.document_size_limit') / 1024);
        $imageFileRules = [
            'file',
            'mimetypes:'.implode(',', $imageMimes),
            'mimes:'.implode(',', $imageExtensions),
            'max:'.$imageMaxKilobytes,
        ];
        $documentFileRules = [
            'file',
            'mimetypes:'.implode(',', $documentMimes),
            'mimes:'.implode(',', $documentExtensions),
            'max:'.$documentMaxKilobytes,
        ];

        $validationRules = [
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in(['single', 'variable', 'combo'])],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('base_unit_id')->whereNull('deleted_at');
            })],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('category_type', 'product')->where('parent_id', 0)->whereNull('deleted_at');
            })],
            'sub_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('category_type', 'product')->whereNull('deleted_at');
            })],
            'tax' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'tax_type' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'sku' => ['nullable', 'string', 'max:191'],
            'barcode_type' => ['required', Rule::in(['C39', 'C128', 'EAN-13', 'EAN-8', 'UPC-A', 'UPC-E', 'ITF-14'])],
            'enable_stock' => ['sometimes', 'boolean'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0'],
            'not_for_selling' => ['sometimes', 'boolean'],
            'enable_sr_no' => ['sometimes', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'product_description' => ['nullable', 'string'],
            'sub_unit_ids' => ['nullable', 'array'],
            'sub_unit_ids.*' => ['integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'secondary_unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at')->whereNull('base_unit_id');
            })],
            'warranty_id' => ['nullable', 'integer', Rule::exists('warranties', 'id')->where('business_id', $businessId)],
            'expiry_period' => ['nullable', 'numeric', 'min:0'],
            'expiry_period_type' => ['nullable', Rule::in(['months', 'days'])],
            'preparation_time_in_minutes' => ['nullable', 'integer', 'min:0'],
            'image' => array_merge([$imageRule, 'image'], $imageFileRules),
            'product_brochure' => array_merge(['nullable'], $documentFileRules),
            'product_locations' => ['sometimes', 'array'],
            'product_locations.*' => ['integer', Rule::exists('business_locations', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('is_active', 1);
            })],
            'product_racks' => ['sometimes', 'array'],
            'product_racks.*' => ['array:rack,row,position'],
            'product_racks.*.rack' => ['nullable', 'string', 'max:191'],
            'product_racks.*.row' => ['nullable', 'string', 'max:191'],
            'product_racks.*.position' => ['nullable', 'string', 'max:191'],
            'single_dpp' => ['required_if:type,single', 'numeric', 'min:0'],
            'single_dpp_inc_tax' => ['required_if:type,single', 'numeric', 'min:0'],
            'profit_percent' => ['required_if:type,single,combo', 'numeric'],
            'single_dsp' => ['required_if:type,single', 'numeric', 'min:0'],
            'single_dsp_inc_tax' => ['required_if:type,single', 'numeric', 'min:0'],
            'sku_type' => ['nullable', Rule::in(['with_out_variation', 'with_variation'])],
            'product_variation' => ['required_if:type,variable', 'array', 'min:1'],
            'product_variation.*' => ['array:name,variation_template_id,variations'],
            'product_variation.*.name' => ['nullable', 'string', 'max:191'],
            'product_variation.*.variation_template_id' => ['nullable', 'integer', Rule::exists('variation_templates', 'id')->where('business_id', $businessId)],
            'product_variation.*.variations' => ['required', 'array', 'min:1'],
            'product_variation.*.variations.*' => ['array:value,sub_sku,default_purchase_price,dpp_inc_tax,profit_percent,default_sell_price,sell_price_inc_tax'],
            'product_variation.*.variations.*.value' => ['required', 'string', 'max:191'],
            'product_variation.*.variations.*.sub_sku' => ['nullable', 'string', 'max:191'],
            'product_variation.*.variations.*.default_purchase_price' => ['required', 'numeric', 'min:0'],
            'product_variation.*.variations.*.dpp_inc_tax' => ['required', 'numeric', 'min:0'],
            'product_variation.*.variations.*.profit_percent' => ['required', 'numeric'],
            'product_variation.*.variations.*.default_sell_price' => ['required', 'numeric', 'min:0'],
            'product_variation.*.variations.*.sell_price_inc_tax' => ['required', 'numeric', 'min:0'],
            'composition_variation_id' => ['required_if:type,combo', 'array', 'min:1'],
            'composition_variation_id.*' => ['integer'],
            'quantity' => ['required_if:type,combo', 'array', 'min:1'],
            'quantity.*' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required_if:type,combo', 'array', 'min:1'],
            'unit.*' => ['required', 'integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'has_module_data' => ['sometimes', 'boolean'],
            'item_level_purchase_price_total' => ['required_if:type,combo', 'numeric', 'min:0'],
            'purchase_price_inc_tax' => ['required_if:type,combo', 'numeric', 'min:0'],
            'selling_price' => ['required_if:type,combo', 'numeric', 'min:0'],
            'selling_price_inc_tax' => ['required_if:type,combo', 'numeric', 'min:0'],
        ] + collect(range(1, 20))->mapWithKeys(function ($number) {
            return ['product_custom_field'.$number => ['nullable', 'string', 'max:191']];
        })->all();

        foreach (array_keys($request->allFiles()) as $inputName) {
            if ($inputName === 'variation_images' || str_starts_with($inputName, 'variation_images_')) {
                $validationRules[$inputName] = ['sometimes', 'array'];
                $validationRules[$inputName.'.*'] = $imageFileRules;
            }
        }

        $data = $request->validate($validationRules);

        if (! empty($data['sub_category_id']) && empty($data['category_id'])) {
            throw ValidationException::withMessages([
                'sub_category_id' => 'A category is required when a sub-category is provided.',
            ]);
        }

        if (! empty($data['sub_category_id']) && ! Category::where('business_id', $businessId)
                ->where('id', $data['sub_category_id'])
                ->where('parent_id', $data['category_id'])
                ->exists()) {
            throw ValidationException::withMessages([
                'sub_category_id' => 'The sub-category must belong to the selected category.',
            ]);
        }

        if ($data['type'] === 'variable') {
            foreach ($data['product_variation'] as $index => $productVariation) {
                if (empty($productVariation['name']) && empty($productVariation['variation_template_id'])) {
                    throw ValidationException::withMessages([
                        "product_variation.$index.name" => 'Provide a variation name or a variation template ID.',
                    ]);
                }
            }
        }

        if ($data['type'] === 'combo') {
            $variationIds = array_values(array_unique(array_map('intval', $data['composition_variation_id'])));
            $businessVariationIds = Variation::whereIn('id', $variationIds)
                ->whereHas('product', function ($query) use ($businessId) {
                    $query->where('business_id', $businessId);
                })
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            if (count($businessVariationIds) !== count($variationIds)) {
                throw ValidationException::withMessages([
                    'composition_variation_id' => 'Every component variation must belong to the authenticated business.',
                ]);
            }

            if (array_keys($data['composition_variation_id']) !== array_keys($data['quantity'])
                || array_keys($data['composition_variation_id']) !== array_keys($data['unit'])) {
                throw ValidationException::withMessages([
                    'composition_variation_id' => 'Component variation, quantity, and unit arrays must use matching indexes.',
                ]);
            }
        }

        $permittedLocations = $user->permitted_locations($businessId);
        $accessibleLocations = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->when($permittedLocations !== 'all', function ($query) use ($permittedLocations) {
                $query->whereIn('id', $permittedLocations);
            })
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $locationIds = array_map('intval', $data['product_locations'] ?? []);
        if (! empty(array_diff($locationIds, $accessibleLocations))) {
            return response()->json(['error' => ['message' => 'You are not authorized to access one or more business locations.']], 403);
        }
        if (! array_key_exists('product_locations', $data) && count($accessibleLocations) === 1) {
            $locationIds = $accessibleLocations;
        }

        $rackDetails = $data['product_racks'] ?? [];
        if (! empty($rackDetails)) {
            foreach (array_keys($rackDetails) as $rackLocationId) {
                if (! ctype_digit((string) $rackLocationId)) {
                    throw ValidationException::withMessages([
                        'product_racks' => 'Rack detail keys must be business location IDs.',
                    ]);
                }
            }

            $rackLocationIds = array_map('intval', array_keys($rackDetails));
            if (! empty(array_diff($rackLocationIds, $accessibleLocations))) {
                return response()->json(['error' => ['message' => 'Rack details can only be set for accessible business locations.']], 403);
            }
        }

        $moduleFormFields = $this->moduleUtil->getModuleFormField('product_form_fields');

        $product = DB::transaction(function () use ($request, $data, $business, $businessId, $user, $locationIds, $rackDetails, $moduleFormFields) {
            $productFields = [
                'name', 'brand_id', 'unit_id', 'category_id', 'sub_category_id', 'tax', 'type',
                'barcode_type', 'sku', 'alert_quantity', 'tax_type', 'weight', 'product_description',
                'sub_unit_ids', 'secondary_unit_id', 'preparation_time_in_minutes',
                'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4',
                'product_custom_field5', 'product_custom_field6', 'product_custom_field7', 'product_custom_field8',
                'product_custom_field9', 'product_custom_field10', 'product_custom_field11', 'product_custom_field12',
                'product_custom_field13', 'product_custom_field14', 'product_custom_field15', 'product_custom_field16',
                'product_custom_field17', 'product_custom_field18', 'product_custom_field19', 'product_custom_field20',
            ];
            $productDetails = $request->only(array_merge($productFields, $moduleFormFields));
            $productDetails['business_id'] = $businessId;
            $productDetails['created_by'] = $user->id;
            $productDetails['enable_stock'] = $data['enable_stock'] ?? true;
            $productDetails['not_for_selling'] = $data['not_for_selling'] ?? false;
            $productDetails['enable_sr_no'] = $data['enable_sr_no'] ?? false;
            $productDetails['alert_quantity'] = $data['alert_quantity'] ?? 0;
            $productDetails['sku'] = trim($data['sku'] ?? '') === '' ? ' ' : $data['sku'];
            $productDetails['warranty_id'] = $data['warranty_id'] ?? null;
            $productDetails['image'] = $this->productUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');

            if (! empty($business->enable_product_expiry) && ! empty($data['expiry_period']) && ! empty($data['expiry_period_type'])) {
                $productDetails['expiry_period'] = $data['expiry_period'];
                $productDetails['expiry_period_type'] = $data['expiry_period_type'];
            }

            $product = Product::create($productDetails);
            event(new ProductsCreatedOrModified($productDetails, 'added'));

            if (trim($product->sku) === '') {
                $product->sku = ($business->sku_prefix ?? '').str_pad($product->id, 4, '0', STR_PAD_LEFT);
                $product->save();
            }

            $product->product_locations()->sync($locationIds);

            if ($product->type === 'single') {
                $this->productUtil->createSingleProductVariation(
                    $product->id,
                    $product->sku,
                    $data['single_dpp'],
                    $data['single_dpp_inc_tax'],
                    $data['profit_percent'],
                    $data['single_dsp'],
                    $data['single_dsp_inc_tax']
                );
            } elseif ($product->type === 'variable') {
                $this->productUtil->createVariableProductVariations(
                    $product->id,
                    $data['product_variation'],
                    $data['sku_type'] ?? 'with_out_variation',
                    $businessId
                );
            } else {
                $comboVariations = [];
                foreach ($data['composition_variation_id'] as $index => $variationId) {
                    $comboVariations[] = [
                        'variation_id' => $variationId,
                        'quantity' => $data['quantity'][$index],
                        'unit_id' => $data['unit'][$index],
                    ];
                }

                $this->productUtil->createSingleProductVariation(
                    $product->id,
                    $product->sku,
                    $data['item_level_purchase_price_total'],
                    $data['purchase_price_inc_tax'],
                    $data['profit_percent'],
                    $data['selling_price'],
                    $data['selling_price_inc_tax'],
                    $comboVariations
                );
            }

            if (! empty($rackDetails)) {
                $this->productUtil->addRackDetails($businessId, $product->id, $rackDetails);
            }

            if (! empty($data['has_module_data'])) {
                $this->moduleUtil->getModuleData('after_product_saved', ['product' => $product, 'request' => $request]);
            }

            Media::uploadMedia($product->business_id, $product, $request, 'product_brochure', true);

            return $product->load([
                'product_variations.variations.variation_location_details',
                'product_variations.variations.media',
                'brand',
                'unit',
                'category',
                'sub_category',
                'product_tax',
                'product_locations',
            ]);
        });

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /**
     * Update a product. Use GET /connector/api/product/{id} to load the
     * product before editing. Product type cannot be changed through this API.
     *
     * @urlParam product required Product ID. Example: 123
     * @bodyParam name string Optional updated product name. Example: Coffee - New Label
     * @bodyParam unit_id integer Optional base unit ID belonging to the authenticated business. Example: 1
     * @bodyParam brand_id integer Optional brand ID belonging to the authenticated business. Example: 2
     * @bodyParam category_id integer Optional product category ID belonging to the authenticated business. Example: 4
     * @bodyParam sub_category_id integer Optional sub-category under the selected category. Example: 5
     * @bodyParam tax integer Optional tax rate ID belonging to the authenticated business. Example: 1
     * @bodyParam tax_type string Optional inclusive or exclusive. Example: exclusive
     * @bodyParam sku string Optional updated SKU. Blank values leave the existing SKU unchanged.
     * @bodyParam product_locations array Optional replacement array of active accessible location IDs.
     * @bodyParam single_dpp number Optional single-product purchase price excluding tax. Example: 10
     * @bodyParam single_dpp_inc_tax number Optional single-product purchase price including tax. Example: 11
     * @bodyParam single_dsp number Optional single-product selling price excluding tax. Example: 15
     * @bodyParam single_dsp_inc_tax number Optional single-product selling price including tax. Example: 16.5
     * @bodyParam product_variation_edit array Optional variable-product existing groups and values. Include every existing group and value; use IDs returned by GET.
     * @bodyParam composition_variation_id array Optional replacement combo component variation IDs.
     * @bodyParam quantity array Required with composition_variation_id; positive component quantities with matching indexes.
     * @bodyParam unit array Required with composition_variation_id; component unit IDs with matching indexes.
     * @bodyParam image file Optional replacement product image.
     * @bodyParam product_brochure file Optional additional product brochure/document.
     * @response 200 {"data":{"id":123,"name":"Coffee","type":"single","image_url":"https://example.test/uploads/img/coffee.jpg"}}
     * @response 403 {"error":{"message":"You are not authorized to update products."}}
     * @response 422 {"message":"The given data was invalid.","errors":{"image":["The image must be an image."]}}
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $businessId = $user->business_id;

        if (! $user->can('product.update')) {
            return response()->json(['error' => ['message' => 'You are not authorized to update products.']], 403);
        }

        $product = Product::where('business_id', $businessId)->findOrFail($id);
        $business = Business::findOrFail($businessId);

        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $imageMaxKilobytes = (int) floor(config('constants.image_size_limit') / 1024);
        $documentMimeExtensions = config('constants.document_upload_mimes_types', []);
        $documentMimes = array_keys($documentMimeExtensions);
        $documentExtensions = array_map(function ($extension) {
            return ltrim($extension, '.');
        }, array_values($documentMimeExtensions));
        $documentMaxKilobytes = (int) floor(config('constants.document_size_limit') / 1024);
        $imageFileRules = [
            'file',
            'mimetypes:'.implode(',', $imageMimes),
            'mimes:'.implode(',', $imageExtensions),
            'max:'.$imageMaxKilobytes,
        ];
        $documentFileRules = [
            'file',
            'mimetypes:'.implode(',', $documentMimes),
            'mimes:'.implode(',', $documentExtensions),
            'max:'.$documentMaxKilobytes,
        ];

        $validationRules = [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'type' => ['prohibited'],
            'unit_id' => ['sometimes', 'required', 'integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('base_unit_id')->whereNull('deleted_at');
            })],
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists('brands', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('category_type', 'product')->where('parent_id', 0)->whereNull('deleted_at');
            })],
            'sub_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('category_type', 'product')->whereNull('deleted_at');
            })],
            'tax' => ['sometimes', 'nullable', 'integer', Rule::exists('tax_rates', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'tax_type' => ['sometimes', 'required', Rule::in(['inclusive', 'exclusive'])],
            'sku' => ['sometimes', 'nullable', 'string', 'max:191'],
            'barcode_type' => ['sometimes', 'required', Rule::in(['C39', 'C128', 'EAN-13', 'EAN-8', 'UPC-A', 'UPC-E', 'ITF-14'])],
            'enable_stock' => ['sometimes', 'boolean'],
            'alert_quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'not_for_selling' => ['sometimes', 'boolean'],
            'enable_sr_no' => ['sometimes', 'boolean'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'product_description' => ['sometimes', 'nullable', 'string'],
            'sub_unit_ids' => ['sometimes', 'nullable', 'array'],
            'sub_unit_ids.*' => ['integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'secondary_unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('base_unit_id')->whereNull('deleted_at');
            })],
            'warranty_id' => ['sometimes', 'nullable', 'integer', Rule::exists('warranties', 'id')->where('business_id', $businessId)],
            'expiry_period' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'expiry_period_type' => ['sometimes', 'nullable', Rule::in(['months', 'days'])],
            'preparation_time_in_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'image' => array_merge(['sometimes', 'nullable'], $imageFileRules),
            'product_brochure' => array_merge(['sometimes', 'nullable'], $documentFileRules),
            'product_locations' => ['sometimes', 'array'],
            'product_locations.*' => ['integer', Rule::exists('business_locations', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('is_active', 1);
            })],
            'has_module_data' => ['sometimes', 'boolean'],
            'sku_type' => ['sometimes', Rule::in(['with_out_variation', 'with_variation'])],
            'single_dpp' => ['sometimes', 'numeric', 'min:0'],
            'single_dpp_inc_tax' => ['sometimes', 'numeric', 'min:0'],
            'single_dsp' => ['sometimes', 'numeric', 'min:0'],
            'single_dsp_inc_tax' => ['sometimes', 'numeric', 'min:0'],
            'profit_percent' => ['sometimes', 'numeric'],
            'item_level_purchase_price_total' => ['sometimes', 'numeric', 'min:0'],
            'purchase_price_inc_tax' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'selling_price_inc_tax' => ['sometimes', 'numeric', 'min:0'],
            'combo_variation_id' => ['sometimes', 'integer'],
            'composition_variation_id' => ['sometimes', 'array', 'min:1'],
            'composition_variation_id.*' => ['integer'],
            'quantity' => ['required_with:composition_variation_id', 'array', 'min:1'],
            'quantity.*' => ['required_with:composition_variation_id', 'numeric', 'gt:0'],
            'unit' => ['required_with:composition_variation_id', 'array', 'min:1'],
            'unit.*' => ['required_with:composition_variation_id', 'integer', Rule::exists('units', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereNull('deleted_at');
            })],
            'product_variation_edit' => ['sometimes', 'array', 'min:1'],
            'product_variation_edit.*' => ['array:name,variations_edit,variations'],
            'product_variation_edit.*.name' => ['required_with:product_variation_edit', 'string', 'max:191'],
            'product_variation_edit.*.variations_edit' => ['sometimes', 'array'],
            'product_variation_edit.*.variations_edit.*' => ['array:value,sub_sku,default_purchase_price,dpp_inc_tax,profit_percent,default_sell_price,sell_price_inc_tax'],
            'product_variation_edit.*.variations_edit.*.value' => ['required', 'string', 'max:191'],
            'product_variation_edit.*.variations_edit.*.sub_sku' => ['nullable', 'string', 'max:191'],
            'product_variation_edit.*.variations_edit.*.default_purchase_price' => ['required', 'numeric', 'min:0'],
            'product_variation_edit.*.variations_edit.*.dpp_inc_tax' => ['required', 'numeric', 'min:0'],
            'product_variation_edit.*.variations_edit.*.profit_percent' => ['required', 'numeric'],
            'product_variation_edit.*.variations_edit.*.default_sell_price' => ['required', 'numeric', 'min:0'],
            'product_variation_edit.*.variations_edit.*.sell_price_inc_tax' => ['required', 'numeric', 'min:0'],
            'product_variation_edit.*.variations' => ['sometimes', 'array', 'min:1'],
            'product_variation_edit.*.variations.*' => ['array:value,sub_sku,default_purchase_price,dpp_inc_tax,profit_percent,default_sell_price,sell_price_inc_tax'],
            'product_variation_edit.*.variations.*.value' => ['required', 'string', 'max:191'],
            'product_variation_edit.*.variations.*.sub_sku' => ['nullable', 'string', 'max:191'],
            'product_variation_edit.*.variations.*.default_purchase_price' => ['required', 'numeric', 'min:0'],
            'product_variation_edit.*.variations.*.dpp_inc_tax' => ['required', 'numeric', 'min:0'],
            'product_variation_edit.*.variations.*.profit_percent' => ['required', 'numeric'],
            'product_variation_edit.*.variations.*.default_sell_price' => ['required', 'numeric', 'min:0'],
            'product_variation_edit.*.variations.*.sell_price_inc_tax' => ['required', 'numeric', 'min:0'],
        ];

        foreach (array_keys($request->allFiles()) as $inputName) {
            if (str_starts_with($inputName, 'variation_images_') || str_starts_with($inputName, 'edit_variation_images_')) {
                $validationRules[$inputName] = ['sometimes', 'array'];
                $validationRules[$inputName.'.*'] = $imageFileRules;
            }
        }

        $validationRules += collect(range(1, 20))->mapWithKeys(function ($number) {
            return ['product_custom_field'.$number => ['sometimes', 'nullable', 'string', 'max:191']];
        })->all();

        $data = $request->validate($validationRules);

        if (array_key_exists('sub_category_id', $data) && ! empty($data['sub_category_id'])) {
            $categoryId = $data['category_id'] ?? $product->category_id;
            if (empty($categoryId) || ! Category::where('business_id', $businessId)
                    ->where('id', $data['sub_category_id'])
                    ->where('parent_id', $categoryId)
                    ->exists()) {
                throw ValidationException::withMessages([
                    'sub_category_id' => 'The sub-category must belong to the selected category.',
                ]);
            }
        }

        if ($product->type === 'single' && array_intersect(
            ['sku', 'single_dpp', 'single_dpp_inc_tax', 'single_dsp', 'single_dsp_inc_tax', 'profit_percent'],
            array_keys($data)
        )) {
            $singleVariation = $product->variations()->firstOrFail();
        } else {
            $singleVariation = null;
        }

        if ($product->type === 'combo' && array_key_exists('composition_variation_id', $data)) {
            if (array_keys($data['composition_variation_id']) !== array_keys($data['quantity'])
                || array_keys($data['composition_variation_id']) !== array_keys($data['unit'])) {
                throw ValidationException::withMessages([
                    'composition_variation_id' => 'Component variation, quantity, and unit arrays must use matching indexes.',
                ]);
            }

            $componentVariationIds = array_values(array_unique(array_map('intval', $data['composition_variation_id'])));
            $businessVariationIds = Variation::whereIn('id', $componentVariationIds)
                ->whereHas('product', function ($query) use ($businessId) {
                    $query->where('business_id', $businessId);
                })
                ->pluck('id')
                ->map(function ($variationId) {
                    return (int) $variationId;
                })
                ->all();

            if (count($componentVariationIds) !== count($businessVariationIds)) {
                throw ValidationException::withMessages([
                    'composition_variation_id' => 'Every component variation must belong to the authenticated business.',
                ]);
            }
        }

        if ($product->type === 'combo' && (
            array_key_exists('combo_variation_id', $data)
            || array_intersect(['sku', 'item_level_purchase_price_total', 'purchase_price_inc_tax', 'selling_price', 'selling_price_inc_tax', 'profit_percent'], array_keys($data))
        )) {
            $comboVariation = $product->variations()->firstOrFail();
            if (isset($data['combo_variation_id']) && (int) $data['combo_variation_id'] !== (int) $comboVariation->id) {
                throw ValidationException::withMessages([
                    'combo_variation_id' => 'The combo variation ID does not belong to this product.',
                ]);
            }
        } else {
            $comboVariation = null;
        }

        if ($product->type === 'variable' && array_key_exists('product_variation_edit', $data)) {
            $existingProductVariationIds = $product->product_variations()->pluck('id')
                ->map(function ($variationId) {
                    return (int) $variationId;
                })->sort()->values()->all();
            $requestedProductVariationIds = array_map('intval', array_keys($data['product_variation_edit']));
            sort($requestedProductVariationIds);

            if ($existingProductVariationIds !== $requestedProductVariationIds) {
                throw ValidationException::withMessages([
                    'product_variation_edit' => 'Include every existing product variation group when updating variable product values.',
                ]);
            }

            foreach ($data['product_variation_edit'] as $productVariationId => $group) {
                $existingVariationIds = Variation::where('product_id', $product->id)
                    ->where('product_variation_id', $productVariationId)
                    ->pluck('id')
                    ->map(function ($variationId) {
                        return (int) $variationId;
                    })->sort()->values()->all();
                $requestedVariationIds = array_map('intval', array_keys($group['variations_edit'] ?? []));
                sort($requestedVariationIds);

                if ($existingVariationIds !== $requestedVariationIds) {
                    throw ValidationException::withMessages([
                        'product_variation_edit' => 'Include every existing variation value under its existing group when updating variable product values.',
                    ]);
                }
            }
        }

        $permittedLocations = $user->permitted_locations($businessId);
        $accessibleLocationIds = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->when($permittedLocations !== 'all', function ($query) use ($permittedLocations) {
                $query->whereIn('id', $permittedLocations);
            })
            ->pluck('id')
            ->map(function ($locationId) {
                return (int) $locationId;
            })->all();
        if (isset($data['product_locations'])) {
            $requestedLocationIds = array_map('intval', $data['product_locations']);
            if (! empty(array_diff($requestedLocationIds, $accessibleLocationIds))) {
                return response()->json(['error' => ['message' => 'You are not authorized to access one or more business locations.']], 403);
            }
            if ($permittedLocations !== 'all') {
                $existingInaccessibleLocations = $product->product_locations()->pluck('id')
                    ->map(function ($locationId) {
                        return (int) $locationId;
                    })->diff($accessibleLocationIds)->all();
                $requestedLocationIds = array_values(array_unique(array_merge($requestedLocationIds, $existingInaccessibleLocations)));
            }
        }
        $moduleFormFields = $this->moduleUtil->getModuleFormField('product_form_fields');
        $oldImagePath = $product->image_path;
        $oldImageName = $product->image;

        $product = DB::transaction(function () use ($request, $data, $product, $business, $businessId, $singleVariation, $comboVariation, $moduleFormFields, $requestedLocationIds) {
            $productFields = [
                'name', 'brand_id', 'unit_id', 'category_id', 'sub_category_id', 'tax', 'barcode_type',
                'sku', 'alert_quantity', 'tax_type', 'weight', 'product_description', 'sub_unit_ids',
                'secondary_unit_id', 'preparation_time_in_minutes',
            ];
            foreach (array_merge($productFields, $moduleFormFields, array_map(function ($number) {
                return 'product_custom_field'.$number;
            }, range(1, 20))) as $field) {
                if (array_key_exists($field, $data)) {
                    if ($field === 'sku' && trim((string) $data[$field]) === '') {
                        continue;
                    }
                    $product->{$field} = $data[$field];
                }
            }

            foreach (['enable_stock', 'not_for_selling', 'enable_sr_no'] as $booleanField) {
                if (array_key_exists($booleanField, $data)) {
                    $product->{$booleanField} = $data[$booleanField];
                }
            }

            if (array_key_exists('warranty_id', $data)) {
                $product->warranty_id = $data['warranty_id'];
            }

            if (! empty($business->enable_product_expiry)) {
                if (array_key_exists('expiry_period', $data) || array_key_exists('expiry_period_type', $data)) {
                    $product->expiry_period = $data['expiry_period'] ?? null;
                    $product->expiry_period_type = $data['expiry_period_type'] ?? null;
                }
            }

            if ($request->hasFile('image')) {
                $product->image = $this->productUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');
                if (! empty($product->woocommerce_media_id)) {
                    $product->woocommerce_media_id = null;
                }
            }

            $product->save();
            event(new ProductsCreatedOrModified($product, 'updated'));

            if (isset($requestedLocationIds)) {
                $product->product_locations()->sync($requestedLocationIds);
            }

            if ($singleVariation) {
                $priceFields = [
                    'single_dpp' => 'default_purchase_price',
                    'single_dpp_inc_tax' => 'dpp_inc_tax',
                    'single_dsp' => 'default_sell_price',
                    'single_dsp_inc_tax' => 'sell_price_inc_tax',
                    'profit_percent' => 'profit_percent',
                ];
                foreach ($priceFields as $requestField => $variationField) {
                    if (array_key_exists($requestField, $data)) {
                        $singleVariation->{$variationField} = $data[$requestField];
                    }
                }
                if (array_key_exists('sku', $data) && trim((string) $data['sku']) !== '') {
                    $singleVariation->sub_sku = $data['sku'];
                }
                $singleVariation->save();
                Media::uploadMedia($product->business_id, $singleVariation, $request, 'variation_images');
            }

            if ($comboVariation) {
                $priceFields = [
                    'item_level_purchase_price_total' => 'default_purchase_price',
                    'purchase_price_inc_tax' => 'dpp_inc_tax',
                    'selling_price' => 'default_sell_price',
                    'selling_price_inc_tax' => 'sell_price_inc_tax',
                    'profit_percent' => 'profit_percent',
                ];
                foreach ($priceFields as $requestField => $variationField) {
                    if (array_key_exists($requestField, $data)) {
                        $comboVariation->{$variationField} = $data[$requestField];
                    }
                }
                if (array_key_exists('sku', $data) && trim((string) $data['sku']) !== '') {
                    $comboVariation->sub_sku = $data['sku'];
                }
                if (array_key_exists('composition_variation_id', $data)) {
                    $comboVariations = [];
                    foreach ($data['composition_variation_id'] as $index => $variationId) {
                        $comboVariations[] = [
                            'variation_id' => $variationId,
                            'quantity' => $data['quantity'][$index],
                            'unit_id' => $data['unit'][$index],
                        ];
                    }
                    $comboVariation->combo_variations = $comboVariations;
                }
                $comboVariation->save();
            }

            if ($product->type === 'variable' && isset($data['product_variation_edit'])) {
                $this->productUtil->updateVariableProductVariations(
                    $product->id,
                    $data['product_variation_edit'],
                    $data['sku_type'] ?? 'with_out_variation'
                );
            }

            if (! empty($data['has_module_data'])) {
                $this->moduleUtil->getModuleData('after_product_saved', ['product' => $product, 'request' => $request]);
            }

            if ($request->hasFile('product_brochure')) {
                Media::uploadMedia($product->business_id, $product, $request, 'product_brochure', true);
            }

            return $product->load([
                'product_variations.variations.variation_location_details',
                'product_variations.variations.media',
                'brand',
                'unit',
                'category',
                'sub_category',
                'product_tax',
                'product_locations',
            ]);
        });

        if ($request->hasFile('image') && ! empty($oldImagePath) && $oldImageName !== $product->image && file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }

        return new ProductResource($product);
    }

    /**
     * Get the specified product
     *
     * @urlParam product required comma separated ids of products Example: 1
     * @queryParam selling_price_group (1, 0)
     * @queryParam send_lot_detail Send lot details in each variation location details(1, 0)
     * @response {
            "data": [
                {
                    "id": 1,
                    "name": "Men's Reverse Fleece Crew",
                    "business_id": 1,
                    "type": "single",
                    "sub_unit_ids": null,
                    "enable_stock": 1,
                    "alert_quantity": "5.0000",
                    "sku": "AS0001",
                    "barcode_type": "C128",
                    "expiry_period": null,
                    "expiry_period_type": null,
                    "enable_sr_no": 0,
                    "weight": null,
                    "product_custom_field1": null,
                    "product_custom_field2": null,
                    "product_custom_field3": null,
                    "product_custom_field4": null,
                    "image": null,
                    "woocommerce_media_id": null,
                    "product_description": null,
                    "created_by": 1,
                    "warranty_id": null,
                    "is_inactive": 0,
                    "repair_model_id": null,
                    "not_for_selling": 0,
                    "ecom_shipping_class_id": null,
                    "ecom_active_in_store": 1,
                    "woocommerce_product_id": 356,
                    "woocommerce_disable_sync": 0,
                    "image_url": "http://local.pos.com/img/default.png",
                    "product_variations": [
                        {
                            "id": 1,
                            "variation_template_id": null,
                            "name": "DUMMY",
                            "product_id": 1,
                            "is_dummy": 1,
                            "created_at": "2018-01-03 21:29:08",
                            "updated_at": "2018-01-03 21:29:08",
                            "variations": [
                                {
                                    "id": 1,
                                    "name": "DUMMY",
                                    "product_id": 1,
                                    "sub_sku": "AS0001",
                                    "product_variation_id": 1,
                                    "woocommerce_variation_id": null,
                                    "variation_value_id": null,
                                    "default_purchase_price": "130.0000",
                                    "dpp_inc_tax": "143.0000",
                                    "profit_percent": "0.0000",
                                    "default_sell_price": "130.0000",
                                    "sell_price_inc_tax": "143.0000",
                                    "created_at": "2018-01-03 21:29:08",
                                    "updated_at": "2020-06-09 00:23:22",
                                    "deleted_at": null,
                                    "combo_variations": null,
                                    "variation_location_details": [
                                        {
                                            "id": 56,
                                            "product_id": 1,
                                            "product_variation_id": 1,
                                            "variation_id": 1,
                                            "location_id": 1,
                                            "qty_available": "20.0000",
                                            "created_at": "2020-06-08 23:46:40",
                                            "updated_at": "2020-06-08 23:46:40"
                                        }
                                    ],
                                    "media": [
                                        {
                                            "id": 1,
                                            "business_id": 1,
                                            "file_name": "1591686466_978227300_nn.jpeg",
                                            "description": null,
                                            "uploaded_by": 9,
                                            "model_type": "App\\Variation",
                                            "woocommerce_media_id": null,
                                            "model_id": 1,
                                            "created_at": "2020-06-09 00:07:46",
                                            "updated_at": "2020-06-09 00:07:46",
                                            "display_name": "nn.jpeg",
                                            "display_url": "http://local.pos.com/uploads/media/1591686466_978227300_nn.jpeg"
                                        }
                                    ],
                                    "discounts": [
                                        {
                                            "id": 2,
                                            "name": "FLAT 10%",
                                            "business_id": 1,
                                            "brand_id": null,
                                            "category_id": null,
                                            "location_id": 1,
                                            "priority": 2,
                                            "discount_type": "fixed",
                                            "discount_amount": "5.0000",
                                            "starts_at": "2021-09-01 11:45:00",
                                            "ends_at": "2021-09-30 11:45:00",
                                            "is_active": 1,
                                            "spg": null,
                                            "applicable_in_cg": 1,
                                            "created_at": "2021-09-01 11:46:00",
                                            "updated_at": "2021-09-01 12:12:55",
                                            "formated_starts_at": " 11:45",
                                            "formated_ends_at": " 11:45"
                                        }
                                    ],
                                    "selling_price_group": [
                                        {
                                            "id": 2,
                                            "variation_id": 1,
                                            "price_group_id": 1,
                                            "price_inc_tax": "140.0000",
                                            "created_at": "2020-06-09 00:23:31",
                                            "updated_at": "2020-06-09 00:23:31"
                                        }
                                    ]
                                }
                            ]
                        }
                    ],
                    "brand": {
                        "id": 1,
                        "business_id": 1,
                        "name": "Levis",
                        "description": null,
                        "created_by": 1,
                        "deleted_at": null,
                        "created_at": "2018-01-03 21:19:47",
                        "updated_at": "2018-01-03 21:19:47"
                    },
                    "unit": {
                        "id": 1,
                        "business_id": 1,
                        "actual_name": "Pieces",
                        "short_name": "Pc(s)",
                        "allow_decimal": 0,
                        "base_unit_id": null,
                        "base_unit_multiplier": null,
                        "created_by": 1,
                        "deleted_at": null,
                        "created_at": "2018-01-03 15:15:20",
                        "updated_at": "2018-01-03 15:15:20"
                    },
                    "category": {
                        "id": 1,
                        "name": "Men's",
                        "business_id": 1,
                        "short_code": null,
                        "parent_id": 0,
                        "created_by": 1,
                        "category_type": "product",
                        "description": null,
                        "slug": null,
                        "woocommerce_cat_id": null,
                        "deleted_at": null,
                        "created_at": "2018-01-03 21:06:34",
                        "updated_at": "2018-01-03 21:06:34"
                    },
                    "sub_category": {
                        "id": 5,
                        "name": "Shirts",
                        "business_id": 1,
                        "short_code": null,
                        "parent_id": 1,
                        "created_by": 1,
                        "category_type": "product",
                        "description": null,
                        "slug": null,
                        "woocommerce_cat_id": null,
                        "deleted_at": null,
                        "created_at": "2018-01-03 21:08:18",
                        "updated_at": "2018-01-03 21:08:18"
                    },
                    "product_tax": {
                        "id": 1,
                        "business_id": 1,
                        "name": "VAT@10%",
                        "amount": 10,
                        "is_tax_group": 0,
                        "created_by": 1,
                        "woocommerce_tax_rate_id": null,
                        "deleted_at": null,
                        "created_at": "2018-01-04 02:40:07",
                        "updated_at": "2018-01-04 02:40:07"
                    },
                    "product_locations": [
                    {
                        "id": 1,
                        "business_id": 1,
                        "location_id": null,
                        "name": "Awesome Shop",
                        "landmark": "Linking Street",
                        "country": "USA",
                        "state": "Arizona",
                        "city": "Phoenix",
                        "zip_code": "85001",
                        "invoice_scheme_id": 1,
                        "invoice_layout_id": 1,
                        "selling_price_group_id": null,
                        "print_receipt_on_invoice": 1,
                        "receipt_printer_type": "browser",
                        "printer_id": null,
                        "mobile": null,
                        "alternate_number": null,
                        "email": null,
                        "website": null,
                        "featured_products": [
                            "5",
                            "71"
                        ],
                        "is_active": 1,
                        "default_payment_accounts": "{\"cash\":{\"is_enabled\":\"1\",\"account\":\"1\"},\"card\":{\"is_enabled\":\"1\",\"account\":\"3\"},\"cheque\":{\"is_enabled\":\"1\",\"account\":\"2\"},\"bank_transfer\":{\"is_enabled\":\"1\",\"account\":\"1\"},\"other\":{\"is_enabled\":\"1\",\"account\":\"3\"},\"custom_pay_1\":{\"is_enabled\":\"1\",\"account\":\"1\"},\"custom_pay_2\":{\"is_enabled\":\"1\",\"account\":\"2\"},\"custom_pay_3\":{\"is_enabled\":\"1\",\"account\":\"3\"}}",
                        "custom_field1": null,
                        "custom_field2": null,
                        "custom_field3": null,
                        "custom_field4": null,
                        "deleted_at": null,
                        "created_at": "2018-01-04 02:15:20",
                        "updated_at": "2020-06-09 01:07:05",
                        "pivot": {
                            "product_id": 2,
                            "location_id": 1
                        }
                    }]
                }
            ]
        }
     */
    public function show($product_ids)
    {
        $user = Auth::user();

        // if (!$user->can('api.access')) {
        //     return $this->respondUnauthorized();
        // }

        $business_id = $user->business_id;
        $filters['selling_price_group'] = request()->input('selling_price_group') == 1 ? true : false;

        $filters['product_ids'] = explode(',', $product_ids);

        $products = $this->__getProducts($business_id, $filters);

        return ProductResource::collection($products);
    }

    /**
     * Function to query product
     *
     * @return Response
     */
    private function __getProducts($business_id, $filters = [], $search = [], $pagination = false, $order_by = null, $order_direction = null)
    {
        $query = Product::where('business_id', $business_id);

        $with = ['product_variations.variations.variation_location_details', 'brand', 'unit', 'category', 'sub_category', 'product_tax', 'product_variations.variations.media', 'product_locations'];

        if (! empty($filters['category_id'])) {
            $category_ids = explode(',', $filters['category_id']);
            $query->whereIn('category_id', $category_ids);
        }

        if (! empty($filters['sub_category_id'])) {
            $sub_category_id = explode(',', $filters['sub_category_id']);
            $query->whereIn('sub_category_id', $sub_category_id);
        }

        if (! empty($filters['brand_id'])) {
            $brand_ids = explode(',', $filters['brand_id']);
            $query->whereIn('brand_id', $brand_ids);
        }

        if (! empty($filters['selling_price_group']) && $filters['selling_price_group'] == true) {
            $with[] = 'product_variations.variations.group_prices';
        }
        if (! empty($filters['location_id'])) {
            $location_id = $filters['location_id'];
            $query->whereHas('product_locations', function ($q) use ($location_id) {
                $q->where('product_locations.location_id', $location_id);
            });

            $with['product_variations.variations.variation_location_details'] = function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
            };

            $with['product_locations'] = function ($q) use ($location_id) {
                $q->where('product_locations.location_id', $location_id);
            };
        }

        if (! empty($filters['product_ids'])) {
            $query->whereIn('id', $filters['product_ids']);
        }

        if (! empty($search)) {
            $query->where(function ($query) use ($search) {
                if (! empty($search['name'])) {
                    $query->where('products.name', 'like', '%'.$search['name'].'%');
                }

                if (! empty($search['sku'])) {
                    $sku = $search['sku'];
                    $query->orWhere('sku', 'like', '%'.$sku.'%');
                    $query->orWhereHas('variations', function ($q) use ($sku) {
                        $q->where('variations.sub_sku', 'like', '%'.$sku.'%');
                    });
                }
            });
        }

        //Order by
        if (! empty($order_by)) {
            if ($order_by == 'product_name') {
                $query->orderBy('products.name', $order_direction);
            }

            if ($order_by == 'newest') {
                $query->orderBy('products.id', $order_direction);
            }
        }

        $query->with($with);

        $perPage = ! empty($filters['per_page']) ? $filters['per_page'] : $this->perPage;
        if ($pagination && $perPage != -1) {
            $products = $query->paginate($perPage);
            $products->appends(request()->query());
        } else {
            $products = $query->get();
        }

        return $products;
    }

    /**
     * List Variations
     *
     * @urlParam id comma separated ids of variations Example: 2
     * @queryParam product_id Filter by comma separated products ids
     * @queryParam location_id Example: 1
     * @queryParam brand_id
     * @queryParam category_id
     * @queryParam sub_category_id
     * @queryParam not_for_selling Values: 0 or 1
     * @queryParam name Search term for product name
     * @queryParam sku Search term for product sku
     * @queryParam per_page Total records per page. default: 10, Set -1 for no pagination Example:10
     * @response {
        "data": [
            {
                "variation_id": 1,
                "variation_name": "",
                "sub_sku": "AS0001",
                "product_id": 1,
                "product_name": "Men's Reverse Fleece Crew",
                "sku": "AS0001",
                "type": "single",
                "business_id": 1,
                "barcode_type": "C128",
                "expiry_period": null,
                "expiry_period_type": null,
                "enable_sr_no": 0,
                "weight": null,
                "product_custom_field1": null,
                "product_custom_field2": null,
                "product_custom_field3": null,
                "product_custom_field4": null,
                "product_image": "1528728059_fleece_crew.jpg",
                "product_description": null,
                "warranty_id": null,
                "brand_id": 1,
                "brand_name": "Levis",
                "unit_id": 1,
                "enable_stock": 1,
                "not_for_selling": 0,
                "unit_name": "Pc(s)",
                "unit_allow_decimal": 0,
                "category_id": 1,
                "category": "Men's",
                "sub_category_id": 5,
                "sub_category": "Shirts",
                "tax_id": 1,
                "tax_type": "exclusive",
                "tax_name": "VAT@10%",
                "tax_amount": 10,
                "product_variation_id": 1,
                "default_purchase_price": "130.0000",
                "dpp_inc_tax": "143.0000",
                "profit_percent": "0.0000",
                "default_sell_price": "130.0000",
                "sell_price_inc_tax": "143.0000",
                "product_variation_name": "",
                "variation_location_details": [],
                "media": [],
                "selling_price_group": [],
                "product_image_url": "http://local.pos.com/uploads/img/1528728059_fleece_crew.jpg",
                "product_locations": [
                    {
                        "id": 1,
                        "business_id": 1,
                        "location_id": null,
                        "name": "Awesome Shop",
                        "landmark": "Linking Street",
                        "country": "USA",
                        "state": "Arizona",
                        "city": "Phoenix",
                        "zip_code": "85001",
                        "invoice_scheme_id": 1,
                        "invoice_layout_id": 1,
                        "selling_price_group_id": null,
                        "print_receipt_on_invoice": 1,
                        "receipt_printer_type": "browser",
                        "printer_id": null,
                        "mobile": null,
                        "alternate_number": null,
                        "email": null,
                        "website": null,
                        "featured_products": null,
                        "is_active": 1,
                        "default_payment_accounts": "",
                        "custom_field1": null,
                        "custom_field2": null,
                        "custom_field3": null,
                        "custom_field4": null,
                        "deleted_at": null,
                        "created_at": "2018-01-04 02:15:20",
                        "updated_at": "2019-12-11 04:53:39",
                        "pivot": {
                            "product_id": 1,
                            "location_id": 1
                        }
                    }
                ]
            },
            {
                "variation_id": 2,
                "variation_name": "28",
                "sub_sku": "AS0002-1",
                "product_id": 2,
                "product_name": "Levis Men's Slimmy Fit Jeans",
                "sku": "AS0002",
                "type": "variable",
                "business_id": 1,
                "barcode_type": "C128",
                "expiry_period": null,
                "expiry_period_type": null,
                "enable_sr_no": 0,
                "weight": null,
                "product_custom_field1": null,
                "product_custom_field2": null,
                "product_custom_field3": null,
                "product_custom_field4": null,
                "product_image": "1528727964_levis_jeans.jpg",
                "product_description": null,
                "warranty_id": null,
                "brand_id": 1,
                "brand_name": "Levis",
                "unit_id": 1,
                "enable_stock": 1,
                "not_for_selling": 0,
                "unit_name": "Pc(s)",
                "unit_allow_decimal": 0,
                "category_id": 1,
                "category": "Men's",
                "sub_category_id": 4,
                "sub_category": "Jeans",
                "tax_id": 1,
                "tax_type": "exclusive",
                "tax_name": "VAT@10%",
                "tax_amount": 10,
                "product_variation_id": 2,
                "default_purchase_price": "70.0000",
                "dpp_inc_tax": "77.0000",
                "profit_percent": "0.0000",
                "default_sell_price": "70.0000",
                "sell_price_inc_tax": "77.0000",
                "product_variation_name": "Waist Size",
                "variation_location_details": [
                    {
                        "id": 1,
                        "product_id": 2,
                        "product_variation_id": 2,
                        "variation_id": 2,
                        "location_id": 1,
                        "qty_available": "50.0000",
                        "created_at": "2018-01-06 06:57:11",
                        "updated_at": "2020-08-04 04:11:27"
                    }
                ],
                "media": [
                    {
                        "id": 1,
                        "business_id": 1,
                        "file_name": "1596701997_743693452_test.jpg",
                        "description": null,
                        "uploaded_by": 9,
                        "model_type": "App\\Variation",
                        "woocommerce_media_id": null,
                        "model_id": 2,
                        "created_at": "2020-08-06 13:49:57",
                        "updated_at": "2020-08-06 13:49:57",
                        "display_name": "test.jpg",
                        "display_url": "http://local.pos.com/uploads/media/1596701997_743693452_test.jpg"
                    }
                ],
                "selling_price_group": [],
                "product_image_url": "http://local.pos.com/uploads/img/1528727964_levis_jeans.jpg",
                "product_locations": [
                    {
                        "id": 1,
                        "business_id": 1,
                        "location_id": null,
                        "name": "Awesome Shop",
                        "landmark": "Linking Street",
                        "country": "USA",
                        "state": "Arizona",
                        "city": "Phoenix",
                        "zip_code": "85001",
                        "invoice_scheme_id": 1,
                        "invoice_layout_id": 1,
                        "selling_price_group_id": null,
                        "print_receipt_on_invoice": 1,
                        "receipt_printer_type": "browser",
                        "printer_id": null,
                        "mobile": null,
                        "alternate_number": null,
                        "email": null,
                        "website": null,
                        "featured_products": null,
                        "is_active": 1,
                        "default_payment_accounts": "",
                        "custom_field1": null,
                        "custom_field2": null,
                        "custom_field3": null,
                        "custom_field4": null,
                        "deleted_at": null,
                        "created_at": "2018-01-04 02:15:20",
                        "updated_at": "2019-12-11 04:53:39",
                        "pivot": {
                            "product_id": 2,
                            "location_id": 1
                        }
                    }
                ],
                "discounts": [
                    {
                        "id": 2,
                        "name": "FLAT 10%",
                        "business_id": 1,
                        "brand_id": null,
                        "category_id": null,
                        "location_id": 1,
                        "priority": 2,
                        "discount_type": "fixed",
                        "discount_amount": "5.0000",
                        "starts_at": "2021-09-01 11:45:00",
                        "ends_at": "2021-09-30 11:45:00",
                        "is_active": 1,
                        "spg": null,
                        "applicable_in_cg": 1,
                        "created_at": "2021-09-01 11:46:00",
                        "updated_at": "2021-09-01 12:12:55",
                        "formated_starts_at": " 11:45",
                        "formated_ends_at": " 11:45"
                    }
                ]
            }
        ],
        "links": {
            "first": "http://local.pos.com/connector/api/variation?page=1",
            "last": null,
            "prev": null,
            "next": "http://local.pos.com/connector/api/variation?page=2"
        },
        "meta": {
            "current_page": 1,
            "from": 1,
            "path": "http://local.pos.com/connector/api/variation",
            "per_page": "2",
            "to": 2
        }
    }
     */
    public function listVariations($variation_ids = null)
    {
        $user = Auth::user();

        $business_id = $user->business_id;

        $query = Variation::join('products AS p', 'variations.product_id', '=', 'p.id')
                ->join('product_variations AS pv', 'variations.product_variation_id', '=', 'pv.id')
                ->leftjoin('units', 'p.unit_id', '=', 'units.id')
                ->leftjoin('tax_rates as tr', 'p.tax', '=', 'tr.id')
                ->leftjoin('brands', function ($join) {
                    $join->on('p.brand_id', '=', 'brands.id')
                        ->whereNull('brands.deleted_at');
                })
                ->leftjoin('categories as c', 'p.category_id', '=', 'c.id')
                ->leftjoin('categories as sc', 'p.sub_category_id', '=', 'sc.id')
                ->where('p.business_id', $business_id)
                ->select(
                    'variations.id',
                    'variations.name as variation_name',
                    'variations.sub_sku',
                    'p.id as product_id',
                    'p.name as product_name',
                    'p.sku',
                    'p.type as type',
                    'p.business_id',
                    'p.barcode_type',
                    'p.expiry_period',
                    'p.expiry_period_type',
                    'p.enable_sr_no',
                    'p.weight',
                    'p.product_custom_field1',
                    'p.product_custom_field2',
                    'p.product_custom_field3',
                    'p.product_custom_field4',
                    'p.image as product_image',
                    'p.product_description',
                    'p.warranty_id',
                    'p.brand_id',
                    'brands.name as brand_name',
                    'p.unit_id',
                    'p.enable_stock',
                    'p.not_for_selling',
                    'units.short_name as unit_name',
                    'units.allow_decimal as unit_allow_decimal',
                    'p.category_id',
                    'c.name as category',
                    'p.sub_category_id',
                    'sc.name as sub_category',
                    'p.tax as tax_id',
                    'p.tax_type',
                    'tr.name as tax_name',
                    'tr.amount as tax_amount',
                    'variations.product_variation_id',
                    'variations.default_purchase_price',
                    'variations.dpp_inc_tax',
                    'variations.profit_percent',
                    'variations.default_sell_price',
                    'variations.sell_price_inc_tax',
                    'pv.id as product_variation_id',
                    'pv.name as product_variation_name'
                );

        $with = [
            'variation_location_details',
            'media',
            'group_prices',
            'product',
            'product.product_locations',
        ];

        if (! empty(request()->input('category_id'))) {
            $query->where('category_id', request()->input('category_id'));
        }

        if (! empty(request()->input('sub_category_id'))) {
            $query->where('p.sub_category_id', request()->input('sub_category_id'));
        }

        if (! empty(request()->input('brand_id'))) {
            $query->where('p.brand_id', request()->input('brand_id'));
        }

        if (request()->has('not_for_selling')) {
            $not_for_selling = request()->input('not_for_selling') == 1 ? 1 : 0;
            $query->where('p.not_for_selling', $not_for_selling);
        }
        $filters['selling_price_group'] = request()->input('selling_price_group') == 1 ? true : false;

        if (! empty(request()->input('location_id'))) {
            $location_id = request()->input('location_id');
            $query->whereHas('product.product_locations', function ($q) use ($location_id) {
                $q->where('product_locations.location_id', $location_id);
            });

            $with['variation_location_details'] = function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
            };

            $with['product.product_locations'] = function ($q) use ($location_id) {
                $q->where('product_locations.location_id', $location_id);
            };
        }

        $search = request()->only(['sku', 'name']);

        if (! empty($search)) {
            $query->where(function ($query) use ($search) {
                if (! empty($search['name'])) {
                    $query->where('p.name', 'like', '%'.$search['name'].'%');
                }

                if (! empty($search['sku'])) {
                    $sku = $search['sku'];
                    $query->orWhere('p.sku', 'like', '%'.$sku.'%')
                        ->where('variations.sub_sku', 'like', '%'.$sku.'%');
                }
            });
        }

        //filter by variations ids
        if (! empty($variation_ids)) {
            $variation_ids = explode(',', $variation_ids);
            $query->whereIn('variations.id', $variation_ids);
        }

        //filter by product ids
        if (! empty(request()->input('product_id'))) {
            $product_ids = explode(',', request()->input('product_id'));
            $query->whereIn('p.id', $product_ids);
        }

        $query->with($with);

        $perPage = ! empty(request()->input('per_page')) ? request()->input('per_page') : $this->perPage;
        if ($perPage == -1) {
            $variations = $query->get();
        } else {
            //paginate
            $variations = $query->paginate($perPage);
            $variations->appends(request()->query());
        }

        return VariationResource::collection($variations);
    }

    /**
     * List Selling Price Group
     *
     * @response {
        "data": [
            {
                "id": 1,
                "name": "Retail",
                "description": null,
                "business_id": 1,
                "is_active": 1,
                "deleted_at": null,
                "created_at": "2020-10-21 04:30:06",
                "updated_at": "2020-11-16 18:23:15"
            },
            {
                "id": 2,
                "name": "Wholesale",
                "description": null,
                "business_id": 1,
                "is_active": 1,
                "deleted_at": null,
                "created_at": "2020-10-21 04:30:21",
                "updated_at": "2020-11-16 18:23:00"
            }
        ]
    }
     */
    public function getSellingPriceGroup()
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        $price_groups = SellingPriceGroup::where('business_id', $business_id)
                                        ->get();

        return CommonResource::collection($price_groups);
    }
}
