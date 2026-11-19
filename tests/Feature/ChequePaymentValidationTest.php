<?php

namespace Tests\Feature;

use App\Http\Controllers\TransactionPaymentController;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Connector\Http\Controllers\Api\PurchaseController;
use Tests\TestCase;

class ChequePaymentValidationTest extends TestCase
{
    public function test_direct_payment_requires_cheque_number_and_due_date()
    {
        $request = Request::create('/transaction-payment', 'POST', ['method' => 'cheque']);
        $controller = $this->app->make(TransactionPaymentController::class);

        try {
            $controller->store($request);
            $this->fail('A cheque payment without cheque details was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cheque_number', $exception->errors());
            $this->assertArrayHasKey('cheque_due_date', $exception->errors());
        }
    }

    public function test_transaction_payment_lines_require_cheque_details()
    {
        $this->expectException(ValidationException::class);

        $util = $this->app->make(TransactionUtil::class);
        $util->editPaymentLine(['method' => 'cheque', 'payment_id' => 1]);
    }

    public function test_purchase_api_requires_and_accepts_nested_cheque_details()
    {
        $controller = $this->app->make(PurchaseController::class);
        $rulesMethod = new \ReflectionMethod(PurchaseController::class, 'chequePaymentValidationRules');
        $rulesMethod->setAccessible(true);
        $rules = $rulesMethod->invoke($controller);

        $missingDetails = Validator::make([
            'payments' => [['method' => 'cheque']],
        ], $rules);

        $this->assertTrue($missingDetails->fails());
        $this->assertArrayHasKey('payments.0.cheque_number', $missingDetails->errors()->toArray());
        $this->assertArrayHasKey('payments.0.cheque_due_date', $missingDetails->errors()->toArray());

        $completeDetails = Validator::make([
            'payments' => [[
                'method' => 'cheque',
                'cheque_number' => 'CHQ-1001',
                'cheque_due_date' => '2026-11-30',
            ]],
        ], $rules);

        $this->assertFalse($completeDetails->fails());
    }
}
