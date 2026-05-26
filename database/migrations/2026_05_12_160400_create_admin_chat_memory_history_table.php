<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminChatMemoryHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('admin_chat_memory_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_id');
            $table->longText('content');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('memory_id')->references('id')->on('admin_chat_memory')->onDelete('cascade');
            $table->index('memory_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_chat_memory_history');
    }
}
