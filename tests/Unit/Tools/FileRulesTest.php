<?php

declare(strict_types=1);

namespace Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Tools\FileRules;

final class FileRulesTest extends TestCase
{
    public function testCleanFileHasNoViolation(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\nfinal class A {}\n";

        self::assertSame([], FileRules::check($source));
    }

    public function testCommentIsReported(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\n// note\nfinal class A {}\n";

        self::assertSame(['comment at line 5'], FileRules::check($source));
    }

    public function testDocBlockIsReported(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\n/** doc */\nfinal class A {}\n";

        self::assertSame(['comment at line 5'], FileRules::check($source));
    }

    public function testMissingStrictTypesIsReported(): void
    {
        $source = "<?php\n\nfinal class A {}\n";

        self::assertSame(['missing declare(strict_types=1) as first statement'], FileRules::check($source));
    }

    public function testTooManyLinesIsReported(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n" . str_repeat("\n", FileRules::MAX_LINES);

        self::assertSame(['153 lines (max 150)'], FileRules::check($source));
    }

    public function testViolationsWalksDirectories(): void
    {
        $dir = sys_get_temp_dir() . '/file-rules-' . bin2hex(random_bytes(4));
        mkdir($dir . '/nested', 0o777, true);
        file_put_contents($dir . '/ok.php', "<?php\n\ndeclare(strict_types=1);\n");
        file_put_contents($dir . '/nested/bad.php', "<?php\n// x\n");

        $violations = FileRules::violations([$dir]);

        self::assertCount(2, $violations);
        self::assertStringContainsString('nested/bad.php: comment at line 2', $violations[0]);
        self::assertStringContainsString('nested/bad.php: missing declare', $violations[1]);
        unlink($dir . '/nested/bad.php');
        unlink($dir . '/ok.php');
        rmdir($dir . '/nested');
        rmdir($dir);
    }
}
