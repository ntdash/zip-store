<?php

namespace ZipStore;

use ZipStore\Contracts\ZipHeader;
use ZipStore\Supports\Utils;
use ZipStore\Supports\ZipGPBitsGenerator;
use ZipStore\Supports\ZipXFieldGenerator;

/**
 * Central Directory Entry
 *
 * @property-read Entry $entry
 */
class CentralDirectoryEntry implements ZipHeader
{
    protected const SIGNATURE = 0x02014B50;

    /* caching */

    private string $content;

    private int $size;

    public function __construct(private Entry $entry) {}

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

        /* signature */
        $content = pack('V', self::SIGNATURE);

        /* version made by */
        $content .= pack('v', 0x031E);

        /* version needed */
        $content .= pack('v', 0x0A);

        /* general purpose flags */
        $content .= (string) new ZipGPBitsGenerator(utf8: true);

        /* compression method */
        $content .= $b2null;

        /* modification time */
        $content .= pack('v', Utils::toMSDOSTime($fileMTime));

        /* modification date */
        $content .= pack('v', Utils::toMSDOSDate($fileMTime));

        /* crc-32 digest */
        $content .= $file->getPackedCRC32Digest();

        /* compressed data size */
        $content .= pack('V', $fileSize);

        /* uncompressed data size */
        $content .= pack('V', $fileSize);

        /* filename length */
        $content .= pack('v', \strlen($filename));

        /* extra field length */
        $content .= pack('v', \strlen($xtraField));

        /* entry comment length [2B] */
        $content .= $b2null;

        /* disk number where file starts [2B] */
        $content .= $b2null;

        /* internal file attributes [2B] */
        $content .= $b2null;

        /* external attributes [4B] */
        $content .= pack('V', ($file->getMode() << 16));

        /* relative offset; relative to disk => basic offset from 0, */
        /* since we do not deal with partition */
        $content .= pack('V', $this->entry->offset);

        /* filename */
        $content .= $filename;

        /* extra fields */
        $content .= $xtraField;

        /* comment | */
        $content .= '';

        return $content;
    }
}
