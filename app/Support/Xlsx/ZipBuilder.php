<?php

namespace App\Support\Xlsx;

/** Writes a standard .zip archive (deflate) in memory without the zip extension. */
class ZipBuilder
{
    private string $data = '';
    private string $directory = '';
    private int $count = 0;

    public function add(string $name, string $content): void
    {
        $crc = crc32($content);
        $compressed = gzdeflate($content, 6);
        [$time, $date] = $this->dosTime();
        $offset = strlen($this->data);
        $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $time, $date, $crc, strlen($compressed), strlen($content), strlen($name), 0);
        $this->data .= $header.$name.$compressed;
        $this->directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $time, $date, $crc, strlen($compressed), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        $this->count++;
    }

    public function toString(): string
    {
        return $this->data.$this->directory
            .pack('VvvvvVVv', 0x06054b50, 0, 0, $this->count, $this->count, strlen($this->directory), strlen($this->data), 0);
    }

    private function dosTime(): array
    {
        $t = getdate();
        return [($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2),
            (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday']];
    }
}
