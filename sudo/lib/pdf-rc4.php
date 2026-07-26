<?php
// Minimal pure-PHP decryptor for the PDF "Standard Security Handler" using
// RC4 (V1/V2, revisions 2-4 — 40 or 128-bit). No shell_exec/exec/proc_open
// is used anywhere (production hosting has those disabled — see
// cron/backup_database.php), and no mature pure-PHP library actually
// decrypts real password-protected PDFs: smalot/pdfparser and
// setasign/fpdi both refuse any encrypted source outright (confirmed by
// testing against real M-Pesa statement samples). Both real M-Pesa sample
// files use exactly this RC4/V2/R3/128-bit scheme, which is short and
// exactly specified (PDF 32000-1:2008 §7.6), so a narrow implementation
// of just that scheme is safer than depending on a library that doesn't
// work at all.
//
// Deliberately out of scope: AES / crypt-filter encryption (V4/V5),
// incrementally-updated PDFs with a /Prev xref chain, and decrypting
// literal/hex strings in object dictionaries (only stream data is
// decrypted). None of that is needed to extract the tabular transaction
// text these statement generators produce, and skipping it avoids the
// byte-length-preservation problems that come with re-escaping decrypted
// PDF string literals in place. Unsupported files throw a typed exception
// so the caller can show "please remove the password and re-upload"
// rather than silently importing garbled figures.

class PdfDecryptException extends \RuntimeException
{
}

class PdfWrongPasswordException extends PdfDecryptException
{
}

class PdfUnsupportedEncryptionException extends PdfDecryptException
{
}

final class PdfRc4Decryptor
{
    // PDF 32000-1:2008 Algorithm 2, step (a) padding string.
    private const PAD =
        "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08" .
        "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    public static function isEncrypted(string $bytes): bool
    {
        $trailer = self::findTrailer($bytes);
        return $trailer !== null && preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $trailer) === 1;
    }

    /**
     * Decrypts $bytes (a full PDF file's contents) and returns the
     * decrypted PDF bytes. Returns $bytes unchanged if the file isn't
     * encrypted at all.
     */
    public static function decrypt(string $bytes, string $password = ''): string
    {
        $trailer = self::findTrailer($bytes);
        if ($trailer === null || !preg_match('/\/Encrypt\s+(\d+)\s+\d+\s+R/', $trailer, $encRef)) {
            return $bytes;
        }

        if (!preg_match('/\/ID\s*\[\s*<([0-9A-Fa-f]*)>/', $trailer, $idMatch)) {
            throw new PdfUnsupportedEncryptionException('This PDF has no /ID entry in its trailer, so its encryption key cannot be computed.');
        }
        $id0 = self::hexToBytes($idMatch[1]);

        $xref = self::parseXref($bytes, $trailer);

        $encryptObjNum = (int) $encRef[1];
        $encryptDict = self::readObjectWindow($bytes, $xref, $encryptObjNum);
        if ($encryptDict === null) {
            throw new PdfUnsupportedEncryptionException('Could not locate this PDF\'s /Encrypt object.');
        }

        if (!preg_match('/\/Filter\s*\/Standard/', $encryptDict)) {
            throw new PdfUnsupportedEncryptionException('Only the standard PDF security handler is supported.');
        }

        $v = preg_match('/\/V\s+(\d+)/', $encryptDict, $m) ? (int) $m[1] : 1;
        $r = preg_match('/\/R\s+(\d+)/', $encryptDict, $m) ? (int) $m[1] : 2;
        if ($v >= 4) {
            throw new PdfUnsupportedEncryptionException('This PDF uses AES/crypt-filter encryption, which isn\'t supported. Please remove its password with another tool and re-upload.');
        }

        $keyBits = preg_match('/\/Length\s+(\d+)/', $encryptDict, $m) ? (int) $m[1] : 40;
        $keyBytes = intdiv($keyBits, 8);

        $o = self::readPdfString($encryptDict, 'O');
        $u = self::readPdfString($encryptDict, 'U');
        if ($o === null || $u === null) {
            throw new PdfUnsupportedEncryptionException('This PDF\'s /Encrypt dictionary is missing /O or /U.');
        }
        $p = preg_match('/\/P\s+(-?\d+)/', $encryptDict, $m) ? (int) $m[1] : 0;

        $key = self::computeEncryptionKey($password, $o, $p, $id0, $r, $keyBytes);

        if (!self::passwordIsValid($key, $u, $id0, $r)) {
            throw new PdfWrongPasswordException('Incorrect PDF password.');
        }

        return self::decryptObjects($bytes, $xref, $key, $encryptObjNum, $trailer);
    }

    private static function findTrailer(string $bytes): ?string
    {
        $pos = strrpos($bytes, 'trailer');
        if ($pos === false) {
            return null;
        }
        $end = strpos($bytes, 'startxref', $pos);
        return $end === false ? substr($bytes, $pos) : substr($bytes, $pos, $end - $pos);
    }

    /**
     * Parses the classic (non cross-reference-stream) xref table pointed
     * to by the most recent startxref. Returns [objNum => byteOffset].
     */
    private static function parseXref(string $bytes, string $trailer): array
    {
        if (!preg_match('/startxref\s+(\d+)/', $bytes, $m)) {
            throw new PdfUnsupportedEncryptionException('Could not find startxref.');
        }
        $offset = (int) $m[1];
        if (substr($bytes, $offset, 4) !== 'xref') {
            throw new PdfUnsupportedEncryptionException('This PDF uses cross-reference streams (not the classic xref table), which isn\'t supported.');
        }

        $xrefEnd = strpos($bytes, 'trailer', $offset);
        $section = substr($bytes, $offset + 4, ($xrefEnd !== false ? $xrefEnd : strlen($bytes)) - ($offset + 4));

        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($section));
        $i = 0;
        $count = count($lines);
        while ($i < $count) {
            if (!preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $lines[$i], $sub)) {
                $i++;
                continue;
            }
            $startNum = (int) $sub[1];
            $subCount = (int) $sub[2];
            $i++;
            for ($n = 0; $n < $subCount && $i < $count; $n++, $i++) {
                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])/', $lines[$i], $em) && $em[3] === 'n') {
                    $entries[$startNum + $n] = (int) $em[1];
                }
            }
        }
        return $entries;
    }

    /**
     * Returns the raw bytes of an object from its xref offset up to (but
     * not including) the next known object's offset, or EOF for the last
     * object. This gives an exact window without needing to
     * keyword-scan for "endobj" (which risks false positives inside
     * binary stream data).
     */
    private static function readObjectWindow(string $bytes, array $xref, int $objNum): ?string
    {
        if (!isset($xref[$objNum])) {
            return null;
        }
        $offset = $xref[$objNum];
        $nextOffset = null;
        foreach ($xref as $candidateOffset) {
            if ($candidateOffset > $offset && ($nextOffset === null || $candidateOffset < $nextOffset)) {
                $nextOffset = $candidateOffset;
            }
        }
        $end = $nextOffset ?? strlen($bytes);
        return substr($bytes, $offset, $end - $offset);
    }

    private static function readPdfString(string $dict, string $key): ?string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\s*<([0-9A-Fa-f]*)>/', $dict, $m)) {
            return self::hexToBytes($m[1]);
        }
        if (preg_match('/\/' . preg_quote($key, '/') . '\s*\(((?:\\\\.|[^()\\\\])*)\)/', $dict, $m)) {
            return self::unescapePdfLiteral($m[1]);
        }
        return null;
    }

    private static function unescapePdfLiteral(string $s): string
    {
        return preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', function ($m) {
            $esc = $m[1];
            $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", '(' => '(', ')' => ')', '\\' => '\\'];
            if (isset($map[$esc])) {
                return $map[$esc];
            }
            return chr(octdec($esc) & 0xFF);
        }, $s);
    }

    private static function hexToBytes(string $hex): string
    {
        if (strlen($hex) % 2 !== 0) {
            $hex .= '0';
        }
        return hex2bin($hex);
    }

    private static function packP(int $p): string
    {
        // Low-order byte first, 32-bit two's complement.
        $unsigned = $p < 0 ? $p + 4294967296 : $p;
        return chr($unsigned & 0xFF) . chr(($unsigned >> 8) & 0xFF) . chr(($unsigned >> 16) & 0xFF) . chr(($unsigned >> 24) & 0xFF);
    }

    private static function computeEncryptionKey(string $password, string $o, int $p, string $id0, int $r, int $keyBytes): string
    {
        $padded = substr($password . self::PAD, 0, 32);
        $input = $padded . $o . self::packP($p) . $id0;
        $hash = md5($input, true);
        if ($r >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $hash = md5(substr($hash, 0, $keyBytes), true);
            }
        }
        return substr($hash, 0, $keyBytes);
    }

    private static function passwordIsValid(string $key, string $u, string $id0, int $r): bool
    {
        if ($r === 2) {
            $computed = self::rc4($key, self::PAD);
            return substr($computed, 0, 32) === substr($u . str_repeat("\0", 32), 0, 32);
        }

        $hash = md5(self::PAD . $id0, true);
        $val = self::rc4($key, $hash);
        for ($i = 1; $i <= 19; $i++) {
            $xoredKey = '';
            for ($b = 0; $b < strlen($key); $b++) {
                $xoredKey .= chr(ord($key[$b]) ^ $i);
            }
            $val = self::rc4($xoredKey, $val);
        }
        return substr($val, 0, 16) === substr($u, 0, 16);
    }

    private static function objectKey(string $baseKey, int $objNum, int $gen): string
    {
        $input = $baseKey
            . chr($objNum & 0xFF) . chr(($objNum >> 8) & 0xFF) . chr(($objNum >> 16) & 0xFF)
            . chr($gen & 0xFF) . chr(($gen >> 8) & 0xFF);
        $hash = md5($input, true);
        $n = min(strlen($baseKey) + 5, 16);
        return substr($hash, 0, $n);
    }

    private static function rc4(string $key, string $data): string
    {
        $keyLen = strlen($key);
        if ($keyLen === 0) {
            return $data;
        }
        $s = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLen])) & 0xFF;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }
        $out = '';
        $i = 0;
        $j = 0;
        $len = strlen($data);
        for ($k = 0; $k < $len; $k++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $s[$i]) & 0xFF;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $out .= chr(ord($data[$k]) ^ $s[($s[$i] + $s[$j]) & 0xFF]);
        }
        return $out;
    }

    /**
     * Walks every in-use object and decrypts its stream data (if any) in
     * place using that object's per-object RC4 key, then blanks out the
     * trailer's /Encrypt entry. Literal/hex strings in object
     * dictionaries are deliberately left untouched (see file header
     * comment) — only stream data (page content, ToUnicode CMaps) is
     * decrypted, which is what text extraction actually needs.
     */
    private static function decryptObjects(string $bytes, array $xref, string $key, int $encryptObjNum, string $trailer): string
    {
        $out = $bytes;

        foreach ($xref as $objNum => $offset) {
            if ($objNum === $encryptObjNum || $objNum === 0) {
                continue;
            }
            $window = self::readObjectWindow($bytes, $xref, $objNum);
            if ($window === null || !preg_match('/^(\d+)\s+(\d+)\s+obj/', $window, $hdr)) {
                continue;
            }
            $gen = (int) $hdr[2];

            $streamPos = strpos($window, 'stream');
            if ($streamPos === false) {
                continue;
            }
            $dictPart = substr($window, 0, $streamPos);

            $length = null;
            if (preg_match('/\/Length\s+(\d+)\s+\d+\s+R/', $dictPart, $lm)) {
                $length = self::resolveIndirectInt($bytes, $xref, (int) $lm[1]);
            } elseif (preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dictPart, $lm)) {
                $length = (int) $lm[1];
            }
            if ($length === null) {
                continue;
            }

            // Stream data starts right after "stream" + EOL (CR LF, or LF alone).
            $dataStart = $streamPos + strlen('stream');
            if (substr($window, $dataStart, 2) === "\r\n") {
                $dataStart += 2;
            } elseif (substr($window, $dataStart, 1) === "\n") {
                $dataStart += 1;
            }

            $cipherText = substr($window, $dataStart, $length);
            if (strlen($cipherText) !== $length) {
                continue;
            }
            $plainText = self::rc4(self::objectKey($key, $objNum, $gen), $cipherText);

            $absoluteStart = $offset + $dataStart;
            $out = substr_replace($out, $plainText, $absoluteStart, $length);
        }

        // Blank out "/Encrypt N G R" in the trailer (same length, so no
        // offsets shift) so downstream parsers see a plain, unencrypted file.
        $trailerPos = strrpos($out, 'trailer');
        if ($trailerPos !== false && preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $trailer, $encMatch, PREG_OFFSET_CAPTURE)) {
            $relPos = strpos($out, $encMatch[0][0], $trailerPos);
            if ($relPos !== false) {
                $out = substr_replace($out, str_repeat(' ', strlen($encMatch[0][0])), $relPos, strlen($encMatch[0][0]));
            }
        }

        return $out;
    }

    private static function resolveIndirectInt(string $bytes, array $xref, int $objNum): ?int
    {
        $window = self::readObjectWindow($bytes, $xref, $objNum);
        if ($window !== null && preg_match('/^\d+\s+\d+\s+obj\s+(\d+)/', $window, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
