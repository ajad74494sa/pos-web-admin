<?php

namespace App\Utils;

use App\NotificationTemplate;
use App\Transaction;

class GroupedPaymentReminderUtil extends Util
{
    protected $transactionUtil;

    protected $notificationUtil;

    public function __construct(TransactionUtil $transactionUtil, NotificationUtil $notificationUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->notificationUtil = $notificationUtil;
    }

    public function send($business_id, ?array $contact_ids = null, $permitted_locations = 'all', $is_automatic = false)
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'eligible' => 0,
            'failure_messages' => [],
            'customers' => [],
            'error' => null,
            'error_code' => null,
        ];

        $template = NotificationTemplate::where('business_id', $business_id)
            ->where('template_for', 'payment_reminder')
            ->first();

        if (empty($template)) {
            $result['error'] = __('lang_v1.payment_reminder_template_not_found');
            $result['error_code'] = 'template_missing';

            return $result;
        }

        $transactions_query = Transaction::where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->where('transactions.payment_status', '!=', 'paid')
            ->with(['contact', 'business.currency', 'payment_lines', 'location'])
            ->select('transactions.*');

        if (! is_null($contact_ids)) {
            $transactions_query->whereIn('transactions.contact_id', $contact_ids);
        }
        if ($permitted_locations !== 'all') {
            $transactions_query->whereIn('transactions.location_id', $permitted_locations);
        }

        $transactions_by_contact = $transactions_query->get()
            ->filter(function ($transaction) {
                return ! empty($transaction->contact);
            })
            ->groupBy('contact_id');

        if ($transactions_by_contact->isEmpty()) {
            $result['error'] = 'No selected customers have an outstanding balance.';
            $result['error_code'] = 'no_due_customers';

            return $result;
        }

        foreach ($transactions_by_contact as $contact_transactions) {
            $contact = $contact_transactions->first()->contact;
            $representative_transaction = null;
            $invoice_rows = [];
            $invoice_numbers = [];
            $total_due = 0;

            foreach ($contact_transactions as $transaction) {
                $paid_amount = (float) $this->transactionUtil->getTotalPaid($transaction->id);
                $invoice_due = max(0, (float) $transaction->final_total - $paid_amount);
                if ($invoice_due <= 0) {
                    continue;
                }

                $representative_transaction = $representative_transaction ?? $transaction;
                $invoice_number = trim((string) $transaction->invoice_no);
                if ($invoice_number === '') {
                    $invoice_number = trim((string) $transaction->ref_no);
                }
                if ($invoice_number === '') {
                    $invoice_number = (string) $transaction->id;
                }

                $due_date = ! empty($transaction->due_date)
                    ? $this->transactionUtil->format_date($transaction->due_date->toDateTimeString())
                    : 'No due date set / لا يوجد تاريخ استحقاق محدد';
                $invoice_numbers[] = $invoice_number;
                $invoice_rows[] = [
                    $invoice_number,
                    $due_date,
                    $this->transactionUtil->num_f($invoice_due, true, $transaction->business->currency),
                ];
                $total_due += $invoice_due;
            }

            if (empty($representative_transaction)) {
                continue;
            }
            $result['eligible']++;

            if (empty($contact->mobile)) {
                $result['failed']++;
                $message = 'Customer '.$contact->name.' has no mobile number.';
                $result['failure_messages'][] = $message;
                $result['customers'][] = [
                    'name' => $contact->name,
                    'status' => 'failed',
                    'message' => $message,
                ];

                continue;
            }

            $original_whatsapp_text = $template->whatsapp_text ?? '';
            $has_invoice_list = strpos($original_whatsapp_text, '{due_invoice_list}') !== false
                || strpos($original_whatsapp_text, '{invoice_number}') !== false;
            $has_total_due = strpos($original_whatsapp_text, '{total_due_amount}') !== false
                || strpos($original_whatsapp_text, '{due_amount}') !== false;
            $whatsapp_template = str_replace(
                ['{invoice_number}', '{due_amount}', '{due_invoice_list}', '{total_due_amount}'],
                ['__BULK_INVOICE_LIST__', '__BULK_TOTAL_DUE__', '__BULK_INVOICE_LIST__', '__BULK_TOTAL_DUE__'],
                $original_whatsapp_text
            );
            $tag_replaced_data = $this->notificationUtil->replaceTags(
                $representative_transaction->business,
                ['whatsapp_text' => $whatsapp_template],
                $representative_transaction
            );

            $currency = $representative_transaction->business->currency;
            $formatted_total_due = $this->transactionUtil->num_f($total_due, true, $currency);
            $invoice_list = $this->buildPaymentReminderInvoiceList($invoice_rows);
            $whatsapp_text = str_replace(
                ['__BULK_TOTAL_DUE__', '__BULK_INVOICE_LIST__'],
                [$formatted_total_due, $invoice_list],
                $tag_replaced_data['whatsapp_text']
            );
            if (! $has_invoice_list) {
                $whatsapp_text .= "\n\n".$invoice_list;
            }
            if (! $has_total_due) {
                $whatsapp_text .= "\nTotal due / إجمالي المبلغ المستحق: ".$formatted_total_due;
            }

            try {
                $openwa_response = $this->notificationUtil->dispatchOpenwaMessage([
                    'whatsapp_text' => trim($whatsapp_text),
                    'sms_settings' => $representative_transaction->business->sms_settings ?? [],
                    'mobile_number' => $contact->mobile,
                ]);

                if (! empty($openwa_response['success'])) {
                    $this->notificationUtil->activityLog(
                        $representative_transaction,
                        'whatsapp_notification_sent',
                        null,
                        [
                            'mobile' => $contact->mobile,
                            'contact_id' => $contact->id,
                            'invoice_numbers' => $invoice_numbers,
                            'total_due' => $formatted_total_due,
                            'is_automatic' => $is_automatic,
                        ],
                        false,
                        $business_id
                    );
                    $result['sent']++;
                    $result['customers'][] = [
                        'name' => $contact->name,
                        'status' => 'submitted',
                        'message' => 'Accepted or queued by OpenWA.',
                    ];
                } else {
                    $message = 'Customer '.$contact->name.': '.($openwa_response['message'] ?? 'OpenWA request failed.');
                    $result['failed']++;
                    $result['failure_messages'][] = $message;
                    $result['customers'][] = [
                        'name' => $contact->name,
                        'status' => 'failed',
                        'message' => $message,
                    ];
                    \Log::warning('OpenWA grouped reminder failed for contact '.$contact->id.': '.$message);
                }
            } catch (\Throwable $e) {
                $message = 'Customer '.$contact->name.': '.$e->getMessage();
                $result['failed']++;
                $result['failure_messages'][] = $message;
                $result['customers'][] = [
                    'name' => $contact->name,
                    'status' => 'failed',
                    'message' => $message,
                ];
                \Log::warning('OpenWA grouped reminder failed for contact '.$contact->id.': '.$e->getMessage());
            }
        }

        if ($result['eligible'] === 0) {
            $result['error'] = 'No selected customers have an outstanding balance.';
            $result['error_code'] = 'no_due_customers';
        }

        return $result;
    }

    private function buildPaymentReminderInvoiceList(array $rows)
    {
        $invoice_blocks = [];
        foreach ($rows as $index => $row) {
            $invoice_number = str_replace(["\r", "\n"], ' ', (string) ($row[0] ?? ''));
            $due_date = str_replace(["\r", "\n"], ' ', (string) ($row[1] ?? ''));
            $due_amount = str_replace(["\r", "\n"], ' ', (string) ($row[2] ?? ''));
            $invoice_blocks[] = '*'.($index + 1).'. Invoice number / رقم الفاتورة: '.$invoice_number."*\n"
                .'Due date / تاريخ الاستحقاق: '.$due_date."\n"
                .'Due amount / المبلغ المستحق: '.$due_amount;
        }

        return "Outstanding invoices / الفواتير المستحقة\n\n".implode("\n\n", $invoice_blocks);
    }
}
