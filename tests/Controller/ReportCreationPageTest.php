<?php

namespace App\Tests\Controller;

use App\Entity\Report;
use PHPUnit\Framework\TestCase;

final class ReportCreationPageTest extends TestCase
{
    public function testAudioFieldsAreNotPartOfAReport(): void
    {
        self::assertFalse(method_exists(Report::class, 'getAudioUrl'));
        self::assertFalse(method_exists(Report::class, 'getAudioLanguage'));
    }
}
