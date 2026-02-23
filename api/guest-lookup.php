<?php
/**
 * Guest Lookup API
 *
 * POST /api/guest-lookup.php
 * Body: { "name": "John Doe" }
 * Headers: X-RSVP-Token: <token>
 *
 * Returns the guest record (with plus-one info) if found.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Load config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration missing']);
    exit;
}
$config = require $configPath;

// ─── Rate limiting ────────────────────────────────────────────
$rateDir = $config['security']['rate_limit_dir'];
if (!is_dir($rateDir)) {
    mkdir($rateDir, 0700, true);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $rateDir . md5($ip) . '.json';
$rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
if (!is_array($rateData)) $rateData = [];
$now = time();
// Clean old entries (older than 60s)
$rateData = array_filter($rateData, function ($ts) use ($now) {
    return is_int($ts) && ($now - $ts) < 60;
});
if (count($rateData) >= ($config['security']['rate_limit_rpm'] ?? 10)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please wait a moment.']);
    exit;
}
$rateData[] = $now;
file_put_contents($rateFile, json_encode(array_values($rateData)), LOCK_EX);

// ─── Token validation ─────────────────────────────────────────
$token = $_SERVER['HTTP_X_RSVP_TOKEN'] ?? '';
if (empty($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Missing security token']);
    exit;
}

$parts = explode('.', $token);
if (count($parts) !== 2) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$payload = json_decode(base64_decode($parts[0]), true);
$signature = $parts[1];

if (!$payload || !isset($payload['exp'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$expectedSig = hash_hmac('sha256', $parts[0], $config['security']['token_secret']);
if (!hash_equals($expectedSig, $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

if ($payload['exp'] < time()) {
    http_response_code(403);
    echo json_encode(['error' => 'Token expired. Please refresh and try again.']);
    exit;
}

// ─── Parse input ──────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');

if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid name.']);
    exit;
}

// Sanitize — strip tags, allow only letters, digits, spaces, hyphens, apostrophes
$name = strip_tags($name);
if (!preg_match('/^[\p{L}\d\s\'\-\.]+$/u', $name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid name.']);
    exit;
}

// ─── Database lookup ──────────────────────────────────────────
require_once __DIR__ . '/Database.php';
$db = new Database($config['mysql']);

// Case-insensitive search by name within this tenant
$guest = $db->findOne('guests', [
    'tenant_id' => $config['tenant_id'],
    'name_lower' => strtolower($name),
]);

if (!$guest) {
    // Step 2: LIKE substring match
    $guests = $db->findLike(
        'guests',
        ['tenant_id' => $config['tenant_id']],
        'name_lower',
        '%' . strtolower($name) . '%'
    );

    if (count($guests) === 1) {
        $guest = $guests[0];
    } elseif (count($guests) > 1) {
        echo json_encode([
            'error' => 'Multiple guests found. Please enter your full name as it appears on your invitation.',
        ]);
        exit;
    }
}

if (!$guest) {
    // Step 3: Advanced fuzzy matching
    // - Alias map for common name variants (handles different first letters Soundex misses)
    // - Accent/diacritic normalization
    // - Soundex phonetic matching
    // - Levenshtein distance for close typos
    // - "et" couple matching (search by one spouse's name)

    // ── Name variant aliases (bidirectional) ──
    $aliasMap = [
        // French/Arabic/English first-letter variants
        'carl'       => ['karl'],
        'karl'       => ['carl'],
        'celine'     => ['seline', 'selene', 'celene'],
        'seline'     => ['celine'],
        'javier'     => ['xavier'],
        'xavier'     => ['javier'],
        'charbel'    => ['sharbel'],
        'sharbel'    => ['charbel'],
        'chadi'      => ['shadi'],
        'shadi'      => ['chadi'],
        // Common French/Arabic spelling variants
        'george'     => ['georges'],
        'georges'    => ['george'],
        'elie'       => ['eli', 'ellie', 'ely'],
        'eli'        => ['elie', 'ellie', 'ely'],
        'bashir'     => ['bachir', 'beshir', 'beshara'],
        'bachir'     => ['bashir', 'beshir'],
        'ghassan'    => ['ghasan', 'gassan'],
        'ghasan'     => ['ghassan'],
        'naji'       => ['nagi', 'najy'],
        'nagi'       => ['naji'],
        'rami'       => ['ramy'],
        'ramy'       => ['rami'],
        'tony'       => ['toni', 'anthony', 'antoine'],
        'toni'       => ['tony', 'anthony', 'antoine'],
        'anthony'    => ['tony', 'toni', 'antoine'],
        'antoine'    => ['tony', 'toni', 'anthony'],
        'jean'       => ['john', 'johnny'],
        'john'       => ['jean', 'johnny'],
        'johnny'     => ['jean', 'john'],
        'joseph'     => ['joe', 'youssef', 'yousef', 'yusuf'],
        'joe'        => ['joseph', 'youssef'],
        'youssef'    => ['joseph', 'joe', 'yousef', 'yusuf'],
        'yousef'     => ['joseph', 'youssef', 'yusuf'],
        'pierre'     => ['peter', 'petra', 'boutros'],
        'peter'      => ['pierre', 'boutros'],
        'boutros'    => ['pierre', 'peter'],
        'marie'      => ['maria', 'mary', 'mariam', 'maryam', 'marian'],
        'maria'      => ['marie', 'mary', 'mariam'],
        'mary'       => ['marie', 'maria', 'mariam', 'maryam'],
        'mariam'     => ['marie', 'maria', 'mary', 'maryam'],
        'maryam'     => ['mariam', 'marie', 'mary'],
        'lea'        => ['lia', 'leah', 'leia'],
        'lia'        => ['lea', 'leah', 'leia'],
        'michel'     => ['michael', 'mikael', 'mikhael'],
        'michael'    => ['michel', 'mikael', 'mikhael'],
        'micky'      => ['micki', 'mickey', 'michel', 'michael'],
        'mickey'     => ['micky', 'micki'],
        'raymond'    => ['raimond', 'raymon'],
        'nadim'      => ['nadeem'],
        'nadeem'     => ['nadim'],
        'nada'       => ['nadah'],
        'fouad'      => ['foad', 'fuad', 'fouaad'],
        'fuad'       => ['fouad', 'foad'],
        'ibrahim'    => ['abraham', 'brahim'],
        'abraham'    => ['ibrahim', 'brahim'],
        'walid'      => ['waleed', 'oualid'],
        'waleed'     => ['walid'],
        'jacques'    => ['jack', 'jak'],
        'jack'       => ['jacques'],
        'elias'      => ['ilias', 'elyas', 'ilyas'],
        'ilias'      => ['elias', 'elyas'],
        'ghazi'      => ['ghazy'],
        'ghazy'      => ['ghazi'],
        'samir'      => ['sameer'],
        'sameer'     => ['samir'],
        'nabil'      => ['nabeel'],
        'nabeel'     => ['nabil'],
        'jihad'      => ['jehad', 'gihad'],
        'jehad'      => ['jihad'],
        'abdo'       => ['abdou', 'abdel', 'abd'],
        'abdou'      => ['abdo'],
        'pere'       => ['père', 'father', 'fr'],
        'père'       => ['pere', 'father', 'fr'],
        'gizele'     => ['gisele', 'giselle', 'gizelle'],
        'gisele'     => ['gizele', 'giselle'],
        'giselle'    => ['gizele', 'gisele'],
        'corine'     => ['corinne', 'korine', 'corrine'],
        'corinne'    => ['corine', 'korine'],
        'joelle'     => ['joele', 'joell'],
        'josette'    => ['josete'],
        'christiane' => ['christine', 'christina'],
        'christine'  => ['christiane', 'christina'],
        'claudine'   => ['claudin', 'claudia'],
        'bernadette' => ['bernadet', 'bernadett'],
        'monique'    => ['monika', 'monica'],
        'monika'     => ['monique', 'monica'],
        'moussa'     => ['mousa', 'musa'],
        'mousa'      => ['moussa', 'musa'],
        'raghid'     => ['ragheed', 'rachid', 'rashid'],
        'rachid'     => ['raghid', 'rashid'],
        'rashid'     => ['raghid', 'rachid'],
        'ronald'     => ['ronaldo', 'ronal'],
        'dolly'      => ['doly', 'dolli'],
        'nawal'      => ['nawell', 'nawel'],
        'nawel'      => ['nawal', 'nawell'],
        'patricia'   => ['patrica', 'patrisia'],
        'michelle'   => ['mishelle', 'michell', 'michel'],
        'ghada'      => ['ghada'],
        'katia'      => ['katya', 'catia'],
        'katya'      => ['katia'],
        'martine'    => ['martin', 'martina'],
        'dina'       => ['deena', 'dena'],
        'deena'      => ['dina'],
        'kinda'      => ['kenda'],
        'magda'      => ['magdah', 'magdalena'],
        'nazek'      => ['nazeek'],
        'ghosn'      => ['ghoson', 'ghosen'],
        'kennie'     => ['kenny', 'keni'],
        'kenny'      => ['kennie'],
        'hanne'      => ['hanna', 'hana', 'hannah'],
        'hanna'      => ['hanne', 'hana', 'hannah'],
        'isaac'      => ['isac', 'ishac', 'ishak'],
        'isac'       => ['isaac', 'ishac'],
    ];

    // ── Strip accents/diacritics for comparison ──
    $stripAccents = function ($str) {
        $translitMap = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
            'ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y',
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE',
            'Ç'=>'C','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'Ñ'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y',
        ];
        return strtr($str, $translitMap);
    };

    // ── Helper: check if two words are a fuzzy match ──
    $wordsMatch = function ($inputWord, $guestWord) use ($aliasMap, $stripAccents) {
        $a = $stripAccents(strtolower($inputWord));
        $b = $stripAccents(strtolower($guestWord));

        // Exact after accent strip
        if ($a === $b) return true;

        // Soundex match
        if (soundex($a) === soundex($b)) return true;

        // Alias map match
        if (isset($aliasMap[$a]) && in_array($b, $aliasMap[$a])) return true;

        // Levenshtein for short typos (max distance scales with word length)
        $maxDist = (strlen($a) <= 4) ? 1 : 2;
        if (levenshtein($a, $b) <= $maxDist) return true;

        // Alias + Levenshtein: check if input is close to any alias of the guest word
        if (isset($aliasMap[$b])) {
            foreach ($aliasMap[$b] as $alias) {
                $aliasDist = (strlen($a) <= 4) ? 1 : 2;
                if (levenshtein($a, $alias) <= $aliasDist) return true;
            }
        }

        return false;
    };

    $inputWords = preg_split('/[\s\-]+/', strtolower($name));
    // Remove "et" from input if present (used as French "and" in couple names)
    $inputWords = array_values(array_filter($inputWords, function ($w) {
        return $w !== 'et' && $w !== '&' && $w !== 'and';
    }));
    $inputStripped = array_map($stripAccents, $inputWords);

    $allGuests = $db->find('guests', ['tenant_id' => $config['tenant_id']]);
    $fuzzyMatches = [];

    foreach ($allGuests as $g) {
        $guestWords = preg_split('/[\s\-]+/', $g['name_lower']);
        // Remove "et" from guest name for matching
        $guestFiltered = array_values(array_filter($guestWords, function ($w) {
            return $w !== 'et' && $w !== '&' && $w !== 'and';
        }));

        // Every input word must match at least one guest word
        $matched = 0;
        foreach ($inputWords as $iw) {
            foreach ($guestFiltered as $gw) {
                if ($wordsMatch($iw, $gw)) {
                    $matched++;
                    break;
                }
            }
        }

        if ($matched === count($inputWords) && count($inputWords) > 0) {
            $fuzzyMatches[] = $g;
        }
    }

    if (count($fuzzyMatches) === 1) {
        $guest = $fuzzyMatches[0];
    } elseif (count($fuzzyMatches) > 1) {
        echo json_encode([
            'error' => 'Multiple guests found. Please enter your full name as it appears on your invitation.',
        ]);
        exit;
    } else {
        echo json_encode(['guest' => null]);
        exit;
    }
}

// Return guest data (only safe fields)
$rsvpStatus = $guest['rsvp_status'] ?? 'pending';
echo json_encode([
    'guest' => [
        'id'                => $guest['id'] ?? null,
        'name'              => $guest['name'] ?? '',
        'plus_one'          => (bool) ($guest['plus_one'] ?? false),
        'plus_one_name'     => $guest['plus_one_name'] ?? null,
        'prewedding'        => (bool) ($guest['prewedding'] ?? false),
        'rsvp_status'       => $rsvpStatus,
        'already_submitted' => $rsvpStatus !== 'pending',
    ],
]);
