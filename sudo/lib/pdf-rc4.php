<?php
// Minimal pure-PHP decryptor for the PDF "Standard Security Handler",
// supporting RC4 (V1/V2, revisions 2-4 — 40 or 128-bit) AND AES-128/CBC
// (V4/R4, crypt filter method AESV2 — the scheme I&M Bank's statement
// export uses). No shell_exec/exec/proc_open is used anywhere (production
// hosting has those disabled — see cron/backup_database.php); AES uses
// PHP's built-in openssl extension (a compiled-in library call, not a
// spawned process). No mature pure-PHP library actually decrypts real
// password-protected PDFs: smalot/pdfparser and setasign/fpdi both refuse
// any encrypted source outright (confirmed by testing against real
// M-Pesa/Equity/I&M statement samples), so this decrypts first and hands
// the plain result to smalot/pdfparser for structural parsing.
//
// Two independent PDF file structures are supported, auto-detected by
// decrypt():
//  - Classic: a `trailer` keyword + classic `xref` table. Used by the
//    RC4-encrypted M-Pesa/Equity samples. Streams are decrypted in place
//    (RC4 preserves length exactly, so byte offsets elsewhere don't
//    shift) and the /Encrypt reference is blanked out.
//  - Modern (I&M Bank / iText-generated): no `trailer` keyword at all —
//    just a cross-reference STREAM (/Type /XRef) plus objects packed into
//    compressed object streams (/Type /ObjStm), as commonly produced by
//    iText and similar modern writers. AES-CBC removes a 16-byte IV and
//    PKCS#7 padding on decrypt, so stream lengths shrink and in-place
//    patching (like the classic path) would leave every later object's
//    xref offset pointing at the wrong byte. Rather than re-deflating a
//    corrected xref stream, decryptModern() fully unpacks the file
//    instead: every direct object's stream is decrypted, every ObjStm is
//    decrypted+inflated and its member objects flattened out to their own
//    top-level objects, and the whole thing is re-serialized as a fresh
//    unencrypted PDF with a plain classic xref table + trailer — which
//    smalot/pdfparser (or any standard reader) can read normally.
//
// Deliberately out of scope: AES-256 (V5/R5-R6/AESV3, a different key
// derivation algorithm — not seen in any real sample), incrementally
// updated PDFs with a /Prev xref chain, and decrypting literal/hex
// strings in object dictionaries (only stream data is decrypted — plenty
// for extracting the tabular transaction text these statement generators
// produce). Unsupported files throw a typed exception so the caller can
// show "please remove the password and re-upload" rather than silently
// importing garbled figures.

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
        if ($trailer === null) {
            // No classic `trailer` keyword — either unencrypted, or a
            // modern file using only a cross-reference stream (see the
            // file header comment). Only bother with the modern path if
            // there's actually a startxref to chase; otherwise leave the
            // bytes untouched, same as the "not encrypted" case below.
            return preg_match('/startxref\s+(\d+)/', $bytes, $sm)
                ? self::decryptModern($bytes, $password)
                : $bytes;
        }
        if (!preg_match('/\/Encrypt\s+(\d+)\s+\d+\s+R/', $trailer, $encRef)) {
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

    private static function computeEncryptionKey(string $password, string $o, int $p, string $id0, int $r, int $keyBytes, bool $encryptMetadata = true): string
    {
        $padded = substr($password . self::PAD, 0, 32);
        $input = $padded . $o . self::packP($p) . $id0;
        // Algorithm 2, step (f): R>=4 with metadata explicitly excluded
        // from encryption appends 4 bytes of 0xFF.
        if ($r >= 4 && !$encryptMetadata) {
            $input .= "\xFF\xFF\xFF\xFF";
        }
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

    // ---- Modern path: cross-reference stream + object streams + AES ----
    // See the file header comment for the overall strategy: fully unpack
    // the file (decrypting every direct object's stream, expanding every
    // ObjStm's members to top-level objects) and re-serialize a fresh
    // classic-xref PDF, rather than patch bytes in place (AES changes
    // stream lengths, which in-place patching can't tolerate).

    private static function decryptModern(string $bytes, string $password): string
    {
        if (!preg_match('/startxref\s+(\d+)\s*(?:%%EOF)?\s*$/s', $bytes, $m)) {
            throw new PdfUnsupportedEncryptionException('Could not find startxref.');
        }
        $xrefStreamOffset = (int) $m[1];
        if (!preg_match('/\G\s*(\d+)\s+(\d+)\s+obj/A', $bytes, $hm, 0, $xrefStreamOffset)) {
            throw new PdfUnsupportedEncryptionException('Could not locate the cross-reference stream object.');
        }

        [$type1, $type2, $meta] = self::parseXrefStream($bytes, $xrefStreamOffset);
        $xrefStreamObjNum = (int) $hm[1];

        if ($meta['encryptRef'] === null) {
            // No /Encrypt entry — file isn't actually encrypted under this
            // structure; nothing for this decryptor to do.
            return $bytes;
        }
        if ($meta['root'] === null || $meta['id0'] === null) {
            throw new PdfUnsupportedEncryptionException('This PDF\'s cross-reference stream is missing /Root or /ID.');
        }

        $encryptDict = self::readObjectWindow($bytes, $type1, $meta['encryptRef']);
        if ($encryptDict === null) {
            throw new PdfUnsupportedEncryptionException('Could not locate this PDF\'s /Encrypt object.');
        }
        if (!preg_match('/\/Filter\s*\/Standard/', $encryptDict)) {
            throw new PdfUnsupportedEncryptionException('Only the standard PDF security handler is supported.');
        }
        $v = preg_match('/\/V\s+(\d+)/', $encryptDict, $vm) ? (int) $vm[1] : 1;
        $r = preg_match('/\/R\s+(\d+)/', $encryptDict, $rm) ? (int) $rm[1] : 2;
        if ($v < 4) {
            throw new PdfUnsupportedEncryptionException('Unexpected encryption version for a cross-reference-stream PDF.');
        }
        if ($v >= 5) {
            throw new PdfUnsupportedEncryptionException('This PDF uses AES-256 (V5) encryption, which isn\'t supported. Please remove its password with another tool and re-upload.');
        }

        $cfm = 'Identity';
        if (preg_match('/\/StmF\s*\/(\w+)/', $encryptDict, $stmfM) && $stmfM[1] !== 'Identity') {
            $cfName = $stmfM[1];
            if (preg_match('/\/CF\s*<<.*?\/' . preg_quote($cfName, '/') . '\s*<<(.*?)>>/s', $encryptDict, $cfM)) {
                $cfm = preg_match('/\/CFM\s*\/(\w+)/', $cfM[1], $cfmM) ? $cfmM[1] : 'V2';
            } else {
                $cfm = 'V2';
            }
        }
        if (!in_array($cfm, ['AESV2', 'V2'], true)) {
            throw new PdfUnsupportedEncryptionException('Unsupported crypt filter method (' . $cfm . ').');
        }
        if ($cfm === 'AESV2' && !function_exists('openssl_decrypt')) {
            throw new PdfUnsupportedEncryptionException('This PDF uses AES encryption, which needs the PHP openssl extension (not available on this server).');
        }

        // The top-level /Length (bits) is what Algorithm 2's key derivation
        // uses — strip the nested /CF<< /StdCF << ... /Length 16 >> >>
        // dict first so its inner (bytes) /Length isn't matched instead.
        $dictForLength = preg_replace('/\/CF\s*<<.*?>>\s*>>/s', '', $encryptDict, 1);
        $keyBits = preg_match('/\/Length\s+(\d+)/', $dictForLength, $lm) ? (int) $lm[1] : 128;
        $keyBytes = intdiv($keyBits, 8);
        $encryptMetadata = !preg_match('/\/EncryptMetadata\s+false/', $encryptDict);

        $o = self::readPdfString($encryptDict, 'O');
        $u = self::readPdfString($encryptDict, 'U');
        if ($o === null || $u === null) {
            throw new PdfUnsupportedEncryptionException('This PDF\'s /Encrypt dictionary is missing /O or /U.');
        }
        $p = preg_match('/\/P\s+(-?\d+)/', $encryptDict, $pm) ? (int) $pm[1] : 0;

        $key = self::computeEncryptionKey($password, $o, $p, $meta['id0'], $r, $keyBytes, $encryptMetadata);
        if (!self::passwordIsValid($key, $u, $meta['id0'], $r)) {
            throw new PdfWrongPasswordException('Incorrect PDF password.');
        }

        // Decrypt every direct object's stream (if any), skipping the
        // xref stream itself (never encrypted) and the Encrypt object
        // (has no stream). ObjStm containers are decrypted here too, same
        // as any other direct object — their members get expanded below.
        $decryptedObjects = []; // objNum => ['gen' => int, 'dict' => string, 'streamBytes' => ?string, 'isObjStm' => bool]
        foreach ($type1 as $objNum => $offset) {
            if ($objNum === $xrefStreamObjNum || $objNum === $meta['encryptRef']) {
                continue;
            }
            $window = self::readObjectWindow($bytes, $type1, $objNum);
            if ($window === null || !preg_match('/^(\d+)\s+(\d+)\s+obj\s*/', $window, $hdr, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $gen = (int) $hdr[2][0];
            $bodyStart = $hdr[0][1] + strlen($hdr[0][0]);
            $streamPos = strpos($window, 'stream', $bodyStart);

            if ($streamPos === false) {
                $endobjPos = strpos($window, 'endobj', $bodyStart);
                $dict = trim(substr($window, $bodyStart, ($endobjPos !== false ? $endobjPos : strlen($window)) - $bodyStart));
                $decryptedObjects[$objNum] = ['gen' => $gen, 'dict' => $dict, 'streamBytes' => null, 'isObjStm' => false];
                continue;
            }

            $dictPart = substr($window, $bodyStart, $streamPos - $bodyStart);
            $length = null;
            if (preg_match('/\/Length\s+(\d+)\s+\d+\s+R/', $dictPart, $lm2)) {
                $length = self::resolveIndirectInt($bytes, $type1, (int) $lm2[1]);
            } elseif (preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dictPart, $lm2)) {
                $length = (int) $lm2[1];
            }
            if ($length === null) {
                continue;
            }
            $dataStart = $streamPos + strlen('stream');
            if (substr($window, $dataStart, 2) === "\r\n") {
                $dataStart += 2;
            } elseif (substr($window, $dataStart, 1) === "\n" || substr($window, $dataStart, 1) === "\r") {
                $dataStart += 1;
            }
            $cipherText = substr($window, $dataStart, $length);
            if (strlen($cipherText) !== $length) {
                continue;
            }

            $plainText = self::decryptStreamBytes($cipherText, $key, $objNum, $gen, $cfm);
            $isObjStm = (bool) preg_match('/\/Type\s*\/ObjStm/', $dictPart);
            $decryptedObjects[$objNum] = [
                'gen' => $gen,
                'dict' => trim($dictPart),
                'streamBytes' => $plainText,
                'isObjStm' => $isObjStm,
            ];
        }

        // Expand every ObjStm's members into standalone top-level objects.
        $flattened = []; // objNum => ['gen' => int, 'content' => string ("<<dict>>" or "<<dict>>\nstream\r\n...\r\nendstream" body, no "N G obj"/"endobj" wrapper)]
        foreach ($decryptedObjects as $objNum => $obj) {
            if ($obj['isObjStm']) {
                continue; // expanded below, not emitted itself
            }
            $content = $obj['dict'];
            if ($obj['streamBytes'] !== null) {
                $streamBytes = $obj['streamBytes'];
                $dict = $content;
                // smalot/pdfparser's FlateDecode handler treats an empty
                // *decoded* result as a decode FAILURE (it can't
                // distinguish "legitimately empty" from "broken"),
                // silently leaving a stream like this un-inflated — which
                // then corrupts a /Contents array that includes one of
                // these as a placeholder (iText emits them for otherwise-
                // blank pages/content). Detect that case ourselves and
                // emit it pre-inflated (empty, filter-less) instead — an
                // empty filter-less stream can't be mis-decoded.
                if (preg_match('/\/Filter\s*\/FlateDecode(?!\w)/', $dict) && strpos($dict, '/DecodeParms') === false && strpos($dict, '/DP') === false) {
                    try {
                        if (self::flateDecode($streamBytes) === '') {
                            $streamBytes = '';
                            $dict = preg_replace('/\/Filter\s*\/FlateDecode/', '', $dict, 1);
                        }
                    } catch (\Throwable $e) {
                        // Not actually empty-after-inflate — leave it compressed as-is.
                    }
                }
                $dict = preg_replace('/\/Length\s+(?:\d+\s+\d+\s+R|\d+)/', '/Length ' . strlen($streamBytes), $dict, 1);
                $content = $dict . "\nstream\r\n" . $streamBytes . "\r\nendstream";
            }
            $flattened[$objNum] = ['gen' => $obj['gen'], 'content' => $content];
        }
        foreach ($type2 as $memberObjNum => [$streamObjNum, $indexInStream]) {
            if (isset($flattened[$memberObjNum]) || !isset($decryptedObjects[$streamObjNum]) || $decryptedObjects[$streamObjNum]['streamBytes'] === null) {
                continue;
            }
            $members = self::inflateObjStm($decryptedObjects[$streamObjNum]);
            if (isset($members[$memberObjNum])) {
                $flattened[$memberObjNum] = ['gen' => 0, 'content' => $members[$memberObjNum]];
            }
        }

        return self::serializeFlatPdf($flattened, $meta['root'], $meta['id0']);
    }

    /**
     * Parses a cross-reference stream object at $offset into the
     * (type1, type2, meta) tuple decrypt/decryptModern() need. Cross-
     * reference streams are never encrypted (PDF 32000-1:2008 §7.5.8.2),
     * so only Flate decoding + PNG-predictor un-filtering is needed here.
     */
    private static function parseXrefStream(string $bytes, int $offset): array
    {
        if (!preg_match('/\G\s*(\d+)\s+(\d+)\s+obj\s*/A', $bytes, $hdr, 0, $offset)) {
            throw new PdfUnsupportedEncryptionException('Malformed cross-reference stream object.');
        }
        $bodyStart = $offset + strlen($hdr[0]);
        $streamPos = strpos($bytes, 'stream', $bodyStart);
        if ($streamPos === false) {
            throw new PdfUnsupportedEncryptionException('Cross-reference stream object has no stream.');
        }
        $dict = substr($bytes, $bodyStart, $streamPos - $bodyStart);

        if (!preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dict, $lm)) {
            throw new PdfUnsupportedEncryptionException('Cross-reference stream has an indirect /Length, which isn\'t supported.');
        }
        $length = (int) $lm[1];
        $dataStart = $streamPos + strlen('stream');
        if (substr($bytes, $dataStart, 2) === "\r\n") {
            $dataStart += 2;
        } elseif (substr($bytes, $dataStart, 1) === "\n" || substr($bytes, $dataStart, 1) === "\r") {
            $dataStart += 1;
        }
        $raw = substr($bytes, $dataStart, $length);

        $decoded = self::flateDecode($raw);
        if (preg_match('/\/DecodeParms\s*<<(.*?)>>/s', $dict, $dpm) || preg_match('/\/DP\s*<<(.*?)>>/s', $dict, $dpm)) {
            $predictor = preg_match('/\/Predictor\s+(\d+)/', $dpm[1], $pm) ? (int) $pm[1] : 1;
            $columns = preg_match('/\/Columns\s+(\d+)/', $dpm[1], $cm) ? (int) $cm[1] : 1;
            $colors = preg_match('/\/Colors\s+(\d+)/', $dpm[1], $com) ? (int) $com[1] : 1;
            $bpc = preg_match('/\/BitsPerComponent\s+(\d+)/', $dpm[1], $bm) ? (int) $bm[1] : 8;
            if ($predictor >= 10) {
                $decoded = self::pngPredictorDecode($decoded, $columns, $colors, $bpc);
            } elseif ($predictor !== 1) {
                throw new PdfUnsupportedEncryptionException('Unsupported cross-reference stream predictor.');
            }
        }

        if (!preg_match('/\/W\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s*\]/', $dict, $wm)) {
            throw new PdfUnsupportedEncryptionException('Cross-reference stream is missing /W.');
        }
        [$w1, $w2, $w3] = [(int) $wm[1], (int) $wm[2], (int) $wm[3]];
        $rowWidth = $w1 + $w2 + $w3;

        $size = preg_match('/\/Size\s+(\d+)/', $dict, $sm) ? (int) $sm[1] : 0;
        $index = [0, $size];
        if (preg_match('/\/Index\s*\[\s*([\d\s]+)\]/', $dict, $im)) {
            $index = array_map('intval', preg_split('/\s+/', trim($im[1])));
        }

        $type1 = [];
        $type2 = [];
        $pos = 0;
        for ($s = 0; $s + 1 < count($index); $s += 2) {
            $startNum = $index[$s];
            $count = $index[$s + 1];
            for ($n = 0; $n < $count; $n++) {
                if ($pos + $rowWidth > strlen($decoded)) {
                    break 2;
                }
                $objNum = $startNum + $n;
                $type = $w1 === 0 ? 1 : self::readBigEndianInt($decoded, $pos, $w1);
                $field2 = self::readBigEndianInt($decoded, $pos + $w1, $w2);
                $field3 = $w3 === 0 ? 0 : self::readBigEndianInt($decoded, $pos + $w1 + $w2, $w3);
                $pos += $rowWidth;

                if ($type === 1) {
                    $type1[$objNum] = $field2;
                } elseif ($type === 2) {
                    $type2[$objNum] = [$field2, $field3];
                }
                // type 0 (free) entries are skipped.
            }
        }

        $meta = [
            'root' => preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $dict, $rm) ? (int) $rm[1] : null,
            'encryptRef' => preg_match('/\/Encrypt\s+(\d+)\s+\d+\s+R/', $dict, $em) ? (int) $em[1] : null,
            'id0' => preg_match('/\/ID\s*\[\s*<([0-9A-Fa-f]*)>/', $dict, $idm) ? self::hexToBytes($idm[1]) : null,
        ];

        return [$type1, $type2, $meta];
    }

    /**
     * Expands a decrypted+inflated /Type/ObjStm object's members into
     * objNum => raw value text (e.g. "<< /Type /Page ... >>"), per PDF
     * 32000-1:2008 §7.5.7. Members are never streams themselves (a stream
     * can't be nested inside another stream's compact object format), so
     * no "N G obj"/"endobj" wrapper or further stream handling is needed.
     */
    private static function inflateObjStm(array $objStmObject): array
    {
        $n = preg_match('/\/N\s+(\d+)/', $objStmObject['dict'], $nm) ? (int) $nm[1] : 0;
        $first = preg_match('/\/First\s+(\d+)/', $objStmObject['dict'], $fm) ? (int) $fm[1] : 0;
        if ($n === 0) {
            return [];
        }

        $decoded = self::flateDecode($objStmObject['streamBytes']);
        $header = substr($decoded, 0, $first);
        if (!preg_match_all('/(\d+)\s+(\d+)/', $header, $pairs, PREG_SET_ORDER)) {
            return [];
        }
        $pairs = array_slice($pairs, 0, $n);

        $members = [];
        foreach ($pairs as $i => $pair) {
            $objNum = (int) $pair[1];
            $relOffset = (int) $pair[2];
            $start = $first + $relOffset;
            $end = isset($pairs[$i + 1]) ? $first + (int) $pairs[$i + 1][2] : strlen($decoded);
            $members[$objNum] = trim(substr($decoded, $start, max(0, $end - $start)));
        }
        return $members;
    }

    /**
     * Rebuilds a fresh, unencrypted PDF from a flat objNum => content map
     * (each content string already shaped like "<<dict>>" or "<<dict>>
     * stream\r\n...\r\nendstream", without the "N G obj"/"endobj"
     * wrapper). Uses a plain classic xref table + trailer — no
     * cross-reference stream, no object streams — so any standard reader
     * (smalot/pdfparser included) can parse it without needing to
     * understand modern PDF structure at all.
     */
    private static function serializeFlatPdf(array $flattened, int $rootObjNum, string $id0): string
    {
        ksort($flattened);
        $maxObjNum = empty($flattened) ? 0 : max(array_keys($flattened));

        $out = "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($flattened as $objNum => $obj) {
            $offsets[$objNum] = strlen($out);
            $out .= $objNum . ' ' . $obj['gen'] . " obj\n" . $obj['content'] . "\nendobj\n";
        }

        $startxref = strlen($out);
        $out .= "xref\n0 " . ($maxObjNum + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $maxObjNum; $n++) {
            if (isset($offsets[$n])) {
                $out .= sprintf("%010d %05d n \n", $offsets[$n], $flattened[$n]['gen']);
            } else {
                $out .= "0000000000 00000 f \n";
            }
        }

        $idHex = bin2hex($id0);
        $out .= "trailer\n<< /Size " . ($maxObjNum + 1) . ' /Root ' . $rootObjNum . " 0 R /ID [<$idHex><$idHex>] >>\n";
        $out .= "startxref\n" . $startxref . "\n%%EOF";

        return $out;
    }

    private static function decryptStreamBytes(string $cipherText, string $fileKey, int $objNum, int $gen, string $cfm): string
    {
        if ($cfm === 'AESV2') {
            if (strlen($cipherText) < 16) {
                return '';
            }
            $objKey = self::aesObjectKey($fileKey, $objNum, $gen);
            $iv = substr($cipherText, 0, 16);
            $data = substr($cipherText, 16);
            $plain = openssl_decrypt($data, 'aes-128-cbc', $objKey, OPENSSL_RAW_DATA, $iv);
            if ($plain === false) {
                throw new PdfUnsupportedEncryptionException('AES decryption failed for object ' . $objNum . ' — file may be corrupt or use an unsupported variant.');
            }
            return $plain;
        }
        // 'V2' — plain RC4, same per-object key derivation as the classic path.
        return self::rc4(self::objectKey($fileKey, $objNum, $gen), $cipherText);
    }

    /**
     * PDF 32000-1:2008 Algorithm 1, step (f): AES object keys extend the
     * base per-object key with the 4-byte "sAlT" suffix before hashing.
     */
    private static function aesObjectKey(string $baseKey, int $objNum, int $gen): string
    {
        $input = $baseKey
            . chr($objNum & 0xFF) . chr(($objNum >> 8) & 0xFF) . chr(($objNum >> 16) & 0xFF)
            . chr($gen & 0xFF) . chr(($gen >> 8) & 0xFF)
            . "sAlT";
        $hash = md5($input, true);
        $n = min(strlen($baseKey) + 5, 16);
        return substr($hash, 0, $n);
    }

    private static function readBigEndianInt(string $data, int $offset, int $len): int
    {
        $value = 0;
        for ($i = 0; $i < $len; $i++) {
            $value = ($value << 8) | ord($data[$offset + $i]);
        }
        return $value;
    }

    private static function flateDecode(string $data): string
    {
        $result = @gzuncompress($data);
        if ($result === false) {
            // Some writers omit/mangle the 2-byte zlib header — fall back
            // to raw DEFLATE (skip the header if present, else assume raw).
            $offset = (strlen($data) >= 2 && (ord($data[0]) & 0x0F) === 8) ? 2 : 0;
            $result = @gzinflate(substr($data, $offset));
        }
        if ($result === false) {
            throw new PdfUnsupportedEncryptionException('Could not inflate a Flate-encoded stream.');
        }
        return $result;
    }

    /**
     * General PNG predictor (types 10-15) un-filtering, per PDF
     * 32000-1:2008 §7.4.4.4 / RFC 2083 §6. Each output row is preceded by
     * a 1-byte filter-type tag; bytesPerPixel governs the Sub/Average/
     * Paeth filters' lookback distance.
     */
    private static function pngPredictorDecode(string $data, int $columns, int $colors, int $bpc): string
    {
        $rowBytes = (int) ceil(($colors * $bpc * $columns) / 8);
        $bpp = max(1, (int) ceil(($colors * $bpc) / 8));
        $out = '';
        $prev = str_repeat("\0", $rowBytes);
        $pos = 0;
        $len = strlen($data);

        while ($pos + 1 + $rowBytes <= $len) {
            $filterType = ord($data[$pos]);
            $row = substr($data, $pos + 1, $rowBytes);
            $pos += 1 + $rowBytes;

            $decoded = '';
            for ($i = 0; $i < $rowBytes; $i++) {
                $x = ord($row[$i]);
                $a = $i >= $bpp ? ord($decoded[$i - $bpp]) : 0;
                $b = ord($prev[$i]);
                $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
                switch ($filterType) {
                    case 0:
                        $val = $x;
                        break;
                    case 1:
                        $val = $x + $a;
                        break;
                    case 2:
                        $val = $x + $b;
                        break;
                    case 3:
                        $val = $x + intdiv($a + $b, 2);
                        break;
                    case 4:
                        $p = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);
                        $pr = ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
                        $val = $x + $pr;
                        break;
                    default:
                        throw new PdfUnsupportedEncryptionException('Unsupported PNG predictor filter type ' . $filterType . '.');
                }
                $decoded .= chr($val & 0xFF);
            }
            $out .= $decoded;
            $prev = $decoded;
        }

        return $out;
    }
}
