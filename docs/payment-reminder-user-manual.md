# WhatsApp Due Reminder User Manual

## Configure OpenWA

1. Open **Business Settings → OpenWA Settings**.
2. Enter the OpenWA Base URL, API Key, and Session ID.
3. Save the business settings.
4. Start and connect the configured session in OpenWA, then use **Test OpenWA** with a test number.

The test button uses the saved settings. An inactive session or an unreachable gateway must be corrected in OpenWA or the server/network configuration before reminders can be delivered.

## Configure the reminder message

Open **Notifications → Notification Templates → Customer Notifications** and edit the **Payment reminder** WhatsApp text. The message uses that template. The selected customer's outstanding invoices are added as clearly separated, mobile-friendly blocks. Each block shows the invoice number, due date, and due amount with English and Arabic labels; the total due is also included. This avoids fixed-width columns that can become difficult to read in WhatsApp's mixed right-to-left and left-to-right text.

The `{due_invoice_list}` tag inserts the breakdown and `{total_due_amount}` inserts the total. Existing `{invoice_number}` and `{due_amount}` tags are also supported for bulk reminders. If the template has no invoice-list or total tag, the corresponding details are appended automatically. Invoices without a due date display **No due date set / لا يوجد تاريخ استحقاق محدد**.

## Send to selected customers

1. Open **Sell → All Sales** and click **Send due reminder to all**.
2. Select only the customers to message. All checkboxes start unchecked; **Select all** is optional.
3. Click **Send reminders** and confirm.
4. Keep the page open while the progress bar processes customers one at a time.

Each selected customer receives one WhatsApp message containing all of their outstanding invoices in the user's permitted locations. Customers who are not selected are not submitted. The progress result counts messages submitted to OpenWA or queued by the application; it cannot confirm final WhatsApp delivery. If a customer is marked not sent or not confirmed, check OpenWA before retrying because the gateway may have accepted a message even if the response was lost.

## Schedule automatic reminders

Users with **Business Settings** access can open **Business Settings** and select the **Scheduled Due Reminders** button at the top of the settings navigation.

Before enabling scheduled reminders, save the authorized admin phone and email under **Business Settings → OpenWA Settings** or in the **Authorized admin contacts** box on the Scheduled Due Reminders page. These are the same saved contacts. The OTP is sent through OpenWA first; if that delivery fails, the system emails the code.

The scheduler is **off by default**. Turn it on or off using the button on the Scheduled Due Reminders page and confirm the OTP. This authorization applies to both the grouped scheduler and the legacy daily reminder command.

1. Choose **One-time date(s)** to enter one or more future dates, or **Monthly recurring day** to select a day from 1 to 31.
2. Set the send time and the email address that should receive the run summary. The page defaults the email to the business owner's email address.
3. Save the schedule. Active schedules can be cancelled from the schedule list.

All schedule dates and times use the business timezone saved when the schedule is created. A monthly day that does not exist in a month (for example, day 31 in February) runs on that month's last day. A monthly schedule created after its chosen day/time has passed starts with the next month's occurrence.

Before **every scheduled run**, the system sends a new OTP to the authorized admin contacts and waits for confirmation on the Scheduled Due Reminders page. Codes expire after 15 minutes. If a code is not confirmed or cannot be delivered, that occurrence is skipped and no customer reminder is sent. Never share an OTP with anyone.

Each grouped run uses the **Payment reminder** WhatsApp template and sends at most one grouped message to each customer with outstanding invoices. The email report includes customers with balances, messages submitted/queued, failures, and any run note. “Submitted or queued” is not confirmation of final WhatsApp delivery. One-time schedules are deactivated after their run; monthly schedules remain active until cancelled.

### Server setup required

The deployment must apply the scheduler and OTP authorization migrations before using this page. Apply them through the normal backup and deployment process. The authorization migration creates the per-business scheduler switch and OTP challenge history; until it is applied, all scheduled sends must remain stopped.

If applying migrations manually, use the specific scheduler migration paths so unrelated pending migrations are not run accidentally:

```sh
php artisan migrate --path=database/migrations/2026_11_19_000001_create_payment_reminder_schedules_tables.php --force
php artisan migrate --path=database/migrations/2026_11_19_000002_create_payment_reminder_authorization_tables.php --force
```

Laravel's scheduler must be invoked once per minute by the server's cron, for example:

```cron
* * * * * cd /path/to/pos && php artisan schedule:run >> /dev/null 2>&1
```

The grouped scheduler is registered for `live` and `production` app environments. The legacy daily reminder keeps its existing schedule and template behavior, but it also waits for OTP confirmation before sending. Check the server cron and mail configuration during deployment; if a grouped-run email report cannot be sent, the run is logged and its report failure is recorded in the run history table.
