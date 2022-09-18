<?php

namespace App\Twig;

use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\FAIconGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AttachmentExtension extends AbstractExtension
{
    protected AttachmentURLGenerator $attachmentURLGenerator;
    protected FAIconGenerator $FAIconGenerator;

    public function __construct(AttachmentURLGenerator $attachmentURLGenerator, FAIconGenerator $FAIconGenerator)
    {
        $this->attachmentURLGenerator = $attachmentURLGenerator;
        $this->FAIconGenerator = $FAIconGenerator;
    }

    public function getFunctions(): array
    {
        return [
            /* Returns the URL to a thumbnail of the given attachment */
            new TwigFunction('attachment_thumbnail', [$this->attachmentURLGenerator, 'getThumbnailURL']),
            /* Returns the font awesome icon class which is representing the given file extension  */
            new TwigFunction('ext_to_fa_icon', [$this->FAIconGenerator, 'fileExtensionToFAType']),
        ];
    }
}