<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeSnapshotSessionMessageNullable extends Migration
{
    public function up()
    {
        // Drop foreign keys first
        DB::statement('ALTER TABLE admin_chat_snapshots DROP FOREIGN KEY admin_chat_snapshots_session_id_foreign');
        DB::statement('ALTER TABLE admin_chat_snapshots DROP FOREIGN KEY admin_chat_snapshots_message_id_foreign');

        // Make columns nullable via raw ALTER
        DB::statement('ALTER TABLE admin_chat_snapshots MODIFY session_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE admin_chat_snapshots MODIFY message_id BIGINT UNSIGNED NULL');

        // Re-add foreign keys
        DB::statement('ALTER TABLE admin_chat_snapshots ADD CONSTRAINT admin_chat_snapshots_session_id_foreign FOREIGN KEY (session_id) REFERENCES admin_chat_sessions(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE admin_chat_snapshots ADD CONSTRAINT admin_chat_snapshots_message_id_foreign FOREIGN KEY (message_id) REFERENCES admin_chat_messages(id) ON DELETE CASCADE');
    }

    public function down()
    {
        DB::statement('ALTER TABLE admin_chat_snapshots DROP FOREIGN KEY admin_chat_snapshots_session_id_foreign');
        DB::statement('ALTER TABLE admin_chat_snapshots DROP FOREIGN KEY admin_chat_snapshots_message_id_foreign');

        DB::statement('ALTER TABLE admin_chat_snapshots MODIFY session_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE admin_chat_snapshots MODIFY message_id BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE admin_chat_snapshots ADD CONSTRAINT admin_chat_snapshots_session_id_foreign FOREIGN KEY (session_id) REFERENCES admin_chat_sessions(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE admin_chat_snapshots ADD CONSTRAINT admin_chat_snapshots_message_id_foreign FOREIGN KEY (message_id) REFERENCES admin_chat_messages(id) ON DELETE CASCADE');
    }
}
