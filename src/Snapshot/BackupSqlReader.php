<?php

/**
 * Reads the SQL files OpenEMR's backup.php page wrote for layouts and lists (removed in
 * openemr/openemr#13471, August 2026), as data. The file is never executed. backup.php ran
 * mysqldump with --skip-opt --complete-insert --no-create-info --hex-blob, so each row is an
 * INSERT with its column names, preceded by DELETEs of the same layout or list, and for DEM and
 * HIS layouts followed by a prepared ALTER TABLE adding missing columns.
 *
 * Accepted, strictly: INSERTs into layout_group_properties, layout_options and list_options;
 * the DELETEs backup.php wrote for them; comments and SET statements mysqldump adds; and
 * backup.php's column repair, which is recognised and skipped (the compare reports missing
 * columns itself). Anything else refuses the whole file, since it isn't one backup.php wrote.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Snapshot;

final class BackupSqlReader
{
    private const IDENT = '`?([A-Za-z0-9_]+)`?';

    public static function read(string $sql): Snapshot
    {
        $rows = ['layout_group_properties' => [], 'layout_options' => [], 'list_options' => []];
        $schema = [];
        $layoutIds = [];
        $listIds = [];
        foreach (self::statements($sql) as $n => $statement) {
            $s = trim($statement);
            if ($s === '' || preg_match('/^SET\s/i', $s) === 1) {
                continue;
            }
            if (preg_match('/^(LOCK TABLES\s+' . self::IDENT . '\s+WRITE|UNLOCK TABLES)$/i', $s) === 1) {
                continue;
            }
            // Transaction control: MariaDB 12's mariadb-dump wraps each table's rows in SET @@AUTOCOMMIT=0 ... COMMIT.
            if (preg_match('/^(COMMIT|BEGIN|START TRANSACTION)$/i', $s) === 1) {
                continue;
            }
            // backup.php's repair for DEM and HIS layouts: build ALTER TABLE ... ADD for missing columns, then run it.
            if (preg_match("/^SELECT CONCAT\\(\\s*'ALTER TABLE (patient_data|history_data) '.*INTO @sql$/is", $s) === 1
                || preg_match('/^PREPARE stmt FROM @sql$/i', $s) === 1 || preg_match('/^EXECUTE stmt$/i', $s) === 1) {
                continue;
            }
            if (preg_match('/^DELETE FROM ' . self::IDENT . '\s+WHERE\s+(.+)$/is', $s, $m) === 1) {
                self::delete($m[1], $m[2], $n + 1, $layoutIds, $listIds);
                continue;
            }
            if (preg_match('/^INSERT INTO ' . self::IDENT . '\s*\((.*?)\)\s*VALUES\s*(.+)$/is', $s, $m) === 1) {
                $table = $m[1];
                if (!isset($rows[$table])) {
                    throw new FileError(sprintf('Statement %d inserts into %s, which backup.php never exported with layouts and lists; the file was not read.', $n + 1, $table));
                }
                $columns = array_map(static fn(string $c): string => trim($c, " \t\n\r`"), explode(',', $m[2]));
                $schema[$table] ??= $columns;
                foreach (self::tuples($m[3], $n + 1) as $values) {
                    if (count($values) !== count($columns)) {
                        throw new FileError(sprintf('Statement %d has %d columns but %d values; the file was not read.', $n + 1, count($columns), count($values)));
                    }
                    $rows[$table][] = array_combine($columns, $values);
                }
                continue;
            }
            throw new FileError(sprintf('Statement %d is not one backup.php wrote for layouts and lists (%s); the file was not read.', $n + 1, mb_substr($s, 0, 60)));
        }
        $layouts = [];
        foreach ($rows['layout_group_properties'] as $r) {
            $layouts[(string)$r['grp_form_id']]['properties'][] = $r;
        }
        foreach ($rows['layout_options'] as $r) {
            $layouts[(string)$r['form_id']]['fields'][] = $r;
        }
        foreach ($layoutIds as $id => $_) {
            $layouts[$id] ??= [];
        }
        $lists = [];
        foreach ($rows['list_options'] as $r) {
            if ($r['list_id'] === 'lists') {
                $lists[(string)$r['option_id']]['list'] = $r;
            } else {
                $lists[(string)$r['list_id']]['options'][] = $r;
            }
        }
        foreach ($listIds as $id => $_) {
            $lists[$id] ??= [];
        }
        $layouts = array_map(static fn(array $l): array => ['properties' => $l['properties'] ?? [], 'fields' => $l['fields'] ?? []], $layouts);
        $lists = array_map(static fn(array $l): array => ['list' => $l['list'] ?? null, 'options' => $l['options'] ?? []], $lists);
        return (new Snapshot($layouts, $lists, $schema))->normalized();
    }

    /**
     * @param array<string, true> $layoutIds
     * @param array<string, true> $listIds
     */
    private static function delete(string $table, string $where, int $n, array &$layoutIds, array &$listIds): void
    {
        $q = '["\']([^"\']*)["\']';
        $ok = match ($table) {
            'list_options' => preg_match('/^list_id\s*=\s*' . $q . '(\s+AND\s+option_id\s*=\s*' . $q . ')?$/i', trim($where), $m) === 1,
            'layout_options' => preg_match('/^form_id\s*=\s*' . $q . '$/i', trim($where), $m) === 1,
            'layout_group_properties' => preg_match('/^grp_form_id\s*=\s*' . $q . '$/i', trim($where), $m) === 1,
            default => false,
        };
        if (!$ok) {
            throw new FileError(sprintf('Statement %d deletes from %s in a way backup.php never did; the file was not read.', $n, $table));
        }
        if ($table === 'list_options') {
            $listIds[isset($m[3]) && $m[1] === 'lists' ? $m[3] : $m[1]] = true;
        } else {
            $layoutIds[$m[1]] = true;
        }
    }

    /**
     * The file's statements, split on semicolons outside quotes and backticks.
     *
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $out = [];
        $current = '';
        $quote = null;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $current .= $c;
                if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $current .= $sql[++$i];
                } elseif ($c === $quote) {
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                        $current .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $current .= $c;
            } elseif ($c === '/' && ($sql[$i + 1] ?? '') === '*') {
                // Comments, including mysqldump's /*!40101 SET ... */ and MariaDB's /*M!999999 ... */, are skipped.
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new FileError('The file ends inside a comment; it was not read.');
                }
                $current .= ' ';
                $i = $end + 1;
            } elseif ($c === '-' && substr($sql, $i, 3) === '-- ' && trim($current) === '') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
            } elseif ($c === ';') {
                $out[] = $current;
                $current = '';
            } else {
                $current .= $c;
            }
        }
        if ($quote !== null) {
            throw new FileError('The file ends inside a quoted value; it was not read.');
        }
        if (trim($current) !== '') {
            $out[] = $current;
        }
        return $out;
    }

    /**
     * The value tuples after VALUES: (…),(…). Strings with MySQL's escapes, NULL, numbers and 0x hex.
     *
     * @return list<list<?string>>
     */
    private static function tuples(string $s, int $n): array
    {
        $tuples = [];
        $i = 0;
        $len = strlen($s);
        $skip = static function () use ($s, &$i, $len): void {
            while ($i < $len && ctype_space($s[$i])) {
                $i++;
            }
        };
        while (true) {
            $skip();
            if ($i >= $len || $s[$i] !== '(') {
                throw new FileError(sprintf('Statement %d has values that are not a list in parentheses; the file was not read.', $n));
            }
            $i++;
            $values = [];
            while (true) {
                $skip();
                $values[] = self::value($s, $i, $n);
                $skip();
                if ($i < $len && $s[$i] === ',') {
                    $i++;
                    continue;
                }
                if ($i < $len && $s[$i] === ')') {
                    $i++;
                    break;
                }
                throw new FileError(sprintf('Statement %d has a value list that does not close; the file was not read.', $n));
            }
            $tuples[] = $values;
            $skip();
            if ($i < $len && $s[$i] === ',') {
                $i++;
                continue;
            }
            if ($i >= $len) {
                return $tuples;
            }
            throw new FileError(sprintf('Statement %d has something after its values; the file was not read.', $n));
        }
    }

    private static function value(string $s, int &$i, int $n): ?string
    {
        $rest = substr($s, $i);
        if (preg_match('/^NULL\b/i', $rest) === 1) {
            $i += 4;
            return null;
        }
        if (preg_match('/^0x([0-9A-Fa-f]*)/', $rest, $m) === 1) {
            $i += strlen($m[0]);
            return (string)hex2bin(strlen($m[1]) % 2 === 1 ? '0' . $m[1] : $m[1]);
        }
        if (preg_match('/^-?\d+(\.\d+)?([eE][-+]?\d+)?/', $rest, $m) === 1) {
            $i += strlen($m[0]);
            return $m[0];
        }
        if ($rest !== '' && ($rest[0] === "'" || $rest[0] === '"')) {
            $quote = $rest[0];
            $out = '';
            $i++;
            $len = strlen($s);
            $escapes = ['0' => "\0", "'" => "'", '"' => '"', 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1A", '\\' => '\\', '%' => '\\%', '_' => '\\_'];
            while ($i < $len) {
                $c = $s[$i];
                if ($c === '\\' && $i + 1 < $len) {
                    $next = $s[$i + 1];
                    $out .= $escapes[$next] ?? $next;
                    $i += 2;
                    continue;
                }
                if ($c === $quote) {
                    if ($i + 1 < $len && $s[$i + 1] === $quote) {
                        $out .= $quote;
                        $i += 2;
                        continue;
                    }
                    $i++;
                    return $out;
                }
                $out .= $c;
                $i++;
            }
            throw new FileError(sprintf('Statement %d has a quoted value that does not end; the file was not read.', $n));
        }
        throw new FileError(sprintf('Statement %d has a value that is not a string, number, NULL or hex; the file was not read.', $n));
    }
}
