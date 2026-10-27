<?php

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Tests;

use OpenHealthcare\LayoutExport\Stock\Reserved;
use OpenHealthcare\LayoutExport\Stock\StockCheck;
use OpenHealthcare\LayoutExport\Stock\StockSchema;
use PHPUnit\Framework\TestCase;

/**
 * The stock schema is read from core's own database.sql (an excerpt of master 644903b86d in tests/fixtures), the
 * reserved columns from core's own edit_layout.php (its isColumnReserved() at master and at 7.0.0), and the check
 * finds what core issue #14742 costs: a stock column gone, a field the editor would drop the column of, a stock
 * field not on the layout.
 */
final class StockCheckTest extends TestCase
{
    private static function stock(): StockSchema
    {
        return StockSchema::fromSql((string)file_get_contents(__DIR__ . '/fixtures/database-sql-excerpt.sql'));
    }

    public function testTheStockSchemaIsReadFromDatabaseSql(): void
    {
        $stock = self::stock();
        self::assertSame(['DEM', 'HIS'], array_keys($stock->fields));
        self::assertSame(['title', 'ref_providerID', 'pharmacy_id', 'hipaa_notice'], $stock->fields['DEM'], 'em_name is an employer_data field, not a patient_data column');
        self::assertSame(['usertext11', 'exams'], $stock->fields['HIS']);
        self::assertGreaterThan(100, count($stock->columns['patient_data']), 'the whole CREATE TABLE was read');
        self::assertSame('`ref_providerID` int(11) default NULL', $stock->columns['patient_data']['ref_providerID']);
        self::assertArrayNotHasKey('PRIMARY', $stock->columns['patient_data'], 'keys are not columns');
    }

    public function testLayoutsMapToTablesAsTheLayoutEditorMapsThem(): void
    {
        self::assertSame('patient_data', StockSchema::tableFor('DEM'));
        self::assertSame('history_data', StockSchema::tableFor('HIS'));
        self::assertSame('lists_ippf_srh', StockSchema::tableFor('SRH'));
        self::assertSame('', StockSchema::tableFor('LBFvitals'), 'a layout-based form stores its fields in rows');
        self::assertSame('', StockSchema::tableFor('FACUSR'));
    }

    public function testTheReservedColumnsAreReadFromTheEditorOfEachVersion(): void
    {
        foreach (['644903b86d' => 39, 'v7_0_0' => 37] as $tag => $patient) {
            $reserved = Reserved::fromSource((string)file_get_contents(__DIR__ . "/fixtures/isColumnReserved-$tag.php.txt"));
            self::assertNotNull($reserved, $tag);
            self::assertCount($patient, $reserved['patient_data'], "$tag: patient_data");
            self::assertSame(['id', 'date', 'pid'], $reserved['history_data'], "$tag: history_data");
            self::assertNotContains('ref_providerID', $reserved['patient_data'], "$tag: the column #14742 names is not protected");
        }
        self::assertNull(Reserved::fromSource('<?php function other() {}'), 'no function: unreadable, not "nothing reserved"');
        self::assertNull(Reserved::fromSource("<?php\nfunction isColumnReserved(\$t, \$f)\n{\n    return false;\n}\n"), 'a list without pid is taken as unread');
    }

    public function testTheCheckFindsWhatTheLayoutEditorCanCost(): void
    {
        $reserved = ['patient_data' => ['pid', 'title'], 'history_data' => ['id', 'date', 'pid']];
        $siteFields = ['DEM' => ['title', 'pharmacy_id', 'hipaa_notice'], 'HIS' => ['usertext11']];
        $siteColumns = ['patient_data' => ['pid', 'title', 'pharmacy_id', 'hipaa_notice'], 'history_data' => ['id', 'pid', 'usertext11', 'exams']];
        $empty = ['patient_data' => ['title', 'pharmacy_id'], 'history_data' => ['usertext11']];
        $found = StockCheck::findings(self::stock(), $siteFields, $siteColumns, $empty, $reserved);
        $by = array_map(static fn(array $f): string => $f['kind'] . ':' . $f['layout'] . '.' . $f['field'], $found);
        self::assertSame([
            'dropped:DEM.ref_providerID',      // deleted with its field: a new visit fails on it
            'would_drop:DEM.pharmacy_id',      // empty and unprotected
            'would_drop:HIS.usertext11',
            'not_on_layout:HIS.exams',         // off the layout, column kept
        ], $by, 'title is empty but protected; hipaa_notice has data');
        self::assertSame('ALTER TABLE `patient_data` ADD COLUMN `ref_providerID` int(11) default NULL;',
            StockCheck::repair($found[0]['table'], $found[0]['definition']));

        $unread = StockCheck::findings(self::stock(), $siteFields, $siteColumns, $empty, null);
        self::assertSame(['dropped', 'not_on_layout'], array_values(array_unique(array_column($unread, 'kind'))),
            'without the protected list, nothing is said to be droppable');
    }

    public function testAHistoryColumnGoneIsOneFindingThoughEveryHistoryLayoutHasIt(): void
    {
        $stock = new StockSchema(['HIS' => ['exams'], 'HIS2' => ['exams']], ['history_data' => ['exams' => '`exams` longtext']]);
        $found = StockCheck::findings($stock, ['HIS' => ['exams'], 'HIS2' => ['exams']], ['history_data' => ['id']], [], null);
        self::assertCount(1, $found);
        self::assertSame(StockCheck::DROPPED, $found[0]['kind']);
    }
}
