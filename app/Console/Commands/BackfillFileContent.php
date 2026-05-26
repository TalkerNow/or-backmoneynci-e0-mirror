<?php

namespace App\Console\Commands;

use App\Models\Files;
use Illuminate\Console\Command;

class BackfillFileContent extends Command
{
    protected $signature = 'files:backfill-content {--dry-run : Afficher sans modifier la base}';
    protected $description = 'Lit les fichiers encore sur disque et stocke leur contenu en DB (backfill post-migration BLOB)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $query = Files::whereNull('file_content');
        $total = $query->count();

        $this->info("Fichiers avec file_content NULL : {$total}");

        if ($dryRun) {
            $this->warn('[DRY-RUN] Aucune modification en base.');
        }

        $recovered = 0;
        $missing   = 0;
        $errors    = 0;

        $query->chunkById(100, function ($files) use ($dryRun, &$recovered, &$missing, &$errors) {
            foreach ($files as $file) {
                $candidates = [
                    public_path('img/' . $file->filename),
                ];
                if (!empty($file->dossier)) {
                    $candidates[] = public_path('img/' . $file->dossier . '/' . $file->filename);
                }
                if (!empty($file->user_id)) {
                    $candidates[] = public_path('img/' . $file->user_id . '/' . $file->filename);
                    if (!empty($file->dossier)) {
                        $candidates[] = public_path('img/' . $file->user_id . '/' . $file->dossier . '/' . $file->filename);
                    }
                }

                $found = null;
                foreach ($candidates as $path) {
                    if (is_readable($path)) {
                        $found = $path;
                        break;
                    }
                }

                if (!$found) {
                    $this->line("  MANQUANT  id={$file->id} {$file->filename}");
                    $missing++;
                    continue;
                }

                try {
                    $content  = file_get_contents($found);
                    $mimeType = @mime_content_type($found) ?: 'application/octet-stream';
                    $size     = strlen($content);

                    if (!$dryRun) {
                        $file->file_content = $content;
                        $file->mime_type    = $mimeType;
                        $file->file_size    = $size;
                        // Mettre à jour l'URL vers le nouveau endpoint DB si c'est une ancienne URL disque
                        if (empty($file->url) || !str_contains($file->url, '/api/downloadFile')) {
                            $file->url = url('/api/downloadFile?file_id=' . $file->id);
                        }
                        $file->save();
                    }

                    $this->line("  OK        id={$file->id} {$file->filename} ({$size} octets)");
                    $recovered++;
                } catch (\Exception $e) {
                    $this->error("  ERREUR    id={$file->id} {$file->filename} : " . $e->getMessage());
                    $errors++;
                }
            }
        });

        $this->newLine();
        $this->info("=== RÉSULTAT ===");
        $this->info("Récupérés  : {$recovered}");
        $this->warn("Manquants  : {$missing}");
        if ($errors) {
            $this->error("Erreurs    : {$errors}");
        }

        return 0;
    }
}
