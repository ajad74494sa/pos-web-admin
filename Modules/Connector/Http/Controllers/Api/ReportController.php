<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\Utils\ProductUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends ApiController
{
    protected $productUtil;

    public function __construct(ProductUtil $productUtil)
    {
        $this->productUtil = $productUtil;
    }

    public function sales(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'location_id' => 'nullable|integer|min:1',
            'customer_id' => 'nullable|integer|min:1',
            'payment_status' => 'nullable|in:due,partial,paid,overdue',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $this->validateDateRange($filters);

        $user = Auth::guard('api')->user();
        if (! $user->hasAnyPermission(['sell.view', 'sell.create', 'direct_sell.access', 'view_own_sell_only'])) {
            return $this->respondUnauthorized();
        }

        $query = Transaction::query()
            ->where('transactions.business_id', $user->business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->with([
                'contact:id,name,supplier_business_name,contact_id',
                'payment_lines:id,transaction_id,method',
            ]);

        $this->applyLocationAccess($query, $user, 'transactions.location_id', $filters['location_id'] ?? null);

        if (! $user->hasAnyPermission(['sell.view', 'direct_sell.view'])) {
            $canViewOwnSales = $user->hasAnyPermission(['view_own_sell_only', 'access_own_shipping']);
            $canViewCommissionSales = $user->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping']);

            if ($canViewOwnSales && $canViewCommissionSales) {
                $query->where(function ($query) use ($user) {
                    $query->where('transactions.created_by', $user->id)
                        ->orWhere('transactions.commission_agent', $user->id);
                });
            } elseif ($canViewOwnSales) {
                $query->where('transactions.created_by', $user->id);
            } elseif ($canViewCommissionSales) {
                $query->where('transactions.commission_agent', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if (! empty($filters['customer_id'])) {
            $query->where('transactions.contact_id', $filters['customer_id']);
        }
        if (! empty($filters['payment_status'])) {
            $query->where('transactions.payment_status', $filters['payment_status']);
        }
        $this->applyDateFilters($query, $filters, 'transactions.transaction_date');

        $sales = $query->orderByDesc('transactions.transaction_date')
            ->paginate($filters['per_page'] ?? $this->perPage);

        $sales->getCollection()->transform(function ($sale) {
            return [
                'id' => $sale->id,
                'location_id' => $sale->location_id,
                'customer' => $sale->contact,
                'invoice_no' => $sale->invoice_no,
                'transaction_date' => $sale->transaction_date,
                'total_before_tax' => $sale->total_before_tax,
                'discount_amount' => $sale->discount_amount,
                'tax_amount' => $sale->tax_amount,
                'final_total' => $sale->final_total,
                'payment_status' => $sale->payment_status,
                'payment_methods' => $sale->payment_lines->pluck('method')->unique()->values(),
            ];
        });

        return $sales;
    }

    public function salesPayments(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'location_id' => 'nullable|integer|min:1',
            'customer_id' => 'nullable|integer|min:1',
            'payment_method' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $this->validateDateRange($filters);

        $user = Auth::guard('api')->user();
        if (! $user->can('purchase_n_sell_report.view')) {
            return $this->respondUnauthorized();
        }

        $businessId = $user->business_id;
        $childPayments = DB::table('transaction_payments as child_payments')
            ->join('transactions as child_transactions', 'child_payments.transaction_id', '=', 'child_transactions.id')
            ->where('child_payments.business_id', $businessId)
            ->whereNotNull('child_payments.parent_id')
            ->where('child_transactions.business_id', $businessId)
            ->whereIn('child_transactions.type', ['sell', 'opening_balance'])
            ->select('child_payments.parent_id')
            ->selectRaw('MIN(child_payments.transaction_id) as transaction_id')
            ->groupBy('child_payments.parent_id');

        $query = TransactionPayment::query()
            ->leftJoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->leftJoinSub($childPayments, 'child_payment_transactions', function ($join) {
                $join->on('child_payment_transactions.parent_id', '=', 'transaction_payments.id');
            })
            ->leftJoin('transactions as child_t', 'child_payment_transactions.transaction_id', '=', 'child_t.id')
            ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
            ->leftJoin('contacts as child_c', 'child_t.contact_id', '=', 'child_c.id')
            ->where('transaction_payments.business_id', $businessId)
            ->where(function ($query) use ($businessId) {
                $query->where(function ($query) use ($businessId) {
                    $query->where('t.business_id', $businessId)
                        ->whereIn('t.type', ['sell', 'opening_balance']);
                })->orWhere(function ($query) use ($businessId) {
                    $query->whereNull('t.id')
                        ->where('child_t.business_id', $businessId)
                        ->whereIn('child_t.type', ['sell', 'opening_balance']);
                });
            })
            ->select([
                'transaction_payments.id',
                'transaction_payments.amount',
                'transaction_payments.is_return',
                'transaction_payments.method',
                'transaction_payments.paid_on',
                'transaction_payments.payment_ref_no',
                'transaction_payments.transaction_no',
            ])
            ->selectRaw('COALESCE(t.id, child_t.id) as transaction_id')
            ->selectRaw('COALESCE(t.invoice_no, child_t.invoice_no) as invoice_no')
            ->selectRaw('COALESCE(c.id, child_c.id) as customer_id')
            ->selectRaw('COALESCE(c.name, child_c.name) as customer_name')
            ->selectRaw('COALESCE(c.supplier_business_name, child_c.supplier_business_name) as customer_business_name');

        $permittedLocations = $user->permitted_locations($businessId);
        if ($permittedLocations !== 'all') {
            $query->where(function ($query) use ($permittedLocations) {
                $query->where(function ($query) use ($permittedLocations) {
                    $query->whereNotNull('t.id')
                        ->whereIn('t.location_id', $permittedLocations);
                })->orWhere(function ($query) use ($permittedLocations) {
                    $query->whereNull('t.id')
                        ->whereIn('child_t.location_id', $permittedLocations);
                });
            });
        }

        if (! empty($filters['location_id'])) {
            $query->where(function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereNotNull('t.id')
                        ->where('t.location_id', $filters['location_id']);
                })->orWhere(function ($query) use ($filters) {
                    $query->whereNull('t.id')
                        ->where('child_t.location_id', $filters['location_id']);
                });
            });
        }
        if (! empty($filters['customer_id'])) {
            $query->where(function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereNotNull('t.id')
                        ->where('t.contact_id', $filters['customer_id']);
                })->orWhere(function ($query) use ($filters) {
                    $query->whereNull('t.id')
                        ->where('child_t.contact_id', $filters['customer_id']);
                });
            });
        }
        if (! empty($filters['payment_method'])) {
            $query->where('transaction_payments.method', $filters['payment_method']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('transaction_payments.paid_on', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('transaction_payments.paid_on', '<=', $filters['end_date']);
        }

        return $query->orderByDesc('transaction_payments.paid_on')
            ->paginate($filters['per_page'] ?? $this->perPage);
    }

    public function productSales(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'location_id' => 'nullable|integer|min:1',
            'customer_id' => 'nullable|integer|min:1',
            'category_id' => 'nullable|integer|min:1',
            'brand_id' => 'nullable|integer|min:1',
            'variation_id' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $this->validateDateRange($filters);

        $user = Auth::guard('api')->user();
        if (! $user->can('purchase_n_sell_report.view')) {
            return $this->respondUnauthorized();
        }

        $query = TransactionSellLine::query()
            ->join('transactions as t', 'transaction_sell_lines.transaction_id', '=', 't.id')
            ->join('products as p', 'transaction_sell_lines.product_id', '=', 'p.id')
            ->join('variations as v', 'transaction_sell_lines.variation_id', '=', 'v.id')
            ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
            ->where('t.business_id', $user->business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('transaction_sell_lines.parent_sell_line_id')
            ->select([
                'transaction_sell_lines.id',
                't.id as transaction_id',
                't.invoice_no',
                't.transaction_date',
                't.location_id',
                'c.id as customer_id',
                'c.name as customer_name',
                'p.id as product_id',
                'p.name as product_name',
                'p.sku',
                'pv.name as product_variation',
                'v.name as variation_name',
                'v.sub_sku',
                'u.short_name as unit',
                'transaction_sell_lines.unit_price_before_discount as unit_price',
                'transaction_sell_lines.unit_price_inc_tax as unit_sale_price',
                'transaction_sell_lines.item_tax',
            ])
            ->selectRaw('(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) as sold_quantity')
            ->selectRaw('((transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax) as subtotal');

        $this->applyLocationAccess($query, $user, 't.location_id', $filters['location_id'] ?? null);

        if (! empty($filters['customer_id'])) {
            $query->where('t.contact_id', $filters['customer_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('p.category_id', $filters['category_id']);
        }
        if (! empty($filters['brand_id'])) {
            $query->where('p.brand_id', $filters['brand_id']);
        }
        if (! empty($filters['variation_id'])) {
            $query->where('transaction_sell_lines.variation_id', $filters['variation_id']);
        }
        $this->applyDateFilters($query, $filters, 't.transaction_date');

        return $query->orderByDesc('t.transaction_date')
            ->paginate($filters['per_page'] ?? $this->perPage);
    }

    public function popularProducts(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'location_id' => 'nullable|integer|min:1',
            'category_id' => 'nullable|integer|min:1',
            'sub_category_id' => 'nullable|integer|min:1',
            'brand_id' => 'nullable|integer|min:1',
            'unit_id' => 'nullable|integer|min:1',
            'product_type' => 'nullable|in:single,variable,combo',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        $this->validateDateRange($filters);

        $user = Auth::guard('api')->user();
        if (! $user->can('trending_product_report.view')) {
            return $this->respondUnauthorized();
        }

        $products = $this->productUtil->getTrendingProducts($user->business_id, [
            'start_date' => $filters['start_date'] ?? null,
            'end_date' => $filters['end_date'] ?? null,
            'location_id' => $filters['location_id'] ?? null,
            'category' => $filters['category_id'] ?? null,
            'sub_category' => $filters['sub_category_id'] ?? null,
            'brand' => $filters['brand_id'] ?? null,
            'unit' => $filters['unit_id'] ?? null,
            'product_type' => $filters['product_type'] ?? null,
            'limit' => $filters['limit'] ?? 5,
        ]);

        return response()->json([
            'data' => $products,
            'chart' => [
                'labels' => $products->map(function ($product) {
                    return $product->product.' - '.$product->sku.' ('.$product->unit.')';
                })->values(),
                'values' => $products->pluck('total_unit_sold')->map(function ($quantity) {
                    return (float) $quantity;
                })->values(),
            ],
        ]);
    }

    private function applyLocationAccess(Builder $query, $user, $column, $locationId)
    {
        $permittedLocations = $user->permitted_locations($user->business_id);
        if ($permittedLocations !== 'all') {
            $query->whereIn($column, $permittedLocations);
        }

        if (! empty($locationId)) {
            $query->where($column, $locationId);
        }
    }

    private function applyDateFilters(Builder $query, array $filters, $column)
    {
        if (! empty($filters['start_date'])) {
            $query->whereDate($column, '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate($column, '<=', $filters['end_date']);
        }
    }

    private function validateDateRange(array $filters)
    {
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])
            && $filters['end_date'] < $filters['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => ['The end date must be on or after the start date.'],
            ]);
        }
    }
}
