<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_reminder_schedules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('schedule_type', 20);
            $table->date('scheduled_date')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('send_time');
            $table->string('timezone', 64);
            $table->string('report_email');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('payment_reminder_schedule_runs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('schedule_id');
            $table->string('run_key', 20);
            $table->dateTime('scheduled_for');
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('customer_count')->default(0);
            $table->unsignedInteger('submitted_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->longText('report_details')->nullable();
            $table->boolean('report_sent')->default(false);
            $table->text('report_error')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('schedule_id')->references('id')->on('payment_reminder_schedules')->onDelete('cascade');
            $table->unique(['schedule_id', 'run_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_reminder_schedule_runs');
        Schema::dropIfExists('payment_reminder_schedules');
    }
};
