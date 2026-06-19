<?php

namespace App\Services\Registre;

/** Levée quand une condition contient une construction non autorisée. */
class UnsafeExpressionException extends \RuntimeException {}

/** Levée quand une variable référencée est absente du namespace. */
class MissingVariableException extends \RuntimeException {}

/**
 * Évaluateur d'expressions SÛR pour l'enforcement Gate #2 (côté Laravel).
 *
 * N'utilise JAMAIS eval()/create_function. Tokenizer + parseur en descente
 * récursive sur une grammaire restreinte : littéraux, variables, indexation,
 * and/or/not, comparaisons, + - * / %, et un whitelist de fonctions
 * (min/max/abs/round/len). Tout le reste (appels de fonctions PHP, accès
 * propriétés, etc.) est rejeté (UnsafeExpressionException).
 *
 * Mirroir des sémantiques validées côté Python (skill_validation_autocontrole).
 */
class RuleEvaluator
{
    private const FUNCS = ['min', 'max', 'abs', 'round', 'len'];

    private array $tokens = [];
    private int $pos = 0;

    /**
     * @param array<int,array{code:string,condition:string,message:string,niveau:string}> $rules
     * @param array<string,mixed> $namespace
     * @return array{alertes:array,arret_critique:?array,skipped:array}
     */
    public function evaluate(array $rules, array $namespace): array
    {
        $alertes = [];
        $critiqueCodes = [];
        $skipped = [];

        foreach ($rules as $rule) {
            $code = (string) ($rule['code'] ?? '?');
            $condition = trim((string) ($rule['condition'] ?? ''));
            $isCritique = stripos((string) ($rule['niveau'] ?? ''), 'CRITIQUE') !== false;

            if ($condition === '' || str_starts_with($condition, '#')) {
                $skipped[] = ['code' => $code, 'reason' => 'condition vide ou placeholder'];
                continue;
            }

            try {
                $fired = $this->run($condition, $namespace);
            } catch (MissingVariableException $e) {
                $skipped[] = ['code' => $code, 'reason' => 'variable manquante: ' . $e->getMessage()];
                continue;
            } catch (UnsafeExpressionException $e) {
                $skipped[] = ['code' => $code, 'reason' => 'expression non autorisée: ' . $e->getMessage()];
                continue;
            } catch (\Throwable $e) {
                $skipped[] = ['code' => $code, 'reason' => 'erreur évaluation: ' . $e->getMessage()];
                continue;
            }

            if ($fired) {
                $alertes[] = [
                    'code'    => $code,
                    'message' => (string) ($rule['message'] ?? ''),
                    'niveau'  => $isCritique ? 'CRITIQUE' : 'AVERTISSEMENT',
                ];
                if ($isCritique) {
                    $critiqueCodes[] = $code;
                }
            }
        }

        $arret = null;
        if (!empty($critiqueCodes)) {
            $arret = [
                'raison' => 'Incohérences critiques détectées : ' . implode(', ', $critiqueCodes),
                'codes'  => $critiqueCodes,
            ];
        }

        return ['alertes' => $alertes, 'arret_critique' => $arret, 'skipped' => $skipped];
    }

    /** Évalue une condition booléenne. */
    private function run(string $expr, array $ns): bool
    {
        $this->tokens = $this->tokenize($expr);
        $this->pos = 0;
        $ast = $this->parseOr();
        if ($this->peek() !== null) {
            throw new UnsafeExpressionException('jeton inattendu en fin d\'expression');
        }
        return (bool) $this->eval($ast, $ns);
    }

    // ── Tokenizer ────────────────────────────────────────────────────────────

    private function tokenize(string $s): array
    {
        $tokens = [];
        $i = 0;
        $n = strlen($s);
        $keywords = ['and' => 'AND', 'or' => 'OR', 'not' => 'NOT'];
        $consts = ['true' => true, 'false' => false, 'none' => null, 'null' => null];

        while ($i < $n) {
            $c = $s[$i];

            if (ctype_space($c)) { $i++; continue; }

            // Strings
            if ($c === '"' || $c === "'") {
                $quote = $c;
                $i++;
                $buf = '';
                while ($i < $n && $s[$i] !== $quote) { $buf .= $s[$i]; $i++; }
                if ($i >= $n) throw new UnsafeExpressionException('chaîne non terminée');
                $i++; // closing quote
                $tokens[] = ['t' => 'STR', 'v' => $buf];
                continue;
            }

            // Numbers
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $n && ctype_digit($s[$i + 1]))) {
                $buf = '';
                while ($i < $n && (ctype_digit($s[$i]) || $s[$i] === '.')) { $buf .= $s[$i]; $i++; }
                $tokens[] = ['t' => 'NUM', 'v' => (str_contains($buf, '.') ? (float) $buf : (int) $buf)];
                continue;
            }

            // Identifiers / keywords
            if (ctype_alpha($c) || $c === '_') {
                $buf = '';
                while ($i < $n && (ctype_alnum($s[$i]) || $s[$i] === '_')) { $buf .= $s[$i]; $i++; }
                $low = strtolower($buf);
                if (isset($keywords[$low])) {
                    $tokens[] = ['t' => $keywords[$low], 'v' => $low];
                } elseif (array_key_exists($low, $consts)) {
                    $tokens[] = ['t' => 'CONST', 'v' => $consts[$low]];
                } else {
                    $tokens[] = ['t' => 'IDENT', 'v' => $buf];
                }
                continue;
            }

            // Two-char operators
            $two = substr($s, $i, 2);
            if (in_array($two, ['==', '!=', '<=', '>='], true)) {
                $tokens[] = ['t' => 'CMP', 'v' => $two];
                $i += 2;
                continue;
            }

            // Single-char
            switch ($c) {
                case '<': case '>':
                    $tokens[] = ['t' => 'CMP', 'v' => $c]; $i++; break;
                case '+': case '-': case '*': case '/': case '%':
                    $tokens[] = ['t' => 'AROP', 'v' => $c]; $i++; break;
                case '(': $tokens[] = ['t' => 'LP']; $i++; break;
                case ')': $tokens[] = ['t' => 'RP']; $i++; break;
                case '[': $tokens[] = ['t' => 'LB']; $i++; break;
                case ']': $tokens[] = ['t' => 'RB']; $i++; break;
                case ',': $tokens[] = ['t' => 'COMMA']; $i++; break;
                default:
                    throw new UnsafeExpressionException('caractère non autorisé: ' . $c);
            }
        }

        return $tokens;
    }

    private function peek(): ?array { return $this->tokens[$this->pos] ?? null; }

    private function next(): ?array { return $this->tokens[$this->pos++] ?? null; }

    private function expect(string $type): array
    {
        $tok = $this->next();
        if ($tok === null || $tok['t'] !== $type) {
            throw new UnsafeExpressionException('jeton attendu: ' . $type);
        }
        return $tok;
    }

    // ── Parser (précédence: or < and < not < comparaison < add < mul < unaire) ──

    private function parseOr(): array
    {
        $items = [$this->parseAnd()];
        while (($t = $this->peek()) && $t['t'] === 'OR') { $this->next(); $items[] = $this->parseAnd(); }
        return count($items) === 1 ? $items[0] : ['n' => 'or', 'items' => $items];
    }

    private function parseAnd(): array
    {
        $items = [$this->parseNot()];
        while (($t = $this->peek()) && $t['t'] === 'AND') { $this->next(); $items[] = $this->parseNot(); }
        return count($items) === 1 ? $items[0] : ['n' => 'and', 'items' => $items];
    }

    private function parseNot(): array
    {
        if (($t = $this->peek()) && $t['t'] === 'NOT') { $this->next(); return ['n' => 'not', 'e' => $this->parseNot()]; }
        return $this->parseComparison();
    }

    private function parseComparison(): array
    {
        $left = $this->parseAdd();
        $ops = [];
        while (($t = $this->peek()) && $t['t'] === 'CMP') {
            $this->next();
            $ops[] = ['op' => $t['v'], 'node' => $this->parseAdd()];
        }
        return empty($ops) ? $left : ['n' => 'cmp', 'left' => $left, 'ops' => $ops];
    }

    private function parseAdd(): array
    {
        $node = $this->parseMul();
        while (($t = $this->peek()) && $t['t'] === 'AROP' && in_array($t['v'], ['+', '-'], true)) {
            $this->next();
            $node = ['n' => 'bin', 'op' => $t['v'], 'l' => $node, 'r' => $this->parseMul()];
        }
        return $node;
    }

    private function parseMul(): array
    {
        $node = $this->parseUnary();
        while (($t = $this->peek()) && $t['t'] === 'AROP' && in_array($t['v'], ['*', '/', '%'], true)) {
            $this->next();
            $node = ['n' => 'bin', 'op' => $t['v'], 'l' => $node, 'r' => $this->parseUnary()];
        }
        return $node;
    }

    private function parseUnary(): array
    {
        if (($t = $this->peek()) && $t['t'] === 'AROP' && in_array($t['v'], ['-', '+'], true)) {
            $this->next();
            return ['n' => $t['v'] === '-' ? 'neg' : 'pos', 'e' => $this->parseUnary()];
        }
        return $this->parsePostfix();
    }

    private function parsePostfix(): array
    {
        $node = $this->parsePrimary();
        while (($t = $this->peek()) && $t['t'] === 'LB') {
            $this->next();
            $key = $this->parseOr();
            $this->expect('RB');
            $node = ['n' => 'index', 'c' => $node, 'k' => $key];
        }
        return $node;
    }

    private function parsePrimary(): array
    {
        $t = $this->next();
        if ($t === null) throw new UnsafeExpressionException('expression incomplète');

        switch ($t['t']) {
            case 'NUM': return ['n' => 'lit', 'v' => $t['v']];
            case 'STR': return ['n' => 'lit', 'v' => $t['v']];
            case 'CONST': return ['n' => 'lit', 'v' => $t['v']];
            case 'LP':
                $e = $this->parseOr();
                $this->expect('RP');
                return $e;
            case 'LB':
                $items = [];
                if (($p = $this->peek()) && $p['t'] !== 'RB') {
                    $items[] = $this->parseOr();
                    while (($p = $this->peek()) && $p['t'] === 'COMMA') { $this->next(); $items[] = $this->parseOr(); }
                }
                $this->expect('RB');
                return ['n' => 'list', 'items' => $items];
            case 'IDENT':
                if (($p = $this->peek()) && $p['t'] === 'LP') {
                    // appel de fonction (whitelist)
                    $name = strtolower($t['v']);
                    if (!in_array($name, self::FUNCS, true)) {
                        throw new UnsafeExpressionException('fonction non autorisée: ' . $t['v']);
                    }
                    $this->next(); // consume LP
                    $args = [];
                    if (($p = $this->peek()) && $p['t'] !== 'RP') {
                        $args[] = $this->parseOr();
                        while (($p = $this->peek()) && $p['t'] === 'COMMA') { $this->next(); $args[] = $this->parseOr(); }
                    }
                    $this->expect('RP');
                    return ['n' => 'call', 'name' => $name, 'args' => $args];
                }
                return ['n' => 'var', 'name' => $t['v']];
            default:
                throw new UnsafeExpressionException('jeton inattendu');
        }
    }

    // ── Évaluateur ─────────────────────────────────────────────────────────────

    private function eval(array $node, array $ns)
    {
        switch ($node['n']) {
            case 'lit':
                return $node['v'];

            case 'var':
                if (!array_key_exists($node['name'], $ns)) {
                    throw new MissingVariableException($node['name']);
                }
                return $ns[$node['name']];

            case 'list':
                return array_map(fn ($e) => $this->eval($e, $ns), $node['items']);

            case 'or':
                $val = false;
                foreach ($node['items'] as $it) { $val = $this->eval($it, $ns); if ($val) return $val; }
                return $val;

            case 'and':
                $val = true;
                foreach ($node['items'] as $it) { $val = $this->eval($it, $ns); if (!$val) return $val; }
                return $val;

            case 'not':
                return !$this->eval($node['e'], $ns);

            case 'neg':
                return -$this->eval($node['e'], $ns);

            case 'pos':
                return +$this->eval($node['e'], $ns);

            case 'bin':
                $l = $this->eval($node['l'], $ns);
                $r = $this->eval($node['r'], $ns);
                return match ($node['op']) {
                    '+' => $l + $r,
                    '-' => $l - $r,
                    '*' => $l * $r,
                    '/' => $r == 0 ? throw new \RuntimeException('division par zéro') : $l / $r,
                    '%' => $r == 0 ? throw new \RuntimeException('modulo par zéro') : $l % $r,
                    default => throw new UnsafeExpressionException('opérateur: ' . $node['op']),
                };

            case 'cmp':
                $left = $this->eval($node['left'], $ns);
                foreach ($node['ops'] as $cmp) {
                    $right = $this->eval($cmp['node'], $ns);
                    if (!$this->compare($left, $cmp['op'], $right)) return false;
                    $left = $right;
                }
                return true;

            case 'index':
                $c = $this->eval($node['c'], $ns);
                $k = $this->eval($node['k'], $ns);
                if (is_array($c)) return $c[$k] ?? null;
                throw new \RuntimeException('indexation invalide');

            case 'call':
                $args = array_map(fn ($a) => $this->eval($a, $ns), $node['args']);
                return $this->callFunc($node['name'], $args);

            default:
                throw new UnsafeExpressionException('nœud inconnu');
        }
    }

    private function compare($l, string $op, $r): bool
    {
        return match ($op) {
            '==' => $l == $r,
            '!=' => $l != $r,
            '<'  => $l < $r,
            '<=' => $l <= $r,
            '>'  => $l > $r,
            '>=' => $l >= $r,
            default => throw new UnsafeExpressionException('comparateur: ' . $op),
        };
    }

    private function callFunc(string $name, array $args)
    {
        switch ($name) {
            case 'min':
            case 'max':
                if (count($args) === 1 && is_array($args[0])) $args = $args[0];
                if (empty($args)) throw new \RuntimeException("$name() sans argument");
                return $name === 'min' ? min($args) : max($args);
            case 'abs':
                return abs($args[0]);
            case 'round':
                return round($args[0], (int) ($args[1] ?? 0));
            case 'len':
                $a = $args[0];
                return is_array($a) ? count($a) : strlen((string) $a);
            default:
                throw new UnsafeExpressionException('fonction: ' . $name);
        }
    }
}
