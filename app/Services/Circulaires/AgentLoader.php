<?php

namespace App\Services\Circulaires;

class AgentLoader
{
    public const AGENTS_DIR = 'circulaires/agents';
    public const CIRCULAIRES_DIR = 'circulaires/circulaires';

    /**
     * Returns indexed-by-slug agents:
     *   [
     *     'vplr' => ['slug', 'name', 'circulaire', 'triggers_js', 'llm_hint', 'output', 'body'],
     *     ...
     *   ]
     */
    public function loadAll(): array
    {
        $dir = resource_path(self::AGENTS_DIR);
        $files = glob($dir . '/*.md') ?: [];
        $latestMtime = 0;
        foreach ($files as $f) {
            $latestMtime = max($latestMtime, filemtime($f) ?: 0);
        }

        return cache()->remember('circulaires.agents.loader.' . $latestMtime, 300, function () use ($files) {
            $agents = [];
            foreach ($files as $file) {
                $parsed = $this->parseAgentFile($file);
                if ($parsed && !empty($parsed['slug'])) {
                    $agents[$parsed['slug']] = $parsed;
                }
            }
            return $agents;
        });
    }

    public function loadOne(string $slug): ?array
    {
        $all = $this->loadAll();
        return $all[$slug] ?? null;
    }

    public function loadCirculaireBody(string $filename): ?string
    {
        $path = resource_path(self::CIRCULAIRES_DIR . '/' . $filename);
        return is_file($path) ? (file_get_contents($path) ?: null) : null;
    }

    private function parseAgentFile(string $path): ?array
    {
        $raw = file_get_contents($path);
        if ($raw === false) return null;

        if (!preg_match('/^---\s*\R(.*?)\R---\s*\R(.*)$/s', $raw, $m)) {
            return null;
        }
        $meta = $this->parseSimpleYaml($m[1]);
        $body = trim($m[2]);

        return array_merge($meta, ['body' => $body]);
    }

    private function parseSimpleYaml(string $yaml): array
    {
        $lines = preg_split('/\R/', $yaml);
        $out = [];
        $stack = [&$out];
        $stackIndent = [-1];

        foreach ($lines as $line) {
            if (trim($line) === '' || preg_match('/^\s*#/', $line)) continue;
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            $trim = ltrim($line, ' ');

            while (count($stackIndent) > 1 && $indent <= end($stackIndent)) {
                array_pop($stack);
                array_pop($stackIndent);
            }
            $cur = &$stack[count($stack) - 1];

            if (preg_match('/^-\s+(.*)$/', $trim, $lm)) {
                if (!is_array($cur)) $cur = [];
                $cur[] = $this->castScalar($lm[1]);
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_\-]+)\s*:\s*(.*)$/', $trim, $km)) {
                $k = $km[1];
                $v = trim($km[2]);
                if ($v === '' || $v === null) {
                    $cur[$k] = [];
                    $stack[] = &$cur[$k];
                    $stackIndent[] = $indent;
                } else {
                    $cur[$k] = $this->castScalar($v);
                }
            }
            unset($cur);
        }
        return $out;
    }

    private function castScalar(string $v)
    {
        $v = trim($v);
        if ($v === 'true') return true;
        if ($v === 'false') return false;
        if ($v === 'null' || $v === '~') return null;
        if (preg_match('/^-?\d+$/', $v)) return (int)$v;
        if (preg_match('/^-?\d*\.\d+$/', $v)) return (float)$v;
        if (preg_match('/^"(.*)"$/', $v, $m)) return stripcslashes($m[1]);
        if (preg_match("/^'(.*)'$/", $v, $m)) return $m[1];
        return $v;
    }
}
