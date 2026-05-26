<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ChangeFileContentToLongblob extends Migration
{
    public function up()
    {
        // binary() dans Laravel crée un BLOB (65 Ko max).
        // On force LONGBLOB (4 Go max) pour supporter les PDF/DOCX.
        DB::statement('ALTER TABLE files MODIFY file_content LONGBLOB NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE files MODIFY file_content BLOB NULL');
    }
}
