<?php

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Tests;

use OpenHealthcare\LayoutExport\Compat\OpenEmr;
use PHPUnit\Framework\TestCase;

final class PageLanguageTest extends TestCase
{
    public function testAnOlderLibraryWithoutPageLangGivesEnglishLeftToRight(): void
    {
        self::assertSame('en', OpenEmr::fromLibrary('pageLang', 'en', PageLanguageTestOldLibrary::class));
        self::assertSame('ltr', OpenEmr::fromLibrary('pageDir', 'ltr', PageLanguageTestOldLibrary::class));
    }

    public function testALibraryWithPageLangIsAsked(): void
    {
        self::assertSame('es', OpenEmr::fromLibrary('pageLang', 'en', PageLanguageTestNewLibrary::class));
        self::assertSame('rtl', OpenEmr::fromLibrary('pageDir', 'ltr', PageLanguageTestNewLibrary::class));
    }

    public function testThePageDeclaresTheUsersLanguageThroughTheWrapper(): void
    {
        foreach (glob(__DIR__ . '/../public/*.php') ?: [] as $page) {
            $source = (string)file_get_contents($page);
            if (preg_match('/<html[\s>]/', $source) === 1) {
                self::assertStringContainsString('<html lang="<?php echo attr(OpenEmr::pageLang()); ?>" dir="<?php echo attr(OpenEmr::pageDir()); ?>">', $source, basename($page));
                self::assertStringContainsString('use OpenHealthcare\LayoutExport\\Compat\\OpenEmr;', $source, basename($page));
            }
        }
    }
}

final class PageLanguageTestOldLibrary
{
}

final class PageLanguageTestNewLibrary
{
    public static function pageLang(): string
    {
        return 'es';
    }

    public static function pageDir(): string
    {
        return 'rtl';
    }
}
