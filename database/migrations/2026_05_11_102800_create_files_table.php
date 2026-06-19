<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table `files` (manquante du jeu de migrations : le commit e7f488d a ajouté
 * l'ALTER add_file_content_to_files_table sans la migration de création).
 *
 * Idempotent : ne crée la table que si elle n'existe pas déjà. En prod, `files`
 * préexiste => no-op. En local (migrate:fresh), elle est créée ici, avant l'ALTER
 * 2026_05_11_102833 qui ajoute file_content / mime_type / file_size.
 *
 * Colonnes dérivées de App\Models\Files::$fillable (hors colonnes ajoutées par l'ALTER).
 */
class CreateFilesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('files')) {
            return;
        }

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('filename')->nullable();
            $table->string('url')->nullable();
            $table->string('dossier')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('files');
    }
}
