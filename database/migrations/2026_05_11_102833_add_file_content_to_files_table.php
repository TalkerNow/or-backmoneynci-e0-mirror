<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFileContentToFilesTable extends Migration
{
    public function up()
    {
        Schema::table('files', function (Blueprint $table) {
            // Contenu binaire du fichier (LONGBLOB via binary())
            $table->binary('file_content')->nullable()->after('url');
            // Type MIME pour le téléchargement
            $table->string('mime_type', 100)->nullable()->after('file_content');
            // Taille en octets
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });
    }

    public function down()
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn(['file_content', 'mime_type', 'file_size']);
        });
    }
}
