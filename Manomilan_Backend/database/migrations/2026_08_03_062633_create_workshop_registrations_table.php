<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkshopRegistrationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workshop_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('full_name');
            $table->string('mobile_number',15);
            $table->string('whatsapp_number',15);
            $table->date('date_of_birth');
            $table->string('education');
            $table->string('city');
            $table->string('district');
            $table->enum('marital_status',[
                'Unmarried',
                'Divorced',
                'Widow',
                'Widower'
            ]);
            $table->string('parent_name');
            $table->string('parent_contact_number',15);
            $table->string('payment_screenshot');
            $table->string('transaction_id');
            $table->boolean('consent')->default(0);
            $table->enum('status',[
                'Pending',
                'Verified',
                'Rejected'
            ])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workshop_registrations');
    }
}
