<?php declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class LabelDialogTemplateTest extends TestCase
{
    public function testPdfViewerHeightIsIncreased(): void
    {
        $tpl = file_get_contents(__DIR__ . '/../../templates/label_system/dialog.html.twig');

        $this->assertStringContainsString('id="pdf_preview"', $tpl, 'PDF object id must exist');
        $this->assertStringContainsString('height: 280px', $tpl, 'Default PDF viewer height should be 280px to make the toolbar visible in Chromium-based browsers');
    }
}
