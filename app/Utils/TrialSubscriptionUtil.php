<?php

namespace App\Utils;

use App\Business;
use App\System;
use App\User;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Notifications\NewSubscriptionNotification;
use Notification;

class TrialSubscriptionUtil
{
    public function createForBusiness(Business $business, User $user, Package $package): Subscription
    {
        $startDate = Subscription::end_date($business->id)->copy();
        $trialEndDate = $startDate->copy()->addDays(max(0, (int) $package->trial_days - 1));

        $packageDetails = [
            'location_count' => $package->location_count,
            'user_count' => $package->user_count,
            'product_count' => $package->product_count,
            'invoice_count' => $package->invoice_count,
            'name' => $package->name,
        ];

        if (! empty($package->custom_permissions)) {
            foreach ($package->custom_permissions as $name => $value) {
                $packageDetails[$name] = $value;
            }
        }

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'package_id' => $package->id,
            'start_date' => $startDate->toDateString(),
            'trial_end_date' => $trialEndDate->toDateString(),
            'end_date' => $trialEndDate->toDateString(),
            'package_price' => 0,
            'package_details' => $packageDetails,
            'created_id' => $user->id,
            'paid_via' => 'trial',
            'status' => 'approved',
        ]);

        $email = System::getProperty('email');
        if (! empty($email) && System::getProperty('enable_new_subscription_notification') == 1) {
            Notification::route('mail', $email)
                ->notify(new NewSubscriptionNotification($subscription));
        }

        return $subscription;
    }
}
