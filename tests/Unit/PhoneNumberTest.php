<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public static function cases(): array
    {
        return [
            'awalan 0'           => ['081234567890', '6281234567890'],
            'format +62 spasi'   => ['+62 812-3456-7890', '6281234567890'],
            'sudah 62'           => ['6281234567890', '6281234567890'],
            'tanpa awalan'       => ['81234567890', '6281234567890'],
            'salah ketik 620'    => ['620812 3456 7890', '6281234567890'],
            'terlalu pendek'     => ['0812', null],
            'bukan angka'        => ['abc', null],
            'telepon rumah'      => ['021555555', null],
            'kosong'             => ['', null],
            'null'               => [null, null],
        ];
    }
}
