<?php

namespace App\Services;

/**
 * Parse CF7 contact form body/snippet into identity fields.
 * Mirrors front parseCf7ContactBody (inbox/utils.js) — TEST Lot 1.
 */
class Cf7ContactParser
{
    public static function parse(?string $text): array
    {
        $empty = [
            "first_name" => "",
            "last_name" => "",
            "name" => "",
            "phone" => "",
            "email" => "",
            "birth_date" => "",
            "interest" => "",
            "message" => "",
            "civility" => "",
        ];
        $raw = preg_replace("/\s+/u", " ", trim((string) $text));
        if ($raw === "") {
            return $empty;
        }

        $nextLabel = "(?=\\s*(?:Téléphone|Telephone|Adresse\\s*(?:e-?mail|mail)|E-?mail|Date\\s*de\\s*naissance|Statut|Vous\\s*êtes|Besoin|Message|Votre\\s*message|Formulaire\\s+rempli|Title|Name|phone|email)\\b|$)";

        $firstName = "";
        $lastName = "";
        $name = "";
        $civility = "";

        if (preg_match("/(?:Prénom|Prenom)\\s*,\\s*Nom\\s+(.+?)" . $nextLabel . "/iu", $raw, $m)) {
            $name = trim($m[1]);
            $parts = preg_split("/\s+/", $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($parts && preg_match("/^(m\\.?|mr\\.?|mme\\.?|mlle\\.?|monsieur|madame)$/iu", $parts[0])) {
                $civility = self::normalizeCivility(array_shift($parts));
            }
            if (count($parts) === 1) {
                $firstName = $parts[0];
            } elseif (count($parts) > 1) {
                $firstName = $parts[0];
                $lastName = implode(" ", array_slice($parts, 1));
            }
        } elseif (preg_match("/Nom\\s*,\\s*(?:Prénom|Prenom)\\s+(.+?)" . $nextLabel . "/iu", $raw, $m)) {
            $name = trim($m[1]);
            $parts = preg_split("/\s+/", $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($parts && preg_match("/^(m\\.?|mr\\.?|mme\\.?|mlle\\.?|monsieur|madame)$/iu", $parts[0])) {
                $civility = self::normalizeCivility(array_shift($parts));
            }
            if (count($parts) === 1) {
                $lastName = $parts[0];
            } elseif (count($parts) === 2) {
                $firstName = $parts[0];
                $lastName = $parts[1];
            } elseif (count($parts) > 2) {
                $firstName = implode(" ", array_slice($parts, 0, -1));
                $lastName = $parts[count($parts) - 1];
            }
        } elseif (preg_match("/(?:^|\\n)\\s*Name\\s*:\\s*(.+?)(?=\\s*(?:phone|email|Message|Title)\\b|$)/iu", $raw, $m)
            || preg_match("/\\bName\\s*:\\s*(.+?)(?=\\s*(?:phone|email|Message|Title)\\b|$)/iu", $raw, $m)) {
            // Smoke / simple CF7 webhook layout
            $name = trim($m[1]);
            $parts = preg_split("/\s+/", $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) === 1) {
                $firstName = $parts[0];
            } elseif (count($parts) > 1) {
                $firstName = $parts[0];
                $lastName = implode(" ", array_slice($parts, 1));
            }
        }

        if (preg_match("/\\bTitle\\s*:\\s*(Mr|Mme|Mlle|M\\.?|Madame|Monsieur)\\b/iu", $raw, $m)) {
            $civility = self::normalizeCivility($m[1]);
        }

        $phone = "";
        if (preg_match("/(?:Téléphone|Telephone|phone)\\s*[:\\s]\\s*([+0-9][0-9.\\s\\/-]{6,})" . $nextLabel . "/iu", $raw, $m)) {
            $phone = preg_replace("/[.\\s\\/-]/", "", trim($m[1]));
        }

        $email = "";
        if (preg_match("/(?:Adresse\\s*(?:e-?mail|mail)|E-?mail|email)\\s*[:\\s]\\s*([^\\s]+@[^\\s]+)/iu", $raw, $m)) {
            $email = rtrim(trim($m[1]), ">,;");
        }

        $birthDate = "";
        if (preg_match("/Date\\s*de\\s*naissance\\s*[:\\s]\\s*([0-9]{1,2}[\\/.\\-][0-9]{1,2}[\\/.\\-][0-9]{2,4})" . $nextLabel . "/iu", $raw, $m)) {
            $birthDate = self::normalizeBirthDate(trim($m[1]));
        }

        $message = "";
        if (preg_match("/(?:Votre\\s+message|\\bMessage(?!\\s+reçu)\\b)\\s+(.+?)(?=\\s*Formulaire\\s+rempli\\s+sur|\\s*$)/iu", $raw, $m)) {
            $message = trim($m[1]);
        }
        if ($message === "" && preg_match("/\\bMessage\\s*:\\s*(.+)$/iu", $raw, $m)) {
            $message = trim($m[1]);
        }
        if ($message === "" && preg_match("/Besoin\\s+(.+?)(?=\\s*Formulaire\\s+rempli\\s+sur|\\s*$)/iu", $raw, $m)) {
            $message = trim($m[1]);
        }

        if ($name === "") {
            $name = trim($firstName . " " . $lastName);
        }

        return [
            "first_name" => $firstName,
            "last_name" => $lastName,
            "name" => $name,
            "phone" => $phone,
            "email" => $email,
            "birth_date" => $birthDate,
            "interest" => "",
            "message" => $message,
            "civility" => $civility,
        ];
    }

    private static function normalizeCivility(string $raw): string
    {
        $t = strtolower(trim($raw));
        if (in_array($t, ["mme", "madame"], true)) {
            return "Madame";
        }
        if (in_array($t, ["mlle", "mademoiselle"], true)) {
            return "Mlle";
        }
        return "Monsieur";
    }

    private static function normalizeBirthDate(string $raw): string
    {
        if (preg_match("/^(\\d{1,2})[\\/.\\-](\\d{1,2})[\\/.\\-](\\d{2,4})$/", $raw, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 1900;
            }
            return sprintf("%04d-%02d-%02d", $y, $mo, $d);
        }
        return "";
    }
}
