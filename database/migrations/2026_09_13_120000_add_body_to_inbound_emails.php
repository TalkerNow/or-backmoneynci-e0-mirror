<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBodyToInboundEmails extends Migration
{
    public function up()
    {
        Schema::table('inbound_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_emails', 'body')) {
                $table->longText('body')->nullable()->after('snippet');
            }
        });
    }
    public function down()
    {
        Schema::table('inbound_emails', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_emails', 'body')) {
                $table->dropColumn('body');
            }
        });
    }
}
