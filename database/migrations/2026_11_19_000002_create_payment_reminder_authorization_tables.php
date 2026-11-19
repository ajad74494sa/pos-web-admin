<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_reminder_scheduler_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });

        Schema::create('payment_reminder_otp_challenges', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('schedule_run_id')->nullable()->unique();
            $table->string('purpose', 32);
            $table->string('reference_key', 80);
            $table->string('code_hash');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('delivered_via', 20)->nullable();
            $table->text('delivery_error')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('schedule_run_id')->references('id')->on('payment_reminder_schedule_runs')->onDelete('cascade');
            $table->unique(['business_id', 'purpose', 'reference_key'], 'reminder_otp_business_purpose_ref_unique');
            $table->index(['business_id', 'status', 'expires_at'], 'reminder_otp_business_status_expiry_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_reminder_otp_challenges');
        Schema::dropIfExists('payment_reminder_scheduler_settings');
    }
};
