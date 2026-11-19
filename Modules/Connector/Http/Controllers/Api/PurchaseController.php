<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Account;
use App\Business;
use App\BusinessLocation;
use App\Product;
use App\TaxRate;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Events\PurchaseCreatedOrModified;

/**
 * @group Purchase management
 * @authenticated
 */
class PurchaseController extends ApiController
{
    protected $productUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        parent::__construct();
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * List purchases belonging to the authenticated user's business.
     *
     * @queryParam location_id integer Optional accessible business location ID. Example: 1
     * @queryParam contact_id integer Optional supplier contact ID. Example: 12
     * @queryParam status string Optional received, pending, ordered, or draft. Example: received
     * @queryParam payment_status string Optional paid, partial, or due. Example: due
     * @queryParam start_date string Optional inclusive start date. Example: 2026-10-01
     * @queryParam end_date string Optional inclusive end date. Example: 2026-10-31
     * @queryParam per_page integer Optional page size from 1 to 100. Example: 25
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();
        $businessId = $user->business_id;
        $permittedLocations = $user->permitted_locations($businessId);
        if (! $user->can('purchase.view') && ! $user->can('view_own_purchase')) {
            return response()->json(['error' => ['message' => 'You are not authorized to view purchases.']], 403);
        }

        $filters = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['received', 'pending', 'ordered', 'draft'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'partial', 'due'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Transaction::where('business_id', $businessId)
            ->where('type', 'purchase')
            ->with(['contact', 'location', 'purchase_lines.product', 'purchase_lines.variations', 'payment_lines']);

        if ($permittedLocations !== 'all') {
            $query->whereIn('location_id', $permittedLocations);
        }
        if (! $user->can('purchase.view') && $user->can('view_own_purchase')) {
            $query->where('created_by', $user->id);
        }
        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }
        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['end_date']);
        }

        $purchases = $query->orderByDesc('transaction_date')
            ->paginate($filters['per_page'] ?? $this->perPage);

        return response()->json([
            'data' => $purchases->getCollection()->map(fn ($purchase) => $this->formatPurchase($purchase)),
            'current_page' => $purchases->currentPage(),
            'last_page' => $purchases->lastPage(),
            'per_page' => $purchases->perPage(),
            'total' => $purchases->total(),
        ]);
    }

    /**
     * Show one purchase belonging to the authenticated user's business.
     */
    public function show($id)
    {
        $user = auth('api')->user();
        $permittedLocations = $user->permitted_locations($user->business_id);
        if (! $user->can('purchase.view') && ! $user->can('view_own_purchase')) {
            return response()->json(['error' => ['message' => 'You are not authorized to view this purchase.']], 403);
        }
        $purchaseQuery = Transaction::where('business_id', $user->business_id)
            ->where('type', 'purchase')
            ->with(['contact', 'location', 'purchase_lines.product', 'purchase_lines.variations', 'payment_lines']);
        if ($permittedLocations !== 'all') {
            $purchaseQuery->whereIn('location_id', $permittedLocations);
        }
        if (! $user->can('purchase.view') && $user->can('view_own_purchase')) {
            $purchaseQuery->where('created_by', $user->id);
        }
        $purchase = $purchaseQuery->findOrFail($id);

        return response()->json(['data' => $this->formatPurchase($purchase)]);
    }

    /**
     * Create a purchase and update stock for received purchases.
     *
     * @bodyParam contact_id integer required Supplier contact ID in this business. Example: 12
     * @bodyParam location_id integer required Active business location accessible to the authenticated user. Example: 1
     * @bodyParam transaction_date string Optional purchase date. Example: 2026-10-25
     * @bodyParam status string Optional received, pending, ordered, or draft. Defaults to received. Example: received
     * @bodyParam ref_no string Optional purchase reference number; generated when omitted or blank. Example: PO-API-1001
     * @bodyParam exchange_rate number Optional positive rate for the purchase currency. Defaults to 1. Example: 1
     * @bodyParam tax_id integer Optional purchase-level tax rate ID belonging to this business. Example: 2
     * @bodyParam discount_type string Optional fixed or percentage. Defaults to fixed. Example: fixed
     * @bodyParam discount_amount number Optional non-negative discount in purchase currency. Example: 5
     * @bodyParam shipping_charges number Optional non-negative shipping charge in purchase currency. Example: 3
     * @bodyParam additional_expense_key_1 string Optional first additional expense label. Example: Freight
     * @bodyParam additional_expense_value_1 number Optional first additional expense amount. Example: 2
     * @bodyParam additional_expense_key_2 string Optional second additional expense label.
     * @bodyParam additional_expense_value_2 number Optional second additional expense amount.
     * @bodyParam additional_expense_key_3 string Optional third additional expense label.
     * @bodyParam additional_expense_value_3 number Optional third additional expense amount.
     * @bodyParam additional_expense_key_4 string Optional fourth additional expense label.
     * @bodyParam additional_expense_value_4 number Optional fourth additional expense amount.
     * @bodyParam shipping_details string Optional shipping information.
     * @bodyParam additional_notes string Optional purchase notes.
     * @bodyParam pay_term_number integer Optional supplier payment term length. Example: 30
     * @bodyParam pay_term_type string Required with pay_term_number; days or months. Example: days
     * @bodyParam custom_field_1 string Optional purchase custom field.
     * @bodyParam custom_field_2 string Optional purchase custom field.
     * @bodyParam custom_field_3 string Optional purchase custom field.
     * @bodyParam custom_field_4 string Optional purchase custom field.
     * @bodyParam shipping_custom_field_1 string Optional shipping custom field.
     * @bodyParam shipping_custom_field_2 string Optional shipping custom field.
     * @bodyParam shipping_custom_field_3 string Optional shipping custom field.
     * @bodyParam shipping_custom_field_4 string Optional shipping custom field.
     * @bodyParam shipping_custom_field_5 string Optional shipping custom field.
     * @bodyParam products array required At least one purchase line. Quantities are in the product base unit.
     * @bodyParam products.*.product_id integer required Business-owned product ID. Example: 10
     * @bodyParam products.*.variation_id integer required Variation belonging to product_id. Example: 18
     * @bodyParam products.*.quantity number required Positive quantity in the base unit. Example: 5
     * @bodyParam products.*.unit_cost_before_discount number required Non-negative unit purchase cost before line discount. Example: 12.5
     * @bodyParam products.*.discount_percent number Optional line discount between 0 and 100. Example: 5
     * @bodyParam products.*.purchase_line_tax_id integer Optional line tax rate ID belonging to this business. Example: 3
     * @bodyParam products.*.lot_number string Optional inventory lot number. Example: LOT-001
     * @bodyParam products.*.mfg_date string Optional manufacture date. Example: 2026-01-01
     * @bodyParam products.*.exp_date string Optional expiration date. Example: 2027-01-01
     * @bodyParam payments array Optional purchase payments; cannot be used with draft status.
     * @bodyParam payments.*.amount number required Positive payment amount in the business base currency. Example: 10
     * @bodyParam payments.*.method string required Payment method enabled for the selected location, or advance. Example: cash
     * @bodyParam payments.*.cheque_number string Required when method is cheque. Example: CHQ-1001
     * @bodyParam payments.*.cheque_due_date date Required when method is cheque; use YYYY-MM-DD. Example: 2026-11-30
     * @bodyParam payments.*.paid_on string Optional payment date. Example: 2026-10-25
     * @bodyParam payments.*.note string Optional payment note.
     * @bodyParam payments.*.account_id integer Optional active account ID accessible to the user; requires account.access permission.
     * @bodyParam payments.*.transaction_no_1 string Optional transaction reference for custom_pay_1.
     * @bodyParam payments.*.transaction_no_2 string Optional transaction reference for custom_pay_2.
     * @bodyParam payments.*.transaction_no_3 string Optional transaction reference for custom_pay_3.
     * @bodyParam payments.*.transaction_no_4 string Optional transaction reference for custom_pay_4.
     * @bodyParam payments.*.transaction_no_5 string Optional transaction reference for custom_pay_5.
     * @bodyParam payments.*.transaction_no_6 string Optional transaction reference for custom_pay_6.
     * @bodyParam payments.*.transaction_no_7 string Optional transaction reference for custom_pay_7.
     * @bodyParam document file Optional purchase document. Must use a configured document MIME type and size limit.
     * @response 201 {"data":{"id":1001,"ref_no":"PO-API-1001","status":"received","payment_status":"due","products":[],"payments":[]}}
     * @response 403 {"error":{"message":"You are not authorized to create purchases."}}
     * @response 422 {"message":"The given data was invalid.","errors":{"products.0.quantity":["The quantity must be greater than 0."]}}
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();
        $businessId = $user->business_id;

        if (! $user->can('purchase.create')) {
            return response()->json(['error' => ['message' => 'You are not authorized to create purchases.']], 403);
        }

        $documentMimeExtensions = config('constants.document_upload_mimes_types', []);
        $documentMimes = array_keys($documentMimeExtensions);
        $documentExtensions = array_map(function ($extension) {
            return ltrim($extension, '.');
        }, array_values($documentMimeExtensions));
        $documentMaxKilobytes = (int) floor(config('constants.document_size_limit') / 1024);

        $validationRules = [
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->whereIn('type', ['supplier', 'both']);
            })],
            'location_id' => ['required', 'integer', Rule::exists('business_locations', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('is_active', 1);
            })],
            'transaction_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['received', 'pending', 'ordered', 'draft'])],
            'ref_no' => ['nullable', 'string', 'max:191'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'tax_id' => ['nullable', 'integer', 'min:1', Rule::exists('tax_rates', 'id')->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('for_tax_group', 0);
            })],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_charges' => ['nullable', 'numeric', 'min:0'],
            'shipping_details' => ['nullable', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'pay_term_number' => ['nullable', 'integer', 'min:0', 'required_with:pay_term_type'],
            'pay_term_type' => ['nullable', Rule::in(['days', 'months']), 'required_with:pay_term_number'],
            'document' => ['sometimes', 'nullable', 'file', 'mimetypes:'.implode(',', $documentMimes), 'mimes:'.implode(',', $documentExtensions), 'max:'.$documentMaxKilobytes],
            'custom_field_1' => ['nullable', 'string', 'max:191'],
            'custom_field_2' => ['nullable', 'string', 'max:191'],
            'custom_field_3' => ['nullable', 'string', 'max:191'],
            'custom_field_4' => ['nullable', 'string', 'max:191'],
            'shipping_custom_field_1' => ['nullable', 'string', 'max:191'],
            'shipping_custom_field_2' => ['nullable', 'string', 'max:191'],
            'shipping_custom_field_3' => ['nullable', 'string', 'max:191'],
            'shipping_custom_field_4' => ['nullable', 'string', 'max:191'],
            'shipping_custom_field_5' => ['nullable', 'string', 'max:191'],
            'additional_expense_key_1' => ['nullable', 'string', 'max:191'],
            'additional_expense_key_2' => ['nullable', 'string', 'max:191'],
            'additional_expense_key_3' => ['nullable', 'string', 'max:191'],
            'additional_expense_key_4' => ['nullable', 'string', 'max:191'],
            'additional_expense_value_1' => ['nullable', 'numeric', 'min:0'],
            'additional_expense_value_2' => ['nullable', 'numeric', 'min:0'],
            'additional_expense_value_3' => ['nullable', 'numeric', 'min:0'],
            'additional_expense_value_4' => ['nullable', 'numeric', 'min:0'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer'],
            'products.*.variation_id' => ['required', 'integer'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.unit_cost_before_discount' => ['required', 'numeric', 'min:0'],
            'products.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'products.*.purchase_line_tax_id' => ['nullable', 'integer', 'min:1'],
            'products.*.lot_number' => ['nullable', 'string', 'max:191'],
            'products.*.mfg_date' => ['nullable', 'date'],
            'products.*.exp_date' => ['nullable', 'date'],
            'payments' => ['sometimes', 'array'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card', 'cheque', 'bank_transfer', 'other', 'advance', 'custom_pay_1', 'custom_pay_2', 'custom_pay_3', 'custom_pay_4', 'custom_pay_5', 'custom_pay_6', 'custom_pay_7'])],
            'payments.*.paid_on' => ['nullable', 'date'],
            'payments.*.note' => ['nullable', 'string', 'max:1000'],
            'payments.*.account_id' => ['nullable', 'integer', 'min:1'],
            'payments.*.transaction_no_1' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_2' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_3' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_4' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_5' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_6' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_7' => ['nullable', 'string', 'max:191'],
        ];
        $data = $request->validate(array_merge($validationRules, $this->chequePaymentValidationRules()));

        if (($data['discount_type'] ?? 'fixed') === 'percentage' && ($data['discount_amount'] ?? 0) > 100) {
            return response()->json(['error' => ['message' => 'The purchase discount percentage cannot exceed 100.']], 422);
        }

        if (($data['status'] ?? 'received') === 'draft' && ! empty($data['payments'])) {
            return response()->json(['error' => ['message' => 'Draft purchases cannot include payments.']], 422);
        }

        $locationId = (int) $data['location_id'];
        $permittedLocations = $user->permitted_locations($businessId);
        if ($permittedLocations !== 'all'
            && ! in_array($locationId, array_map('intval', $permittedLocations), true)) {
            return response()->json(['error' => ['message' => 'You are not authorized to access this business location.']], 403);
        }
        $location = BusinessLocation::where('business_id', $businessId)->findOrFail($locationId);
        $availablePaymentMethods = array_keys($this->productUtil->payment_types($location, true, $businessId));

        $business = Business::findOrFail($businessId);
        if (! $this->moduleUtil->isSubscribed($businessId)) {
            return response()->json(['error' => ['message' => 'The business subscription is inactive.']], 403);
        }

        $products = [];
        foreach ($data['products'] as $index => $line) {
            $product = Product::where('business_id', $businessId)->find($line['product_id']);
            $variation = Variation::where('product_id', $line['product_id'])->find($line['variation_id']);
            if (! $product || ! $variation) {
                return response()->json([
                    'error' => [
                        'message' => 'The product or variation does not belong to this business.',
                        'line' => $index,
                    ],
                ], 422);
            }
            if ($product->unit && ! $product->unit->allow_decimal && floor((float) $line['quantity']) != (float) $line['quantity']) {
                return response()->json([
                    'error' => [
                        'message' => 'This product only supports whole-number quantities in its base unit.',
                        'line' => $index,
                    ],
                ], 422);
            }

            $taxId = $line['purchase_line_tax_id'] ?? null;
            $lineTax = $taxId !== null
                ? TaxRate::where('business_id', $businessId)->ExcludeForTaxGroup()->find($taxId)
                : null;
            if ($taxId !== null && ! $lineTax) {
                return response()->json([
                    'error' => [
                        'message' => 'The purchase line tax is not a selectable tax in this business.',
                        'line' => $index,
                    ],
                ], 422);
            }

            $unitCostBeforeDiscount = (float) $line['unit_cost_before_discount'];
            $discountPercent = (float) ($line['discount_percent'] ?? 0);
            $purchasePrice = $unitCostBeforeDiscount * (1 - ($discountPercent / 100));
            $itemTax = $purchasePrice * (($lineTax->amount ?? 0) / 100);
            $quantity = (float) $line['quantity'];
            $products[] = [
                'product_id' => $product->id,
                'variation_id' => $variation->id,
                'quantity' => $quantity,
                'pp_without_discount' => $unitCostBeforeDiscount,
                'discount_percent' => $discountPercent,
                'purchase_price' => $purchasePrice,
                'purchase_price_inc_tax' => $purchasePrice + $itemTax,
                'item_tax' => $itemTax,
                'purchase_line_tax_id' => $taxId,
                'lot_number' => $line['lot_number'] ?? null,
                'mfg_date' => $line['mfg_date'] ?? null,
                'exp_date' => $line['exp_date'] ?? null,
            ];
        }

        $exchangeRate = (float) ($data['exchange_rate'] ?? 1);
        $subtotalBeforeTax = 0;
        $subtotalAfterLineTax = 0;
        foreach ($products as $line) {
            $subtotalBeforeTax += $line['purchase_price'] * $line['quantity'];
            $subtotalAfterLineTax += $line['purchase_price_inc_tax'] * $line['quantity'];
        }

        $discountType = $data['discount_type'] ?? 'fixed';
        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        $discount = $discountType === 'percentage'
            ? $subtotalAfterLineTax * ($discountAmount / 100)
            : $discountAmount;
        if ($discount > $subtotalAfterLineTax) {
            return response()->json(['error' => ['message' => 'The purchase discount cannot exceed the subtotal.']], 422);
        }

        $invoiceTax = 0;
        if (! empty($data['tax_id'])) {
            $tax = TaxRate::where('business_id', $businessId)
                ->ExcludeForTaxGroup()
                ->find($data['tax_id']);
            if (! $tax) {
                return response()->json(['error' => ['message' => 'The purchase tax does not belong to this business.']], 422);
            }
            $invoiceTax = ($subtotalAfterLineTax - $discount) * ($tax->amount / 100);
        }

        $shippingCharges = (float) ($data['shipping_charges'] ?? 0);
        $additionalExpenses = 0;
        for ($expense = 1; $expense <= 4; $expense++) {
            $additionalExpenses += (float) ($data['additional_expense_value_'.$expense] ?? 0);
        }
        $finalTotal = $subtotalAfterLineTax - $discount + $invoiceTax + $shippingCharges + $additionalExpenses;
        $paymentTotal = collect($data['payments'] ?? [])->sum('amount');
        if ($paymentTotal > ($finalTotal * $exchangeRate)) {
            return response()->json(['error' => ['message' => 'Purchase payments cannot exceed the purchase total.']], 422);
        }

        $accessibleAccountIds = $this->getAccessibleAccountIds($businessId, $permittedLocations);

        foreach ($data['payments'] ?? [] as $index => $payment) {
            if (! in_array($payment['method'], $availablePaymentMethods, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payments.$index.method" => 'The payment method is not enabled for the selected location.',
                ]);
            }

            if (! empty($payment['account_id'])) {
                if (! $user->can('account.access')) {
                    return response()->json(['error' => ['message' => 'You are not authorized to use payment accounts.']], 403);
                }
                if ($payment['method'] === 'advance') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "payments.$index.account_id" => 'Advance payments cannot be assigned to a payment account.',
                    ]);
                }
                $accountQuery = Account::where('business_id', $businessId)
                    ->where('is_closed', 0)
                    ->whereKey($payment['account_id']);
                if ($accessibleAccountIds !== null) {
                    $accountQuery->whereIn('id', $accessibleAccountIds);
                }
                $accountExists = $accountQuery->exists();
                if (! $accountExists) {
                    return response()->json([
                        'error' => [
                            'message' => 'The payment account is not active or is not accessible to this user.',
                            'payment' => $index,
                        ],
                    ], 422);
                }
            }
        }

        $currencyDetails = clone $this->transactionUtil->purchaseCurrencyDetails($businessId);
        $currencyDetails->thousand_separator = '';
        $currencyDetails->decimal_separator = '.';

        $documentName = $this->transactionUtil->uploadFile($request, 'document', 'documents');
        $purchase = DB::transaction(function () use ($data, $business, $businessId, $user, $products, $exchangeRate, $subtotalBeforeTax, $discountType, $discountAmount, $invoiceTax, $shippingCharges, $finalTotal, $currencyDetails, $documentName) {
            $referenceCount = $this->productUtil->setAndGetReferenceCount('purchase', $businessId);
            $referenceNumber = trim((string) ($data['ref_no'] ?? ''));
            if ($referenceNumber === '') {
                $referenceNumber = $this->productUtil->generateReferenceNumber('purchase', $referenceCount, $businessId);
            }
            $status = $data['status'] ?? 'received';

            $transactionData = [
                'business_id' => $businessId,
                'location_id' => $data['location_id'],
                'type' => 'purchase',
                'status' => $status,
                'contact_id' => $data['contact_id'],
                'ref_no' => $referenceNumber,
                'transaction_date' => Carbon::parse($data['transaction_date'] ?? now())->toDateTimeString(),
                'total_before_tax' => $subtotalBeforeTax * $exchangeRate,
                'tax_id' => $data['tax_id'] ?? null,
                'tax_amount' => $invoiceTax * $exchangeRate,
                'discount_type' => $discountType,
                'discount_amount' => ($discountType === 'fixed' ? $discountAmount * $exchangeRate : $discountAmount),
                'shipping_details' => $data['shipping_details'] ?? null,
                'shipping_charges' => $shippingCharges * $exchangeRate,
                'final_total' => $finalTotal * $exchangeRate,
                'additional_notes' => $data['additional_notes'] ?? null,
                'exchange_rate' => $exchangeRate,
                'payment_status' => 'due',
                'created_by' => $user->id,
                'document' => $documentName,
                'pay_term_number' => $data['pay_term_number'] ?? null,
                'pay_term_type' => $data['pay_term_type'] ?? null,
            ];
            foreach (['custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4',
                'shipping_custom_field_1', 'shipping_custom_field_2', 'shipping_custom_field_3',
                'shipping_custom_field_4', 'shipping_custom_field_5'] as $field) {
                $transactionData[$field] = $data[$field] ?? null;
            }
            for ($expense = 1; $expense <= 4; $expense++) {
                $key = 'additional_expense_key_'.$expense;
                $value = 'additional_expense_value_'.$expense;
                $transactionData[$key] = $data[$key] ?? null;
                $transactionData[$value] = (float) ($data[$value] ?? 0) * $exchangeRate;
            }

            $transaction = Transaction::create($transactionData);

            $this->productUtil->createOrUpdatePurchaseLines(
                $transaction,
                $products,
                $currencyDetails,
                $business->enable_editing_product_from_purchase ?? 0
            );
            $purchaseLines = $transaction->purchase_lines()->orderBy('id')->get();
            foreach ($products as $index => $line) {
                $purchaseLine = $purchaseLines->get($index);
                $purchaseLine->mfg_date = ! empty($line['mfg_date']) ? Carbon::parse($line['mfg_date'])->toDateString() : null;
                $purchaseLine->exp_date = ! empty($line['exp_date']) ? Carbon::parse($line['exp_date'])->toDateString() : null;
                $purchaseLine->save();
            }
            $this->transactionUtil->createOrUpdatePaymentLines(
                $transaction,
                $data['payments'] ?? [],
                $businessId,
                $user->id,
                false
            );
            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
            $this->productUtil->adjustStockOverSelling($transaction);
            $this->transactionUtil->activityLog($transaction, 'added', null, ['from_api' => $this->getClient()]);

            PurchaseCreatedOrModified::dispatch($transaction);

            return $transaction->load([
                'contact',
                'location',
                'purchase_lines.product',
                'purchase_lines.variations',
                'payment_lines',
            ]);
        });

        return response()->json(['data' => $this->formatPurchase($purchase)], 201);
    }

    /**
     * Update a purchase status and/or append payments without replacing payment history.
     *
     * @urlParam id integer required Purchase ID. Example: 1001
     * @bodyParam status string Optional received, pending, ordered, or draft. Example: received
     * @bodyParam payments array Optional new payments to append; existing payments are preserved.
     * @bodyParam payments.*.amount number required Positive payment amount in the business base currency. Example: 10
     * @bodyParam payments.*.method string required Payment method enabled for the purchase location, or advance. Example: cash
     * @bodyParam payments.*.cheque_number string Required when method is cheque. Example: CHQ-1001
     * @bodyParam payments.*.cheque_due_date date Required when method is cheque; use YYYY-MM-DD. Example: 2026-11-30
     * @bodyParam payments.*.paid_on string Optional payment date. Example: 2026-10-25
     * @bodyParam payments.*.note string Optional payment note.
     * @bodyParam payments.*.account_id integer Optional active account ID accessible to the user; requires account.access permission.
     * @bodyParam payments.*.transaction_no_1 string Optional transaction reference for custom_pay_1.
     * @bodyParam payments.*.transaction_no_2 string Optional transaction reference for custom_pay_2.
     * @bodyParam payments.*.transaction_no_3 string Optional transaction reference for custom_pay_3.
     * @bodyParam payments.*.transaction_no_4 string Optional transaction reference for custom_pay_4.
     * @bodyParam payments.*.transaction_no_5 string Optional transaction reference for custom_pay_5.
     * @bodyParam payments.*.transaction_no_6 string Optional transaction reference for custom_pay_6.
     * @bodyParam payments.*.transaction_no_7 string Optional transaction reference for custom_pay_7.
     * @response {"data":{"id":1001,"status":"received","payment_status":"partial","payments":[]}}
     * @response 403 {"error":{"message":"You are not authorized to update this purchase."}}
     * @response 422 {"message":"The given data was invalid.","errors":{"payments":["New purchase payments cannot exceed the remaining purchase balance."]}}
     * @response 404 {"message":"Not Found"}
     */
    public function update(Request $request, $id)
    {
        $user = auth('api')->user();
        $businessId = $user->business_id;
        $hasStatus = $request->has('status');
        $hasPayments = $request->has('payments');

        if (! $hasStatus && ! $hasPayments) {
            return response()->json(['error' => ['message' => 'Provide status and/or new payments to update.']], 422);
        }
        if ($hasStatus && ! $user->can('purchase.update') && ! $user->can('purchase.update_status')) {
            return response()->json(['error' => ['message' => 'You are not authorized to update purchase status.']], 403);
        }
        if ($hasPayments && ! $user->can('purchase.payments')) {
            return response()->json(['error' => ['message' => 'You are not authorized to add purchase payments.']], 403);
        }

        $validationRules = [
            'status' => ['sometimes', 'required', Rule::in(['received', 'pending', 'ordered', 'draft'])],
            'payments' => ['sometimes', 'required', 'array', 'min:1'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card', 'cheque', 'bank_transfer', 'other', 'advance', 'custom_pay_1', 'custom_pay_2', 'custom_pay_3', 'custom_pay_4', 'custom_pay_5', 'custom_pay_6', 'custom_pay_7'])],
            'payments.*.paid_on' => ['nullable', 'date'],
            'payments.*.note' => ['nullable', 'string', 'max:1000'],
            'payments.*.account_id' => ['nullable', 'integer', 'min:1'],
            'payments.*.transaction_no_1' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_2' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_3' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_4' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_5' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_6' => ['nullable', 'string', 'max:191'],
            'payments.*.transaction_no_7' => ['nullable', 'string', 'max:191'],
        ];
        $data = $request->validate(array_merge($validationRules, $this->chequePaymentValidationRules()));

        $permittedLocations = $user->permitted_locations($businessId);
        $purchaseQuery = Transaction::where('business_id', $businessId)
            ->where('type', 'purchase');
        if ($permittedLocations !== 'all') {
            $purchaseQuery->whereIn('location_id', $permittedLocations);
        }
        $purchase = $purchaseQuery->with(['purchase_lines', 'location', 'contact', 'payment_lines'])->findOrFail($id);
        if ($hasStatus) {
            $editDays = Business::whereKey($businessId)->value('transaction_edit_days');
            if (! $this->transactionUtil->canBeEdited($purchase, $editDays)) {
                return response()->json(['error' => ['message' => 'This purchase is outside the configured edit period.']], 422);
            }
        }
        $location = BusinessLocation::where('business_id', $businessId)->findOrFail($purchase->location_id);
        $availablePaymentMethods = array_keys($this->productUtil->payment_types($location, true, $businessId));
        $accessibleAccountIds = $this->getAccessibleAccountIds($businessId, $permittedLocations);

        foreach ($data['payments'] ?? [] as $index => $payment) {
            if (! in_array($payment['method'], $availablePaymentMethods, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payments.$index.method" => 'The payment method is not enabled for the purchase location.',
                ]);
            }

            if (! empty($payment['account_id'])) {
                if (! $user->can('account.access')) {
                    return response()->json(['error' => ['message' => 'You are not authorized to use payment accounts.']], 403);
                }
                if ($payment['method'] === 'advance') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "payments.$index.account_id" => 'Advance payments cannot be assigned to a payment account.',
                    ]);
                }
                $accountQuery = Account::where('business_id', $businessId)
                    ->where('is_closed', 0)
                    ->whereKey($payment['account_id']);
                if ($accessibleAccountIds !== null) {
                    $accountQuery->whereIn('id', $accessibleAccountIds);
                }
                if (! $accountQuery->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "payments.$index.account_id" => 'The payment account is not active or is not accessible to this user.',
                    ]);
                }
            }
        }

        $purchase = DB::transaction(function () use ($data, $purchase, $hasPayments, $businessId, $user, $permittedLocations) {
            $purchase = Transaction::where('business_id', $businessId)
                ->where('type', 'purchase')
                ->when($permittedLocations !== 'all', function ($query) use ($permittedLocations) {
                    $query->whereIn('location_id', $permittedLocations);
                })
                ->lockForUpdate()
                ->findOrFail($purchase->id);
            $beforeStatus = $purchase->status;
            $newStatus = $data['status'] ?? $beforeStatus;
            $transactionBefore = $purchase->replicate();

            if ($newStatus === 'draft' && ($purchase->payment_lines()->exists() || $hasPayments)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'A purchase with payments cannot be changed to draft.',
                ]);
            }

            if (isset($data['status']) && $data['status'] !== $beforeStatus) {
                $purchase->status = $newStatus;
                $purchase->save();

                $currencyDetails = $this->transactionUtil->purchaseCurrencyDetails($businessId);
                foreach ($purchase->purchase_lines as $line) {
                    $this->productUtil->updateProductStock(
                        $beforeStatus,
                        $purchase,
                        $line->product_id,
                        $line->variation_id,
                        $line->quantity,
                        $line->quantity,
                        $currencyDetails
                    );
                }
                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($beforeStatus, $purchase, null);
                $this->productUtil->adjustStockOverSelling($purchase);
            }

            if ($hasPayments) {
                $currentPaid = (float) $this->transactionUtil->getTotalPaid($purchase->id);
                $additionalPaymentTotal = collect($data['payments'])->sum('amount');
                if ($currentPaid + $additionalPaymentTotal > (float) $purchase->final_total + 0.00001) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'payments' => 'New purchase payments cannot exceed the remaining purchase balance.',
                    ]);
                }

                $this->transactionUtil->createOrUpdatePaymentLines(
                    $purchase,
                    $data['payments'],
                    $businessId,
                    $user->id,
                    false,
                    true
                );
                $this->transactionUtil->updatePaymentStatus($purchase->id, $purchase->final_total);
            }

            $this->transactionUtil->activityLog($purchase, 'edited', $transactionBefore, ['from_api' => $this->getClient()]);
            PurchaseCreatedOrModified::dispatch($purchase);

            return $purchase->load([
                'contact',
                'location',
                'purchase_lines.product',
                'purchase_lines.variations',
                'payment_lines',
            ]);
        });

        return response()->json(['data' => $this->formatPurchase($purchase)]);
    }

    private function getAccessibleAccountIds($businessId, $permittedLocations)
    {
        if ($permittedLocations === 'all') {
            return null;
        }

        $locationAccounts = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->whereIn('id', array_map('intval', $permittedLocations))
            ->pluck('default_payment_accounts');
        $accountIds = [];

        foreach ($locationAccounts as $paymentAccountSettings) {
            foreach (json_decode($paymentAccountSettings ?? '', true) ?? [] as $settings) {
                if (! empty($settings['is_enabled']) && ! empty($settings['account'])) {
                    $accountIds[] = (int) $settings['account'];
                }
            }
        }

        return array_values(array_unique($accountIds));
    }

    private function chequePaymentValidationRules()
    {
        return [
            'payments.*.cheque_number' => ['required_if:payments.*.method,cheque', 'nullable', 'string', 'max:255'],
            'payments.*.cheque_due_date' => ['required_if:payments.*.method,cheque', 'nullable', 'date'],
        ];
    }

    private function formatPurchase(Transaction $purchase)
    {
        return [
            'id' => $purchase->id,
            'ref_no' => $purchase->ref_no,
            'business_id' => $purchase->business_id,
            'location_id' => $purchase->location_id,
            'location_name' => $purchase->location->name ?? null,
            'contact_id' => $purchase->contact_id,
            'supplier_name' => $purchase->contact->name ?? null,
            'transaction_date' => $purchase->transaction_date,
            'status' => $purchase->status,
            'payment_status' => $purchase->payment_status,
            'total_before_tax' => $purchase->total_before_tax,
            'tax_amount' => $purchase->tax_amount,
            'discount_type' => $purchase->discount_type,
            'discount_amount' => $purchase->discount_amount,
            'shipping_charges' => $purchase->shipping_charges,
            'final_total' => $purchase->final_total,
            'exchange_rate' => $purchase->exchange_rate,
            'additional_notes' => $purchase->additional_notes,
            'tax_id' => $purchase->tax_id,
            'shipping_details' => $purchase->shipping_details,
            'document' => $purchase->document,
            'pay_term_number' => $purchase->pay_term_number,
            'pay_term_type' => $purchase->pay_term_type,
            'custom_field_1' => $purchase->custom_field_1,
            'custom_field_2' => $purchase->custom_field_2,
            'custom_field_3' => $purchase->custom_field_3,
            'custom_field_4' => $purchase->custom_field_4,
            'shipping_custom_field_1' => $purchase->shipping_custom_field_1,
            'shipping_custom_field_2' => $purchase->shipping_custom_field_2,
            'shipping_custom_field_3' => $purchase->shipping_custom_field_3,
            'shipping_custom_field_4' => $purchase->shipping_custom_field_4,
            'shipping_custom_field_5' => $purchase->shipping_custom_field_5,
            'additional_expenses' => collect(range(1, 4))->map(function ($expense) use ($purchase) {
                return [
                    'label' => $purchase->{'additional_expense_key_'.$expense},
                    'amount' => $purchase->{'additional_expense_value_'.$expense},
                ];
            })->values(),
            'products' => $purchase->purchase_lines->map(function ($line) {
                return [
                    'product_id' => $line->product_id,
                    'product_name' => $line->product->name ?? null,
                    'variation_id' => $line->variation_id,
                    'variation_name' => $line->variations->name ?? null,
                    'quantity' => $line->quantity,
                    'unit_cost_before_discount' => $line->pp_without_discount,
                    'discount_percent' => $line->discount_percent,
                    'purchase_price' => $line->purchase_price,
                    'purchase_price_inc_tax' => $line->purchase_price_inc_tax,
                    'tax_id' => $line->tax_id,
                    'item_tax' => $line->item_tax,
                    'lot_number' => $line->lot_number,
                    'mfg_date' => $line->mfg_date,
                    'exp_date' => $line->exp_date,
                ];
            })->values(),
            'payments' => $purchase->payment_lines->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'cheque_number' => $payment->cheque_number,
                    'cheque_due_date' => $payment->cheque_due_date,
                    'paid_on' => $payment->paid_on,
                    'note' => $payment->note,
                ];
            })->values(),
        ];
    }
}
