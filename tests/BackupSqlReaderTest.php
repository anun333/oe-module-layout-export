<?php

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Tests;

use OpenHealthcare\LayoutExport\Snapshot\BackupSqlReader;
use OpenHealthcare\LayoutExport\Snapshot\FileError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BackupSqlReaderTest extends TestCase
{
    public function testItReadsAFileBackupPhpWroteAsRows(): void
    {
        // Written by mariadb-dump with backup.php's own options and its DELETE lines, from OpenEMR 8.4.1's
        // Referral transaction layout (LBTref) and the risklevel list.
        $s = BackupSqlReader::read((string)file_get_contents(__DIR__ . '/fixtures/backup-php-LBTref.sql'));
        $this->assertSame(['LBTref'], array_keys($s->layouts));
        $this->assertCount(19, $s->layouts['LBTref']['fields']);
        $this->assertCount(3, $s->layouts['LBTref']['properties']);
        $this->assertSame('Referral', $s->layouts['LBTref']['properties'][0]['grp_title'], 'the form\'s own row (grp_group_id empty) first');
        $this->assertSame(['risklevel'], array_keys($s->lists));
        $this->assertSame('Risk Level', $s->lists['risklevel']['list']['title'] ?? null);
        $this->assertSame(['low', 'medium', 'high'], array_column($s->lists['risklevel']['options'], 'option_id'), 'in their seq order');
        $this->assertContains('codes', $s->schema['layout_options'], 'the columns come from the INSERTs');
        $this->assertNull($s->lists['risklevel']['options'][0]['notes'], 'NULL is read as null');
    }

    public function testValuesAreReadWithMysqlsEscapes(): void
    {
        $sql = "INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `notes`, `seq`) VALUES ('x','o1','O\\'Brien \"Jr\"','line\\nnext',-2);\n"
            . "INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `notes`, `seq`) VALUES ('x','o2','it''s; fine',0x41422F,3.5);";
        $options = BackupSqlReader::read($sql)->lists['x']['options'];
        $this->assertSame(['O\'Brien "Jr"', "line\nnext", '-2'], [$options[0]['title'], $options[0]['notes'], $options[0]['seq']]);
        $this->assertSame(["it's; fine", 'AB/', '3.5'], [$options[1]['title'], $options[1]['notes'], $options[1]['seq']], 'a semicolon in a value, a doubled quote and hex');
    }

    public function testBackupPhpsColumnRepairIsRecognisedAndSkipped(): void
    {
        $sql = "DELETE FROM layout_options WHERE form_id = \"DEM\";\nSET sql_mode = '';\nSET group_concat_max_len = 1000000;\n"
            . "SELECT CONCAT(\n'ALTER TABLE patient_data ',\nCOALESCE(GROUP_CONCAT(DISTINCT ' ADD `', lo.field_id, '` TEXT NOT NULL' ORDER BY lo.field_id), '')\n)\n"
            . "FROM layout_options AS lo WHERE\n(lo.form_id LIKE 'DEM%' OR lo.source = 'D') AND lo.field_id NOT IN\n"
            . "(SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'patient_data')\nINTO @sql;\nPREPARE stmt FROM @sql;\nEXECUTE stmt;\n";
        $s = BackupSqlReader::read($sql);
        $this->assertSame(['DEM'], array_keys($s->layouts), 'the DELETE names the layout the file covers, even with no rows');
        $this->assertSame([], $s->layouts['DEM']['fields']);
    }

    /** @return iterable<string, array{string}> */
    public static function refused(): iterable
    {
        yield 'another table' => ["INSERT INTO `users` (`id`) VALUES (1);"];
        yield 'a drop' => ["DROP TABLE list_options;"];
        yield 'a wider delete' => ["DELETE FROM list_options WHERE 1 = 1;"];
        yield 'an update' => ["UPDATE list_options SET title = 'x';"];
        yield 'an unended quote' => ["INSERT INTO `list_options` (`list_id`) VALUES ('x);"];
        yield 'a function call' => ["INSERT INTO `list_options` (`list_id`, `title`) VALUES ('x', NOW());"];
        yield 'too few values' => ["INSERT INTO `list_options` (`list_id`, `option_id`) VALUES ('x');"];
    }

    #[DataProvider('refused')]
    public function testAnythingElseRefusesTheWholeFile(string $sql): void
    {
        $this->expectException(FileError::class);
        BackupSqlReader::read("INSERT INTO `list_options` (`list_id`, `option_id`) VALUES ('ok','1');\n" . $sql);
    }

    public function testStatementsSplitOutsideQuotesAndComments(): void
    {
        $this->assertSame(["SELECT 'a;b'", "SELECT `c;d`", "SELECT 2"],
            array_map('trim', BackupSqlReader::statements("SELECT 'a;b'; SELECT `c;d`; /* x; y */ SELECT 2")));
    }
}
