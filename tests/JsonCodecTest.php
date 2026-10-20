<?php

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Tests;

use OpenHealthcare\LayoutExport\Snapshot\FileError;
use OpenHealthcare\LayoutExport\Snapshot\JsonCodec;
use OpenHealthcare\LayoutExport\Snapshot\Snapshot;
use PHPUnit\Framework\TestCase;

final class JsonCodecTest extends TestCase
{
    public function testTheSameRowsInAnyOrderGiveTheSameBytes(): void
    {
        $a = Rows::snapshot();
        $shuffled = Rows::snapshot(
            array_reverse(array_map(static fn(array $r): array => array_reverse($r, true), Rows::snapshot()->layouts['LBFtest']['fields'])),
            array_reverse(Rows::snapshot()->lists['colors']['options']),
        );
        $this->assertSame(JsonCodec::encode($a), JsonCodec::encode($shuffled));
        $this->assertSame(JsonCodec::encode($a), JsonCodec::encode($a));
        $this->assertStringNotContainsString(date('Y-m-d'), JsonCodec::encode($a), 'no export time in the file');
    }

    public function testAFileReadsBackToTheSameSnapshot(): void
    {
        $json = JsonCodec::encode(Rows::snapshot());
        $this->assertSame($json, JsonCodec::encode(JsonCodec::decode($json)));
        $this->assertSame('8.4.1', JsonCodec::decode($json)->openemr);
        $this->assertStringContainsString('"needs"', $json);
    }

    public function testAZipIsByteStableAndHoldsTheSameRows(): void
    {
        $one = tempnam(sys_get_temp_dir(), 'lex');
        $two = tempnam(sys_get_temp_dir(), 'lex');
        JsonCodec::encodeZip(Rows::snapshot(), (string)$one);
        sleep(1);
        JsonCodec::encodeZip(Rows::snapshot(), (string)$two);
        $this->assertSame(md5_file((string)$one), md5_file((string)$two), 'two exports a second apart are byte-identical');
        $this->assertSame(JsonCodec::encode(Rows::snapshot()), JsonCodec::encode(JsonCodec::decodeZip((string)$one)));
        $zip = new \ZipArchive();
        $zip->open((string)$one);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        $this->assertSame(['manifest.json', 'layouts/LBFtest.json', 'lists/colors.json'], $names);
        unlink((string)$one);
        unlink((string)$two);
    }

    public function testIdsBecomeSafeFileNames(): void
    {
        $this->assertSame('LBF_ok-1.2.json', JsonCodec::fileName('LBF_ok-1.2'));
        $this->assertSame('a%2Fb%20c.json', JsonCodec::fileName('a/b c'));
    }

    public function testOtherJsonIsRefused(): void
    {
        $this->expectException(FileError::class);
        JsonCodec::decode('{"resourceType": "Questionnaire"}');
    }

    public function testANewerFormatIsRefused(): void
    {
        $this->expectException(FileError::class);
        JsonCodec::decode((string)json_encode(['format' => JsonCodec::FORMAT, 'version' => JsonCodec::VERSION + 1]));
    }

    public function testTheColumnsLayoutsNeed(): void
    {
        $s = new Snapshot([
            'DEM' => ['properties' => [], 'fields' => [Rows::field('DEM', 'fname', 1), Rows::field('DEM', 'zz_new', 2)]],
            'HIS' => ['properties' => [], 'fields' => [Rows::field('HIS', 'tobacco', 1)]],
            'LBFx' => ['properties' => [], 'fields' => [Rows::field('LBFx', 'phone_cell', 1, ['source' => 'D']), Rows::field('LBFx', 'note', 2),
                Rows::field('LBFx', 'em_street', 3, ['source' => 'D']), Rows::field('LBFx', 'alcohol', 4, ['source' => 'H'])]],
        ], [], []);
        $this->assertSame(['employer_data' => ['street'], 'history_data' => ['alcohol', 'tobacco'], 'patient_data' => ['fname', 'phone_cell', 'zz_new']], $s->needs());
    }

    public function testEmployerFieldsAndFieldTypesWithTablesOfTheirOwn(): void
    {
        // As stock 8.x Demographics has them: em_ fields are employer_data columns, and addresses (54),
        // telecoms (55), related persons (56) and previous names (52) have tables of their own.
        $s = new Snapshot(['DEM' => ['properties' => [], 'fields' => [
            Rows::field('DEM', 'em_name', 1), Rows::field('DEM', 'em_city', 2),
            Rows::field('DEM', 'additional_addresses', 3, ['data_type' => '54']), Rows::field('DEM', 'additional_telecoms', 4, ['data_type' => '55']),
            Rows::field('DEM', 'related_persons', 5, ['data_type' => '56']), Rows::field('DEM', 'name_history', 6, ['data_type' => '52']),
        ]]], [], []);
        $this->assertSame(['employer_data' => ['city', 'name'], 'history_data' => [], 'patient_data' => []], $s->needs());
    }
}
