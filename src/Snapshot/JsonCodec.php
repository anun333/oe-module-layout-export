<?php

/**
 * Snapshots as files: one JSON file, or a zip with one JSON file per layout and per list plus a
 * manifest, which diffs better in git. Two exports of the same site are byte-identical: keys and
 * rows are in a fixed order, the file holds no export time, and zip entries carry a fixed date.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Snapshot;

final class JsonCodec
{
    public const FORMAT = 'openemr-layout-export';
    public const VERSION = 1;
    private const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
    /** 2026-01-01 00:00:00 UTC: every zip entry's date, so the archive doesn't change with the clock. */
    private const ZIP_TIME = 1767225600;

    public static function encode(Snapshot $snapshot): string
    {
        $s = $snapshot->normalized();
        return json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'openemr' => $s->openemr,
            'schema' => $s->schema,
            'needs' => $s->needs(),
            'layouts' => (object)$s->layouts,
            'lists' => (object)$s->lists,
        ], self::FLAGS) . "\n";
    }

    public static function decode(string $json): Snapshot
    {
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new FileError('This is not a Layout Export file.');
        }
        if (!is_int($data['version'] ?? null) || $data['version'] > self::VERSION) {
            throw new FileError('This file was written by a newer version of Layout Export.');
        }
        $layouts = [];
        foreach (self::map($data['layouts'] ?? []) as $id => $layout) {
            $layouts[(string)$id] = ['properties' => self::rows($layout['properties'] ?? []), 'fields' => self::rows($layout['fields'] ?? [])];
        }
        $lists = [];
        foreach (self::map($data['lists'] ?? []) as $id => $list) {
            $lists[(string)$id] = ['list' => is_array($list['list'] ?? null) ? self::row($list['list']) : null, 'options' => self::rows($list['options'] ?? [])];
        }
        $schema = [];
        foreach (self::map($data['schema'] ?? []) as $table => $columns) {
            if (in_array($table, Snapshot::TABLES, true) && is_array($columns)) {
                $schema[(string)$table] = array_values(array_map('strval', array_filter($columns, 'is_string')));
            }
        }
        return (new Snapshot($layouts, $lists, $schema, is_string($data['openemr'] ?? null) ? $data['openemr'] : ''))->normalized();
    }

    /** A zip: manifest.json, layouts/<id>.json and lists/<id>.json, each id in its own file. */
    public static function encodeZip(Snapshot $snapshot, string $path): void
    {
        $s = $snapshot->normalized();
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not write the zip file.');
        }
        $entries = ['manifest.json' => json_encode(['format' => self::FORMAT, 'version' => self::VERSION, 'openemr' => $s->openemr,
            'schema' => $s->schema, 'needs' => $s->needs()], self::FLAGS) . "\n"];
        foreach ($s->layouts as $id => $layout) {
            $entries['layouts/' . self::fileName((string)$id)] = json_encode(['form_id' => (string)$id] + $layout, self::FLAGS) . "\n";
        }
        foreach ($s->lists as $id => $list) {
            $entries['lists/' . self::fileName((string)$id)] = json_encode(['list_id' => (string)$id] + $list, self::FLAGS) . "\n";
        }
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
            $zip->setMtimeName($name, self::ZIP_TIME);
        }
        $zip->close();
    }

    public static function decodeZip(string $path): Snapshot
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new FileError('This is not a zip file.');
        }
        $manifest = $zip->getFromName('manifest.json');
        if ($manifest === false) {
            $zip->close();
            throw new FileError('The zip has no manifest.json, so it is not a Layout Export file.');
        }
        $data = json_decode($manifest, true);
        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            $zip->close();
            throw new FileError('This is not a Layout Export file.');
        }
        $layouts = [];
        $lists = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            $content = $zip->getFromIndex($i);
            $item = is_string($content) ? json_decode($content, true) : null;
            if (str_starts_with($name, 'layouts/') && is_array($item) && is_string($item['form_id'] ?? null)) {
                $layouts[$item['form_id']] = ['properties' => $item['properties'] ?? [], 'fields' => $item['fields'] ?? []];
            } elseif (str_starts_with($name, 'lists/') && is_array($item) && is_string($item['list_id'] ?? null)) {
                $lists[$item['list_id']] = ['list' => $item['list'] ?? null, 'options' => $item['options'] ?? []];
            }
        }
        $zip->close();
        return self::decode(json_encode($data + ['layouts' => (object)$layouts, 'lists' => (object)$lists], JSON_THROW_ON_ERROR));
    }

    /** A file name for an id: letters, digits, dot, dash and underscore kept, anything else percent-encoded. */
    public static function fileName(string $id): string
    {
        return preg_replace_callback('/[^A-Za-z0-9._-]/', static fn(array $m): string => sprintf('%%%02X', ord($m[0])), $id) . '.json';
    }

    /** @return array<string|int, mixed> */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<array<string, ?string>>
     */
    private static function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_map(self::row(...), array_filter($rows, 'is_array'))) : [];
    }

    /**
     * @param array<mixed> $row
     * @return array<string, ?string>
     */
    private static function row(array $row): array
    {
        $out = [];
        foreach ($row as $column => $value) {
            if (is_string($column) && (is_scalar($value) || $value === null)) {
                $out[$column] = $value === null ? null : (string)$value;
            }
        }
        return $out;
    }
}
