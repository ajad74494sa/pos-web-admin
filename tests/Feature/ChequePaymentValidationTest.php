<?php

namespace Tests\Feature;

use App\Http\Controllers\TransactionPaymentController;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
}
