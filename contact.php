<?php
/**
 * Formulaire « Demande de rappel » du site vitrine gourmetdistri.fr.
 *
 * DÉPÔT PUBLIC : aucun mot de passe ici. Les identifiants d'envoi (adresse
 * ne-pas-repondre@gourmetdistri.fr) sont lus sur le serveur, hors du site :
 *   1. /home/gourmetdin/prive/site-vitrine/config.php  (facultatif, propre au site vitrine)
 *   2. sinon /home/gourmetdin/prive/appli-prod/config.php (configuration de l'appli de commande,
 *      lue sans jamais être modifiée : un changement de mot de passe fait dans l'appli vaut aussi ici)
 *
 * Envoi authentifié par le serveur SMTP d'OVH (ssl0.ovh.net:465), comme l'appli de commande :
 * les e-mails envoyés par la fonction mail() de l'hébergement sont refusés par Gmail et Yahoo.
 *
 * Chaque demande est aussi copiée dans /home/gourmetdin/prive/site-vitrine/demandes/
 * (un fichier par mois, effacé au bout de 3 ans) : aucune demande n'est perdue si l'envoi échoue.
 */
declare(strict_types=1);

const DESTINATAIRE = 'contact@gourmetdistri.fr';
const TELEPHONE = '06 47 90 70 21';
const TYPES = ['Restaurant', 'Brasserie, bistrot', 'Traiteur', 'Boulangerie', 'Hôtel', 'Restauration collective', 'Commerce de bouche', 'Autre'];
const LIMITE_PAR_HEURE = 5;

$prive = dirname(__DIR__) . '/prive';
$dossier = $prive . '/site-vitrine';
$json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

/** Identifiants d'envoi lus hors du site (jamais dans le dépôt). */
function configEnvoi(string $dossier, string $prive): ?array
{
    foreach ([$dossier . '/config.php', $prive . '/appli-prod/config.php'] as $fichier) {
        if (is_file($fichier) && is_readable($fichier)) {
            $cfg = require $fichier;
            if (is_array($cfg) && !empty($cfg['smtp']['user']) && !empty($cfg['smtp']['password'])) {
                return $cfg['smtp'];
            }
        }
    }
    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Contrôle sans envoi : https://gourmetdistri.fr/contact.php?verif
    if (isset($_GET['verif'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'envoi_configure' => configEnvoi($dossier, $prive) !== null,
            'copie_de_secours_possible' => is_dir($dossier) ? is_writable($dossier) : is_writable($prive),
        ]);
        exit;
    }
    header('Location: /#contact', true, 303);
    exit;
}

/* ------------------------------------------------------------------ lecture */
function champ(string $nom, int $max): string
{
    $v = isset($_POST[$nom]) && is_string($_POST[$nom]) ? $_POST[$nom] : '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim($v);
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}
function ligne(string $v): string
{
    return trim(preg_replace('/\s+/u', ' ', $v) ?? '');
}

$d = [
    'nom' => ligne(champ('nom', 120)),
    'etablissement' => ligne(champ('etablissement', 160)),
    'telephone' => ligne(champ('telephone', 30)),
    'email' => ligne(champ('email', 160)),
    'type' => ligne(champ('type', 60)),
    'ville' => ligne(champ('ville', 120)),
    'besoins' => champ('besoins', 3000),
    'origine' => ligne(champ('origine', 120)),
];
$piege = champ('site_web', 200);
$duree = champ('duree', 10);

function repondre(bool $ok, string $message, bool $json): void
{
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ok) {
        header('Location: /merci/', true, 303);
        exit;
    }
    http_response_code(422);
    header('Content-Type: text/html; charset=utf-8');
    $m = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>Demande non envoyée · Gourmet Distri</title></head>'
        . '<body style="margin:0;background:#F6F0E1;color:#12251E;font:17px/1.6 Montserrat,system-ui,sans-serif">'
        . '<main style="max-width:620px;margin:0 auto;padding:80px 24px"><h1 style="font-family:Georgia,serif;font-weight:400;color:#1C3C30">Votre demande n\'est pas partie</h1>'
        . '<p>' . $m . '</p><p><a href="/#contact" style="color:#1C3C30;font-weight:600">Revenir au formulaire</a> ou appeler le '
        . '<a href="tel:+33647907021" style="color:#1C3C30;font-weight:600">' . TELEPHONE . '</a>.</p></main></body></html>';
    exit;
}

/* ------------------------------------------------------------------ robots */
// Champ invisible rempli, ou formulaire rempli en moins de 3 secondes : on fait comme si tout allait bien.
if ($piege !== '' || ($duree !== '' && ctype_digit($duree) && (int) $duree < 3)) {
    repondre(true, '', $json);
}

/* ------------------------------------------------------------------ contrôles */
$manque = [];
foreach (['nom' => 'nom et prénom', 'etablissement' => 'établissement', 'telephone' => 'téléphone', 'email' => 'e-mail', 'type' => "type d'établissement", 'ville' => 'code postal et ville'] as $k => $libelle) {
    if ($d[$k] === '') {
        $manque[] = $libelle;
    }
}
if ($manque) {
    repondre(false, 'Merci de compléter : ' . implode(', ', $manque) . '.', $json);
}
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
    repondre(false, "L'adresse e-mail semble incorrecte. Vérifiez-la, s'il vous plaît.", $json);
}
if (strlen(preg_replace('/\D/', '', $d['telephone']) ?? '') < 9) {
    repondre(false, 'Le numéro de téléphone semble incomplet. Vérifiez-le, s\'il vous plaît.', $json);
}
if (!in_array($d['type'], TYPES, true)) {
    $d['type'] = 'Autre';
}
if (preg_match_all('#https?://|www\.#i', $d['besoins']) > 2) {
    repondre(true, '', $json); // message publicitaire
}

/* ------------------------------------------------------------------ configuration privée */
$smtp = configEnvoi($dossier, $prive);

/* ------------------------------------------------------------------ limite par visiteur */
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$sel = $smtp ? hash('sha256', (string) $smtp['password']) : 'gd';
if (is_dir($dossier . '/limites') || @mkdir($dossier . '/limites', 0700, true)) {
    $f = $dossier . '/limites/' . hash('sha256', $sel . '|' . $ip) . '.txt';
    $maintenant = time();
    $traces = is_file($f) ? array_filter(array_map('intval', explode(',', (string) file_get_contents($f))), function ($t) use ($maintenant) {
        return $t > $maintenant - 3600;
    }) : [];
    if (count($traces) >= LIMITE_PAR_HEURE) {
        repondre(false, 'Vous avez déjà envoyé plusieurs demandes. Pour aller plus vite, appelez-nous au ' . TELEPHONE . '.', $json);
    }
    $traces[] = $maintenant;
    @file_put_contents($f, implode(',', $traces), LOCK_EX);
    // Rien ne traîne : les traces de plus d'un jour sont effacées
    foreach (glob($dossier . '/limites/*.txt') ?: [] as $vieux) {
        if (@filemtime($vieux) < $maintenant - 86400) {
            @unlink($vieux);
        }
    }
}

/* ------------------------------------------------------------------ copie de sécurité */
date_default_timezone_set('Europe/Paris');
$quand = date('d/m/Y à H:i');
if (is_dir($dossier . '/demandes') || @mkdir($dossier . '/demandes', 0700, true)) {
    @file_put_contents($dossier . '/demandes/' . date('Y-m') . '.jsonl', json_encode(['date' => date('c')] + $d, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    foreach (glob($dossier . '/demandes/*.jsonl') ?: [] as $ancien) {
        if (basename($ancien, '.jsonl') < date('Y-m', strtotime('-36 months'))) {
            @unlink($ancien);
        }
    }
}

/* ------------------------------------------------------------------ e-mails */
function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function enveloppe(string $titre, string $contenu): string
{
    return '<!doctype html><html lang="fr"><body style="margin:0;padding:0;background:#EEF1EC;font-family:Arial,Helvetica,sans-serif;color:#16241D">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#EEF1EC;padding:24px 0"><tr><td align="center">'
        . '<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;width:100%;background:#FFFFFF;border-radius:10px;overflow:hidden">'
        . '<tr><td style="background:#1C3C30;padding:18px 24px;color:#EFD28E;font-family:Georgia,serif;font-size:20px;font-weight:bold">Gourmet Distri</td></tr>'
        . '<tr><td style="padding:24px"><h1 style="font-family:Georgia,serif;font-size:22px;color:#1C3C30;margin:0 0 14px">' . h($titre) . '</h1>' . $contenu . '</td></tr>'
        . '<tr><td style="padding:16px 24px;background:#F6F8F5;color:#4A5A51;font-size:13px">Gourmet Distri · 2 rue de l\'Avenir, 91350 Grigny · ' . TELEPHONE . ' · contact@gourmetdistri.fr</td></tr>'
        . '</table></td></tr></table></body></html>';
}
function tableau(array $lignes): string
{
    $t = '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;font-size:15px">';
    foreach ($lignes as $libelle => $valeur) {
        $t .= '<tr><td style="padding:8px 12px 8px 0;border-top:1px solid #E3E8E1;color:#4A5A51;vertical-align:top;white-space:nowrap">' . h($libelle)
            . '</td><td style="padding:8px 0;border-top:1px solid #E3E8E1;vertical-align:top">' . $valeur . '</td></tr>';
    }
    return $t . '</table>';
}

/** Envoi SMTP authentifié (même méthode que l'appli de commande). */
function envoyer(array $smtp, string $a, string $sujet, string $texte, string $html, string $nomExpediteur, string $repondreA): bool
{
    $hote = (string) ($smtp['host'] ?? 'ssl0.ovh.net');
    $port = (int) ($smtp['port'] ?? 465);
    $user = (string) $smtp['user'];
    $pass = (string) $smtp['password'];
    $enc = function (string $t): string {
        return preg_match('/[^\x20-\x7E]/', $t) ? '=?UTF-8?B?' . base64_encode($t) . '?=' : $t;
    };
    $domaine = substr((string) strrchr($user, '@'), 1) ?: 'gourmetdistri.fr';
    $b = 'gd' . bin2hex(random_bytes(12));
    $entetes = [
        'Date: ' . date('r'),
        'From: ' . $enc($nomExpediteur) . " <{$user}>",
        "To: <{$a}>",
        'Subject: ' . $enc($sujet),
        'Message-ID: <' . bin2hex(random_bytes(16)) . "@{$domaine}>",
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$b}\"",
    ];
    if ($repondreA !== '' && filter_var($repondreA, FILTER_VALIDATE_EMAIL)) {
        $entetes[] = "Reply-To: <{$repondreA}>";
    }
    $corps = "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texte))
        . "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--{$b}--\r\n";
    $message = implode("\r\n", $entetes) . "\r\n\r\n" . $corps;

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $s = @stream_socket_client("ssl://{$hote}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if ($s === false) {
        error_log("[Site vitrine] SMTP injoignable : {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($s, 20);
    $attendre = function (int $code) use ($s): void {
        $r = '';
        while (($l = fgets($s, 515)) !== false) {
            $r .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        if ((int) substr($r, 0, 3) !== $code) {
            throw new RuntimeException(trim($r) . " (attendu {$code})");
        }
    };
    $cmd = function (string $l, int $code) use ($s, $attendre): void {
        fwrite($s, $l . "\r\n");
        $attendre($code);
    };
    try {
        $attendre(220);
        $cmd('EHLO ' . $domaine, 250);
        $cmd('AUTH LOGIN', 334);
        $cmd(base64_encode($user), 334);
        $cmd(base64_encode($pass), 235);
        $cmd("MAIL FROM:<{$user}>", 250);
        $cmd("RCPT TO:<{$a}>", 250);
        $cmd('DATA', 354);
        fwrite($s, preg_replace('/^\./m', '..', $message) . "\r\n.\r\n");
        $attendre(250);
        $cmd('QUIT', 221);
        return true;
    } catch (Throwable $e) {
        error_log('[Site vitrine] Envoi à ' . $a . ' refusé : ' . $e->getMessage());
        return false;
    } finally {
        fclose($s);
    }
}

if ($smtp === null) {
    error_log('[Site vitrine] Configuration d\'envoi introuvable : demande gardée dans prive/site-vitrine/demandes');
    repondre(false, "L'envoi n'a pas abouti pour une raison technique. Votre demande a été notée ; pour aller plus vite, appelez-nous au " . TELEPHONE . '.', $json);
}

$telLien = preg_replace('/[^\d+]/', '', $d['telephone']);
if (strpos($telLien, '0') === 0) {
    $telLien = '+33' . substr($telLien, 1);
}
$origine = $d['origine'] !== '' ? $d['origine'] : 'Non renseignée (envoi sans JavaScript)';

// 1. Au service commercial
$lignes = [
    'Nom et prénom' => h($d['nom']),
    'Établissement' => '<strong>' . h($d['etablissement']) . '</strong>',
    'Type' => h($d['type']),
    'Code postal et ville' => h($d['ville']),
    'Téléphone' => '<a href="tel:' . h($telLien) . '" style="color:#1C3C30;font-weight:bold">' . h($d['telephone']) . '</a>',
    'E-mail' => '<a href="mailto:' . h($d['email']) . '" style="color:#1C3C30">' . h($d['email']) . '</a>',
    'Besoins' => $d['besoins'] !== '' ? nl2br(h($d['besoins'])) : '<em>Non précisés</em>',
    'Origine' => h($origine),
    'Reçue le' => h($quand),
];
$texte1 = "Nouvelle demande de rappel reçue sur gourmetdistri.fr le {$quand}\n\n";
foreach (['Nom et prénom' => 'nom', 'Établissement' => 'etablissement', 'Type' => 'type', 'Code postal et ville' => 'ville', 'Téléphone' => 'telephone', 'E-mail' => 'email', 'Besoins' => 'besoins'] as $lib => $k) {
    $texte1 .= $lib . ' : ' . ($d[$k] !== '' ? $d[$k] : '-') . "\n";
}
$texte1 .= 'Origine : ' . $origine . "\n\nRépondre à cet e-mail écrit directement au prospect.\n";
$html1 = enveloppe('Nouvelle demande de rappel', '<p style="margin:0 0 16px">Un prospect demande à être rappelé par le service commercial.</p>' . tableau($lignes)
    . '<p style="margin:18px 0 0;font-size:13px;color:#4A5A51">Répondre à cet e-mail écrit directement au prospect.</p>');
$ok1 = envoyer($smtp, DESTINATAIRE, 'Demande de rappel : ' . $d['etablissement'] . ' (' . $d['ville'] . ')', $texte1, $html1, 'Site gourmetdistri.fr', $d['email']);

if (!$ok1) {
    repondre(false, "L'envoi n'a pas abouti pour une raison technique. Votre demande a été notée ; pour aller plus vite, appelez-nous au " . TELEPHONE . '.', $json);
}

// 2. Accusé de réception au prospect
$prenom = $d['nom'];
$recap = tableau([
    'Établissement' => h($d['etablissement']),
    'Type' => h($d['type']),
    'Ville' => h($d['ville']),
    'Téléphone' => h($d['telephone']),
    'Vos besoins' => $d['besoins'] !== '' ? nl2br(h($d['besoins'])) : '<em>Non précisés</em>',
]);
$html2 = enveloppe('Votre demande est bien arrivée', '<p style="margin:0 0 12px">Bonjour ' . h($prenom) . ',</p>'
    . '<p style="margin:0 0 12px">Merci de l\'intérêt que vous portez à Gourmet Distri. Votre demande est entre les mains de notre service commercial : un interlocuteur vous rappelle pour faire le point sur vos besoins et vous présenter nos conditions.</p>'
    . '<p style="margin:0 0 18px">Besoin d\'une réponse rapide ? Appelez-nous au <a href="tel:+33647907021" style="color:#1C3C30;font-weight:bold">' . TELEPHONE . '</a>, du lundi au vendredi de 8 h à 16 h.</p>'
    . '<p style="margin:0 0 8px;font-weight:bold;color:#1C3C30">Votre demande</p>' . $recap
    . '<p style="margin:22px 0 0">À très bientôt,<br>Adrien et Romain, Gourmet Distri</p>');
$texte2 = "Bonjour {$prenom},\n\nMerci de l'intérêt que vous portez à Gourmet Distri. Votre demande est entre les mains de notre service commercial : "
    . "un interlocuteur vous rappelle pour faire le point sur vos besoins et vous présenter nos conditions.\n\n"
    . 'Besoin d\'une réponse rapide ? Appelez-nous au ' . TELEPHONE . ", du lundi au vendredi de 8 h à 16 h.\n\nÀ très bientôt,\nAdrien et Romain, Gourmet Distri\n";
envoyer($smtp, $d['email'], 'Votre demande de rappel est bien arrivée · Gourmet Distri', $texte2, $html2, 'Gourmet Distri', DESTINATAIRE);

repondre(true, '', $json);
