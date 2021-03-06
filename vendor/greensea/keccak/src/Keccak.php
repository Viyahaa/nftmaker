<?php

namespace kornrunner;

use Exception;
use function mb_strlen;
use function mb_substr;
final class Keccak
{
    const KECCAK_ROUNDS = 24;
    const LFSR = 1;
    const ENCODING = '8bit';
    private static $keccakf_rotc = array(1, 3, 6, 10, 15, 21, 28, 36, 45, 55, 2, 14, 27, 41, 56, 8, 25, 43, 62, 18, 39, 61, 20, 44);
    private static $keccakf_piln = array(10, 7, 11, 17, 18, 3, 5, 16, 8, 21, 24, 4, 15, 23, 19, 13, 12, 2, 20, 14, 22, 9, 6, 1);
    private static $x64 = PHP_INT_SIZE === 8;
    private static function keccakf64(&$st, $rounds)
    {
        $keccakf_rndc = array(array(0, 1), array(0, 32898), array(2147483648, 32906), array(2147483648, 2147516416), array(0, 32907), array(0, 2147483649), array(2147483648, 2147516545), array(2147483648, 32777), array(0, 138), array(0, 136), array(0, 2147516425), array(0, 2147483658), array(0, 2147516555), array(2147483648, 139), array(2147483648, 32905), array(2147483648, 32771), array(2147483648, 32770), array(2147483648, 128), array(0, 32778), array(2147483648, 2147483658), array(2147483648, 2147516545), array(2147483648, 32896), array(0, 2147483649), array(2147483648, 2147516424));
        $bc = array();
        for ($round = 0; $round < $rounds; $round++) {
            // Theta
            for ($i = 0; $i < 5; $i++) {
                $bc[$i] = array($st[$i][0] ^ $st[$i + 5][0] ^ $st[$i + 10][0] ^ $st[$i + 15][0] ^ $st[$i + 20][0], $st[$i][1] ^ $st[$i + 5][1] ^ $st[$i + 10][1] ^ $st[$i + 15][1] ^ $st[$i + 20][1]);
            }
            for ($i = 0; $i < 5; $i++) {
                $t = array($bc[($i + 4) % 5][0] ^ ($bc[($i + 1) % 5][0] << 1 | $bc[($i + 1) % 5][1] >> 31) & 4294967295, $bc[($i + 4) % 5][1] ^ ($bc[($i + 1) % 5][1] << 1 | $bc[($i + 1) % 5][0] >> 31) & 4294967295);
                for ($j = 0; $j < 25; $j += 5) {
                    $st[$j + $i] = array($st[$j + $i][0] ^ $t[0], $st[$j + $i][1] ^ $t[1]);
                }
            }
            // Rho Pi
            $t = $st[1];
            for ($i = 0; $i < 24; $i++) {
                $j = self::$keccakf_piln[$i];
                $bc[0] = $st[$j];
                $n = self::$keccakf_rotc[$i];
                $hi = $t[0];
                $lo = $t[1];
                if ($n >= 32) {
                    $n -= 32;
                    $hi = $t[1];
                    $lo = $t[0];
                }
                $st[$j] = array(($hi << $n | $lo >> 32 - $n) & 4294967295, ($lo << $n | $hi >> 32 - $n) & 4294967295);
                $t = $bc[0];
            }
            //  Chi
            for ($j = 0; $j < 25; $j += 5) {
                for ($i = 0; $i < 5; $i++) {
                    $bc[$i] = $st[$j + $i];
                }
                for ($i = 0; $i < 5; $i++) {
                    $st[$j + $i] = array($st[$j + $i][0] ^ ~$bc[($i + 1) % 5][0] & $bc[($i + 2) % 5][0], $st[$j + $i][1] ^ ~$bc[($i + 1) % 5][1] & $bc[($i + 2) % 5][1]);
                }
            }
            // Iota
            $st[0] = array($st[0][0] ^ $keccakf_rndc[$round][0], $st[0][1] ^ $keccakf_rndc[$round][1]);
        }
    }
    private static function keccak64($in_raw, int $capacity, int $outputlength, $suffix, bool $raw_output) : string
    {
        $capacity /= 8;
        $inlen = mb_strlen($in_raw, self::ENCODING);
        $rsiz = 200 - 2 * $capacity;
        $rsizw = $rsiz / 8;
        $st = array();
        for ($i = 0; $i < 25; $i++) {
            $st[] = array(0, 0);
        }
        for ($in_t = 0; $inlen >= $rsiz; $inlen -= $rsiz, $in_t += $rsiz) {
            for ($i = 0; $i < $rsizw; $i++) {
                $t = unpack('V*', mb_substr($in_raw, $i * 8 + $in_t, 8, self::ENCODING));
                $st[$i] = array($st[$i][0] ^ $t[2], $st[$i][1] ^ $t[1]);
            }
            self::keccakf64($st, self::KECCAK_ROUNDS);
        }
        $temp = mb_substr($in_raw, $in_t, $inlen, self::ENCODING);
        $temp = str_pad($temp, $rsiz, ' ', STR_PAD_RIGHT);
        $temp[$inlen] = chr($suffix);
        $temp[$rsiz - 1] = chr(ord($temp[$rsiz - 1]) | 128);
        for ($i = 0; $i < $rsizw; $i++) {
            $t = unpack('V*', mb_substr($temp, $i * 8, 8, self::ENCODING));
            $st[$i] = array($st[$i][0] ^ $t[2], $st[$i][1] ^ $t[1]);
        }
        self::keccakf64($st, self::KECCAK_ROUNDS);
        $out = '';
        for ($i = 0; $i < 25; $i++) {
            $out .= $t = pack('V*', $st[$i][1], $st[$i][0]);
        }
        $r = mb_substr($out, 0, $outputlength / 8, self::ENCODING);
        return $raw_output ? $r : bin2hex($r);
    }
    private static function keccakf32(&$st, $rounds) : void
    {
        $keccakf_rndc = array(array(0, 0, 0, 1), array(0, 0, 0, 32898), array(32768, 0, 0, 32906), array(32768, 0, 32768, 32768), array(0, 0, 0, 32907), array(0, 0, 32768, 1), array(32768, 0, 32768, 32897), array(32768, 0, 0, 32777), array(0, 0, 0, 138), array(0, 0, 0, 136), array(0, 0, 32768, 32777), array(0, 0, 32768, 10), array(0, 0, 32768, 32907), array(32768, 0, 0, 139), array(32768, 0, 0, 32905), array(32768, 0, 0, 32771), array(32768, 0, 0, 32770), array(32768, 0, 0, 128), array(0, 0, 0, 32778), array(32768, 0, 32768, 10), array(32768, 0, 32768, 32897), array(32768, 0, 0, 32896), array(0, 0, 32768, 1), array(32768, 0, 32768, 32776));
        $bc = array();
        for ($round = 0; $round < $rounds; $round++) {
            // Theta
            for ($i = 0; $i < 5; $i++) {
                $bc[$i] = array($st[$i][0] ^ $st[$i + 5][0] ^ $st[$i + 10][0] ^ $st[$i + 15][0] ^ $st[$i + 20][0], $st[$i][1] ^ $st[$i + 5][1] ^ $st[$i + 10][1] ^ $st[$i + 15][1] ^ $st[$i + 20][1], $st[$i][2] ^ $st[$i + 5][2] ^ $st[$i + 10][2] ^ $st[$i + 15][2] ^ $st[$i + 20][2], $st[$i][3] ^ $st[$i + 5][3] ^ $st[$i + 10][3] ^ $st[$i + 15][3] ^ $st[$i + 20][3]);
            }
            for ($i = 0; $i < 5; $i++) {
                $t = array($bc[($i + 4) % 5][0] ^ ($bc[($i + 1) % 5][0] << 1 | $bc[($i + 1) % 5][1] >> 15) & 65535, $bc[($i + 4) % 5][1] ^ ($bc[($i + 1) % 5][1] << 1 | $bc[($i + 1) % 5][2] >> 15) & 65535, $bc[($i + 4) % 5][2] ^ ($bc[($i + 1) % 5][2] << 1 | $bc[($i + 1) % 5][3] >> 15) & 65535, $bc[($i + 4) % 5][3] ^ ($bc[($i + 1) % 5][3] << 1 | $bc[($i + 1) % 5][0] >> 15) & 65535);
                for ($j = 0; $j < 25; $j += 5) {
                    $st[$j + $i] = array($st[$j + $i][0] ^ $t[0], $st[$j + $i][1] ^ $t[1], $st[$j + $i][2] ^ $t[2], $st[$j + $i][3] ^ $t[3]);
                }
            }
            // Rho Pi
            $t = $st[1];
            for ($i = 0; $i < 24; $i++) {
                $j = self::$keccakf_piln[$i];
                $bc[0] = $st[$j];
                $n = self::$keccakf_rotc[$i] >> 4;
                $m = self::$keccakf_rotc[$i] % 16;
                $st[$j] = array(($t[(0 + $n) % 4] << $m | $t[(1 + $n) % 4] >> 16 - $m) & 65535, ($t[(1 + $n) % 4] << $m | $t[(2 + $n) % 4] >> 16 - $m) & 65535, ($t[(2 + $n) % 4] << $m | $t[(3 + $n) % 4] >> 16 - $m) & 65535, ($t[(3 + $n) % 4] << $m | $t[(0 + $n) % 4] >> 16 - $m) & 65535);
                $t = $bc[0];
            }
            //  Chi
            for ($j = 0; $j < 25; $j += 5) {
                for ($i = 0; $i < 5; $i++) {
                    $bc[$i] = $st[$j + $i];
                }
                for ($i = 0; $i < 5; $i++) {
                    $st[$j + $i] = array($st[$j + $i][0] ^ ~$bc[($i + 1) % 5][0] & $bc[($i + 2) % 5][0], $st[$j + $i][1] ^ ~$bc[($i + 1) % 5][1] & $bc[($i + 2) % 5][1], $st[$j + $i][2] ^ ~$bc[($i + 1) % 5][2] & $bc[($i + 2) % 5][2], $st[$j + $i][3] ^ ~$bc[($i + 1) % 5][3] & $bc[($i + 2) % 5][3]);
                }
            }
            // Iota
            $st[0] = array($st[0][0] ^ $keccakf_rndc[$round][0], $st[0][1] ^ $keccakf_rndc[$round][1], $st[0][2] ^ $keccakf_rndc[$round][2], $st[0][3] ^ $keccakf_rndc[$round][3]);
        }
    }
    private static function keccak32($in_raw, int $capacity, int $outputlength, $suffix, bool $raw_output) : string
    {
        $capacity /= 8;
        $inlen = mb_strlen($in_raw, self::ENCODING);
        $rsiz = 200 - 2 * $capacity;
        $rsizw = $rsiz / 8;
        $st = array();
        for ($i = 0; $i < 25; $i++) {
            $st[] = array(0, 0, 0, 0);
        }
        for ($in_t = 0; $inlen >= $rsiz; $inlen -= $rsiz, $in_t += $rsiz) {
            for ($i = 0; $i < $rsizw; $i++) {
                $t = unpack('v*', mb_substr($in_raw, $i * 8 + $in_t, 8, self::ENCODING));
                $st[$i] = array($st[$i][0] ^ $t[4], $st[$i][1] ^ $t[3], $st[$i][2] ^ $t[2], $st[$i][3] ^ $t[1]);
            }
            self::keccakf32($st, self::KECCAK_ROUNDS);
        }
        $temp = mb_substr($in_raw, $in_t, $inlen, self::ENCODING);
        $temp = str_pad($temp, $rsiz, ' ', STR_PAD_RIGHT);
        $temp[$inlen] = chr($suffix);
        $temp[$rsiz - 1] = chr((int) $temp[$rsiz - 1] | 128);
        for ($i = 0; $i < $rsizw; $i++) {
            $t = unpack('v*', mb_substr($temp, $i * 8, 8, self::ENCODING));
            $st[$i] = array($st[$i][0] ^ $t[4], $st[$i][1] ^ $t[3], $st[$i][2] ^ $t[2], $st[$i][3] ^ $t[1]);
        }
        self::keccakf32($st, self::KECCAK_ROUNDS);
        $out = '';
        for ($i = 0; $i < 25; $i++) {
            $out .= $t = pack('v*', $st[$i][3], $st[$i][2], $st[$i][1], $st[$i][0]);
        }
        $r = mb_substr($out, 0, $outputlength / 8, self::ENCODING);
        return $raw_output ? $r : bin2hex($r);
    }
    private static function keccak($in_raw, int $capacity, int $outputlength, $suffix, bool $raw_output) : string
    {
        return self::$x64 ? self::keccak64($in_raw, $capacity, $outputlength, $suffix, $raw_output) : self::keccak32($in_raw, $capacity, $outputlength, $suffix, $raw_output);
    }
    public static function hash($in, int $mdlen, bool $raw_output = false) : string
    {
        if (!in_array($mdlen, array(224, 256, 384, 512), true)) {
            throw new Exception('Unsupported Keccak Hash output size.');
        }
        return self::keccak($in, $mdlen, $mdlen, self::LFSR, $raw_output);
    }
    public static function shake($in, int $security_level, int $outlen, bool $raw_output = false) : string
    {
        if (!in_array($security_level, array(128, 256), true)) {
            throw new Exception('Unsupported Keccak Shake security level.');
        }
        return self::keccak($in, $security_level, $outlen, 31, $raw_output);
    }
}