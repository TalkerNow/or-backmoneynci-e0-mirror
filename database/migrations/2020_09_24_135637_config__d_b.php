<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConfigDB extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('personal_informations', function (Blueprint $table) {
            $table->id();
            $table->string('civility')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('maiden_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('martial_status')->nullable();
            $table->unsignedInteger('children_number')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('office_number')->nullable();
            $table->string('personal_address')->nullable();
            $table->string('personal_address_2')->nullable();
            $table->unsignedInteger('personal_zip_code')->nullable();
            $table->string('personal_city')->nullable();
            $table->string('personal_country')->nullable();
            $table->string('society_name')->nullable();
            $table->string('society_address')->nullable();
            $table->string('society_address_2')->nullable();
            $table->unsignedInteger('society_zip_code')->nullable();
            $table->string('society_city')->nullable();
            $table->string('society_country')->nullable();
            $table->integer('user_id');
            $table->timestamps();
        });

        Schema::create('user_funds', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('risk');
            $table->integer('user_id');
            $table->timestamps();
        });

        Schema::create('deposits_withdraws', function (Blueprint $table) {
            $table->id();
            $table->integer('amount');
            $table->unsignedInteger('risk');
            $table->string('deposit_state');
            $table->timestamp('date');
            $table->integer('user_id');
            $table->timestamps();
        });

        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vl');
            $table->date('date');
            $table->unsignedInteger('risk');
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('link_to_documents');
            $table->string('type');
            $table->string('document_state');
            $table->string('comment');
            $table->unsignedInteger('advanced_payment');
            $table->integer('user_id');
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->string('name');
            $table->string('description');
            $table->string('variable');
            $table->float('value');
            $table->string('variable1');
            $table->float('value1');
            $table->float('total_ht');
            $table->float('total_ttc');
            $table->float('tva');
            $table->integer('document_id');
            $table->integer('parent_id');
            $table->string('status');
            $table->id();
            $table->timestamps();
        });

        Schema::create('document_managers', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('document_id');
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
        Schema::drop('personal_informations');
        Schema::drop('user_funds');
        Schema::drop('deposits_withdraws');
        Schema::drop('funds');
        Schema::drop('documents');
        Schema::drop('services');
        Schema::drop('document_managers');
    }
}
