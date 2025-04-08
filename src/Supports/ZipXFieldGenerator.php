<?php

namespace ZipStore\Supports;

abstract class ZipXFieldGenerator
{
    /* generate zip extra field[id:5455] a.k.a file timestamps */
    public static function xField_5455(File $file, int $mask = 0x00): string
    {
        $signature = 0x5455;

        /* set mtime [required] */
        $flag = 0x01;
        $data = pack('V', $file->getMTime(true));

        /* set atime */
        if ((bool) (~$mask & 0x02)) {
            $flag |= 0x02;
            $data .= pack('V', $file->getATime(true));
        }

        /* set ctime */
        if ((bool) (~$mask & 0x04)) {
            $flag |= 0x04;
            $data .= pack('V', $file->getCTime(true));
        }

        /* +1 for the flag */
        return pack('vvC', $signature, \strlen($data) + 1, $flag).$data;
    }

    /* generate zip extra field[id:7875] a.k.a file owner/group */
    public static function xField_7875(File $file): string
    {
        $signature = 0x7875;

        /* in order: [version, len(uid), uid, len(gid), gid] */
        $data = pack('CCVCV', 0x01, 0x04, $file->getUID(), 0x04, $file->getGID());

        return pack('vv', $signature, \strlen($data)).$data;
    }
}
