<?php
/**
 * lead.php: nimmt Anfragen von beraterwechsel.ch entgegen und schickt sie per E-Mail.
 * Voraussetzung: PHP 7.4+ mit funktionierendem mail() (oder auf SMTP umstellen, z. B. PHPMailer).
 * Drei Arten von Nachrichten: lead (Kontakt), details (Vertragsangaben), mandate (unterschriebenes Mandat).
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$TO        = 'jetzt@beraterwechsel.ch';   // Empfänger der Anfragen
$FROM      = 'anfrage@beraterwechsel.ch'; // Absender (muss zur Domain passen, sonst landet die Mail im Spam)
$SEND_AUTO = true;                        // Bestätigungsmail an den Kunden bei neuer Anfrage

function out(array $a, int $code = 200): void { http_response_code($code); echo json_encode($a); exit; }
function clean($v, int $max = 500): string { return mb_substr(trim(strip_tags((string)$v)), 0, $max); }
function line(string $k, $v): string { if (is_array($v)) $v = implode(', ', array_map('strval', $v)); return $k . ': ' . ($v === '' ? '-' : $v) . "\n"; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false], 405);
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 3000000) out(['ok' => false], 413);
$d = json_decode($raw, true);
if (!is_array($d)) out(['ok' => false], 400);

// Spam-Schutz: verstecktes Feld muss leer sein
if (!empty($d['hp'])) out(['ok' => true, 'id' => 'x']);

// Einfache Begrenzung: max. 10 Anfragen pro Stunde und IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rl = sys_get_temp_dir() . '/bw_rl_' . md5($ip);
$hits = is_file($rl) ? array_filter(explode(',', (string)file_get_contents($rl)), fn($t) => (int)$t > time() - 3600) : [];
if (count($hits) >= 10) out(['ok' => false], 429);
$hits[] = time(); @file_put_contents($rl, implode(',', $hits));

$type = in_array($d['type'] ?? '', ['lead', 'details', 'mandate'], true) ? $d['type'] : 'lead';
$id   = preg_match('/^[a-z0-9\-]{4,40}$/i', (string)($d['lead_id'] ?? '')) ? $d['lead_id'] : bin2hex(random_bytes(4));
$email = filter_var(clean($d['email'] ?? '', 200), FILTER_VALIDATE_EMAIL) ?: '';
$name  = clean(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''), 120);
if ($type === 'lead' && ($name === '' || $email === '' || clean($d['phone'] ?? '', 40) === '')) out(['ok' => false], 422);

$meta = is_array($d['meta'] ?? null) ? $d['meta'] : [];
$attr = is_array($meta['attr'] ?? null) ? $meta['attr'] : [];

$body  = "Anfrage-Nr.: $id\nArt: $type\nZeit: " . date('c') . "\n\n";
foreach (['first_name' => 'Vorname', 'last_name' => 'Nachname', 'phone' => 'Telefon', 'email' => 'E-Mail', 'language' => 'Sprache',
          'contact_preference' => 'Kontaktweg', 'reasons' => 'Gründe', 'insurance_types' => 'Versicherungen',
          'insurer' => 'Gesellschaft', 'policy_numbers' => 'Policennummer(n)', 'birth_date' => 'Geburtsdatum',
          'street' => 'Strasse', 'postcode' => 'PLZ', 'city' => 'Ort', 'areas' => 'Bereiche', 'reporting' => 'Reporting',
          'signed_at' => 'Unterschrieben am'] as $k => $label) {
    if (isset($d[$k])) $body .= line($label, is_array($d[$k]) ? array_map(fn($x) => clean($x, 80), $d[$k]) : clean($d[$k]));
}
if (!empty($d['mandate_accepted'])) $body .= "Mandat gelesen und erteilt: ja\n";
if (!empty($d['consent_forward']))  $body .= "Einwilligung zur Weitergabe an die Wertenburg GmbH: ja\n";
$body .= "\n-- Herkunft --\n" . line('Variante', clean($meta['variant'] ?? '', 60)) . line('Seite', clean($meta['page'] ?? '', 200)) . line('Referrer', clean($meta['referrer'] ?? '', 200));
foreach ($attr as $k => $v) $body .= line(clean($k, 40), clean($v, 200));

$subject = '=?UTF-8?B?' . base64_encode("beraterwechsel.ch: $type $name #$id") . '?=';
$headers = "From: $FROM\r\nMIME-Version: 1.0\r\n";
if ($email) $headers .= 'Reply-To: ' . str_replace(["\r", "\n"], '', $email) . "\r\n";

// Unterschrift als PNG anhängen
$sig = (string)($d['signature_png'] ?? '');
if ($type === 'mandate' && strpos($sig, 'data:image/png;base64,') === 0) {
    $png = base64_decode(substr($sig, 22), true);
    if ($png !== false && strlen($png) < 1500000) {
        $b = 'bw' . bin2hex(random_bytes(6));
        $headers .= "Content-Type: multipart/mixed; boundary=\"$b\"\r\n";
        $msg  = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$body\r\n";
        $msg .= "--$b\r\nContent-Type: image/png; name=\"unterschrift-$id.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"unterschrift-$id.png\"\r\n\r\n" . chunk_split(base64_encode($png)) . "--$b--";
        $ok = mail($TO, $subject, $msg, $headers);
        out(['ok' => (bool)$ok, 'id' => $id], $ok ? 200 : 500);
    }
}
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$ok = mail($TO, $subject, $body, $headers);

// Bestätigung an den Kunden
if ($ok && $SEND_AUTO && $type === 'lead' && $email) {
    $txt = "Guten Tag " . clean($d['first_name'] ?? '', 60) . "\n\nvielen Dank für Ihre Anfrage bei beraterwechsel.ch. Wir melden uns persönlich bei Ihnen.\n\nLieber persönlich? Sie erreichen uns telefonisch oder per WhatsApp über beraterwechsel.ch\n\nFreundliche Grüsse\nberaterwechsel.ch\n";
    @mail($email, '=?UTF-8?B?' . base64_encode('Ihre Anfrage bei beraterwechsel.ch') . '?=', $txt, "From: $FROM\r\nContent-Type: text/plain; charset=UTF-8\r\n");
}
out(['ok' => (bool)$ok, 'id' => $id], $ok ? 200 : 500);
