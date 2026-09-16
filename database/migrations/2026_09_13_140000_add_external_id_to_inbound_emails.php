<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CF7 webhook tip: clé upsert indépendante de Gmail.
 * - external_id UNIQUE nullable (cf7-…)
 * - gmail_message_id devient nullable (reste UNIQUE) pour POST webhook-only
 */
class AddExternalIdToInboundEmails extends Migration
{
    public function up()
    {
        Schema::table("inbound_emails", function (Blueprint $table) {
            if (!Schema::hasColumn("inbound_emails", "external_id")) {
                $table->string("external_id", 255)->nullable()->unique()->after("id");
            }
        });

        // gmail_message_id: NOT NULL → NULL, conserve UNIQUE
        $col = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \"inbound_emails\"
               AND COLUMN_NAME = \"gmail_message_id\""
        );
        if ($col && strtoupper((string) $col->IS_NULLABLE) === "NO") {
            DB::statement("ALTER TABLE inbound_emails MODIFY gmail_message_id VARCHAR(255) NULL");
        }
    }

    public function down()
    {
        Schema::table("inbound_emails", function (Blueprint $table) {
            if (Schema::hasColumn("inbound_emails", "external_id")) {
                $table->dropUnique(["external_id"]);
                $table->dropColumn("external_id");
            }
        });

        // Remet NOT NULL seulement si aucune ligne NULL (évite échec down en TEST)
        $nulls = (int) DB::table("inbound_emails")->whereNull("gmail_message_id")->count();
        if ($nulls === 0) {
            DB::statement("ALTER TABLE inbound_emails MODIFY gmail_message_id VARCHAR(255) NOT NULL");
        }
    }
}
