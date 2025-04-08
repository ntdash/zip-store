<?php

namespace ZipStore;

use ZipStore\Contracts\ZipHeader;
use ZipStore\Supports\Utils;
use ZipStore\Supports\ZipXFieldGenerator;

class LocalHeader implements ZipHeader
{
    protected const SIGNATURE = 0x04034B50;

    private string $content;

    private int $size;

    public function __construct(public readonly Entry $entry) {}

    public function getContent(): string
    {
        return $this->content ??= $this->generate();
    }

    public function getSize(): int
    {
        return $this->size ??= \strlen($this->getContent());
    }

    private function generate(): string
    {
        $b2null = pack('v', 0x00);
        $file = $this->entry->file;
        $fileSize = $file->getSize();
        $fileMTime = $file->getMTime();
        $filename = $this->entry->entryName;

        $xtraField = ZipXFieldGenerator::xField_5455($file);
        $xtraField .= ZipXFieldGenerator::xField_7875($file);

        /* signature [4bytes] */
        $content = pack('V', self::SIGNATURE);

        /* version needed to extract [2bytes] */
        $content .= pack('v', 0x0A);

        /* general purpose bit flag [2bytes] */
        $content .= $b2null;

        /* compression method [0=none] */
        $content .= $b2null;

        /* last modification time */
        $content .= pack('v', Utils::toMSDOSTime($fileMTime));

        /* last modification date */
        $content .= pack('v', Utils::toMSDOSDate($fileMTime));

        /* CRC-32 of uncompressed data */
        $content .= $file->getPackedCRC32Digest();

        /* compressed data size */
        $content .= pack('V', $fileSize);

        /* uncompressed data size */
        $content .= pack('V', $fileSize);

        /* filename length */
        $content .= pack('v', \strlen($filename));

        /* extra field length */
        $content .= pack('v', \strlen($xtraField));

        /* filename */
        $content .= $filename;

        /* extra fields: [id:5554] */
        $content .= $xtraField;

        return $content;
    }
}
