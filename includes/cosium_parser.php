<?php

declare(strict_types=1);

function run_local_process(array $command): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Impossible de lancer le lecteur PDF local.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'stdout' => $stdout ?: '', 'stderr' => $stderr ?: ''];
}

function local_tool_candidates(string $tool, string $configuredPath = ''): array
{
    $executable = $tool . '.exe';
    $candidates = [];
    $add = static function (?string $path) use (&$candidates): void {
        $path = trim((string) $path, " \t\n\r\0\x0B\"");
        if ($path !== '') {
            $candidates[] = str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
    };

    $add($configuredPath);
    $add(getenv('RAJA_' . mb_strtoupper($tool)) ?: '');

    $localAppData = getenv('LOCALAPPDATA') ?: '';
    $userProfile = getenv('USERPROFILE') ?: '';
    if ($localAppData === '' && $userProfile !== '') {
        $localAppData = rtrim($userProfile, '\\/') . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local';
    }

    if ($tool === 'tesseract') {
        $add($localAppData !== '' ? $localAppData . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Tesseract-OCR' . DIRECTORY_SEPARATOR . $executable : '');
        $add((getenv('ProgramFiles') ?: 'C:\\Program Files') . DIRECTORY_SEPARATOR . 'Tesseract-OCR' . DIRECTORY_SEPARATOR . $executable);
        $add((getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)') . DIRECTORY_SEPARATOR . 'Tesseract-OCR' . DIRECTORY_SEPARATOR . $executable);
        foreach (glob('C:/Users/*/AppData/Local/Programs/Tesseract-OCR/' . $executable) ?: [] as $match) {
            $add($match);
        }
    } else {
        if ($localAppData !== '') {
            $pattern = str_replace('\\', '/', rtrim($localAppData, '\\/'))
                . '/Microsoft/WinGet/Packages/oschwartz10612.Poppler_*/poppler-*/Library/bin/' . $executable;
            foreach (glob($pattern) ?: [] as $match) {
                $add($match);
            }
        }
        foreach (glob('C:/Users/*/AppData/Local/Microsoft/WinGet/Packages/oschwartz10612.Poppler_*/poppler-*/Library/bin/' . $executable) ?: [] as $match) {
            $add($match);
        }
    }

    $where = rtrim((string) (getenv('WINDIR') ?: 'C:\\Windows'), '\\/') . DIRECTORY_SEPARATOR . 'System32' . DIRECTORY_SEPARATOR . 'where.exe';
    if (is_file($where)) {
        $result = run_local_process([$where, $executable]);
        if ($result['code'] === 0) {
            foreach (preg_split('/\R/u', trim($result['stdout'])) ?: [] as $match) {
                $add($match);
            }
        }
    }

    return array_values(array_unique($candidates));
}

function resolve_local_tool(string $tool, string $configuredPath = ''): ?string
{
    foreach (local_tool_candidates($tool, $configuredPath) as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function normalize_cosium_text(string $text): string
{
    $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
    return trim($text);
}

function extract_cosium_pdf(string $pdfPath): array
{
    $tools = app_config('tools') ?? [];
    $resolvedTools = [
        'pdftotext' => resolve_local_tool('pdftotext', (string) ($tools['pdftotext'] ?? '')),
        'pdftoppm' => resolve_local_tool('pdftoppm', (string) ($tools['pdftoppm'] ?? '')),
        'tesseract' => resolve_local_tool('tesseract', (string) ($tools['tesseract'] ?? '')),
    ];
    $missingTools = array_keys(array_filter($resolvedTools, static fn(?string $path): bool => $path === null));
    if ($missingTools) {
        throw new RuntimeException('Le lecteur PDF/OCR local n’est pas configuré. Outil(s) introuvable(s) : ' . implode(', ', $missingTools) . '. Redémarrez Apache après l’installation ou renseignez les chemins dans config/config.php.');
    }
    $pdftotext = (string) $resolvedTools['pdftotext'];
    $pdftoppm = (string) $resolvedTools['pdftoppm'];
    $tesseract = (string) $resolvedTools['tesseract'];

    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'raja_cosium_' . bin2hex(random_bytes(8));
    if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
        throw new RuntimeException('Impossible de préparer le PDF.');
    }
    $textPath = $tempDir . DIRECTORY_SEPARATOR . 'source.txt';
    $method = 'texte PDF';
    try {
        $result = run_local_process([$pdftotext, '-layout', '-enc', 'UTF-8', $pdfPath, $textPath]);
        $text = is_file($textPath) ? (string) file_get_contents($textPath) : '';
        if ($result['code'] !== 0 || mb_strlen(trim($text)) < 80) {
            $method = 'OCR local';
            $prefix = $tempDir . DIRECTORY_SEPARATOR . 'page';
            $render = run_local_process([$pdftoppm, '-png', '-r', '200', $pdfPath, $prefix]);
            if ($render['code'] !== 0) {
                throw new RuntimeException('Le PDF Cosium n’a pas pu être converti en images.');
            }
            $parts = [];
            $images = glob($prefix . '-*.png') ?: [];
            sort($images, SORT_NATURAL);
            foreach ($images as $image) {
                $ocr = run_local_process([$tesseract, $image, 'stdout', '-l', 'fra+eng', '--psm', '6']);
                if ($ocr['code'] === 0) {
                    $parts[] = $ocr['stdout'];
                }
            }
            $text = implode("\n\n", $parts);
        }
        $text = normalize_cosium_text($text);
        if (mb_strlen($text) < 40) {
            throw new RuntimeException('Aucun texte exploitable n’a été détecté dans ce PDF.');
        }
        return ['text' => $text, 'method' => $method, 'parsed' => parse_cosium_text($text)];
    } finally {
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($tempDir);
    }
}

function cosium_match(string $pattern, string $text): ?string
{
    if (!preg_match($pattern, $text, $matches)) {
        return null;
    }
    $value = trim((string) ($matches[1] ?? ''));
    return $value === '' || $value === '-' ? null : $value;
}

function cosium_date(?string $value): ?string
{
    if (!$value) return null;
    $date = DateTimeImmutable::createFromFormat('!d/m/Y', trim($value));
    return $date ? $date->format('Y-m-d') : null;
}

function split_cosium_name(?string $fullName): array
{
    $fullName = trim((string) $fullName);
    $fullName = preg_replace('/^(?:M|MR|MME|MLLE|MW)\.?\s+/iu', '', $fullName) ?? $fullName;
    $fullName = preg_replace('/\s+/', ' ', $fullName) ?? $fullName;
    if ($fullName === '') return ['nom' => '', 'prenom' => ''];
    $parts = explode(' ', $fullName);
    $nom = array_shift($parts) ?: '';
    return ['nom' => mb_strtoupper($nom), 'prenom' => implode(' ', $parts)];
}

function parse_cosium_text(string $text): array
{
    $nameRaw = cosium_match('/\bNom\s*:\s*(.+?)(?=\s+(?:Num[eé]ro|N[ée]\s+le)\s*:|\n|$)/iu', $text);
    $name = split_cosium_name($nameRaw);
    $phone = cosium_match('/T[eé]l\s+Port\.?\s*:\s*([^\n]+)/iu', $text)
        ?? cosium_match('/T[eé]l\s+Dom\.?\s*:\s*([^\n]+)/iu', $text);
    $address = cosium_match('/Adresse\s*:\s*([^\n]+)/iu', $text);
    if ($address && preg_match('/^(?:Courrier|Facturation)(?:\s*,\s*(?:Courrier|Facturation))*$/iu', $address)) {
        $address = null;
    }
    $social = cosium_match('/N[°o]\s*S[.S]?\s*:\s*([0-9 ]{10,30})/iu', $text);
    $social = $social ? preg_replace('/\D+/', '', $social) : null;

    $dossiers = [];
    if (preg_match_all('/Dossiers\s+([A-ZÀ-ÖØ-Þ ]+)(.*?)(?=Dossiers\s+[A-ZÀ-ÖØ-Þ ]+|\z)/isu', $text, $sections, PREG_SET_ORDER)) {
        foreach ($sections as $section) {
            $type = trim(preg_replace('/\s+/', ' ', $section[1]) ?? $section[1]);
            $body = $section[2];
            if (preg_match_all('/Dossier\s+du\s*:?[ \t]*(\d{2}\/\d{2}\/\d{4})(.*?)(?=Dossier\s+du|\z)/isu', $body, $records, PREG_SET_ORDER)) {
                foreach ($records as $record) {
                    $details = trim(preg_replace('/\s+/', ' ', $record[2]) ?? $record[2]);
                    $dossiers[] = [
                        'type' => mb_strtoupper($type),
                        'date' => cosium_date($record[1]),
                        'details' => mb_substr($details, 0, 5000),
                    ];
                }
            }
        }
    }

    return [
        'nom' => $name['nom'],
        'prenom' => $name['prenom'],
        'telephone' => $phone,
        'email' => cosium_match('/e-?mail\s*:\s*([^\s\n]+@[^\s\n]+)/iu', $text),
        'notes' => cosium_match('/Commentaire\s*:\s*(.*?)(?=\n?Origine\s*:)/isu', $text),
        'cosium_numero' => cosium_match('/Num[eé]ro\s*:\s*([0-9]+)/iu', $text),
        'date_naissance' => cosium_date(cosium_match('/N[ée]\s+le\s*:\s*(\d{2}\/\d{2}\/\d{4})/iu', $text)),
        'adresse' => $address,
        'caisse_secu' => cosium_match('/Caisse\s+s[eé]cu\s*:\s*([^\n]+)/iu', $text),
        'numero_securite_sociale' => $social,
        'complementaire' => cosium_match('/Compl[eé]mentaire\s*:\s*(.*?)(?=\s+Relation\s*:|\n|$)/iu', $text),
        'assure_nom' => cosium_match('/Assur[eé]\s*:\s*([^\n]+)/iu', $text),
        'date_creation_cosium' => cosium_date(cosium_match('/Cr[eé][eé]\s+le\s*:\s*(\d{2}\/\d{2}\/\d{4})/iu', $text)),
        'dossiers' => $dossiers,
    ];
}
