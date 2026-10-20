<?php

/**
 * Reads an uploaded file, whichever kind it is: a Layout Export zip, a Layout Export JSON file, or
 * an SQL file from the old backup.php page. Told apart by their contents, not their names.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Snapshot;

final class FileReader
{
    public const ZIP = 'zip';
    public const JSON = 'json';
    public const BACKUP_SQL = 'backup.php SQL';

    /** @return array{0: Snapshot, 1: string} the snapshot, and which kind of file it was */
    public static function read(string $path): array
    {
        $head = (string)file_get_contents($path, false, null, 0, 4);
        if ($head === "PK\x03\x04") {
            return [JsonCodec::decodeZip($path), self::ZIP];
        }
        $content = (string)file_get_contents($path);
        if (str_starts_with(ltrim($content), '{')) {
            return [JsonCodec::decode($content), self::JSON];
        }
        if (str_starts_with($content, "\x1F\x8B")) {
            $content = (string)gzdecode($content);
        }
        if (stripos($content, 'INSERT INTO') === false && stripos($content, 'DELETE FROM') === false) {
            throw new FileError('This is not a Layout Export file or an SQL file from backup.php.');
        }
        return [BackupSqlReader::read($content), self::BACKUP_SQL];
    }
}
