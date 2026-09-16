<?php
declare(strict_types=1);
 
// PHP protection also prevents direct downloads on servers without rewrite rules.
function fbo_interactions_read(string $path): array
{
    if (!is_file($path)) return [];
    $file = fopen($path, 'rb');
    if (!$file) throw new RuntimeException('Could not read visitor data.');
    try {
        if (!flock($file, LOCK_SH)) throw new RuntimeException('Could not lock visitor data.');
        fgets($file);
        $rows = [];
        while (($line = fgets($file)) !== false) {
            $row = json_decode($line, true);
            if (is_array($row)) $rows[] = $row;
        }
        return $rows;
    } finally { fclose($file); }
}

function fbo_interactions_append(string $path, array $row): void
{
    $file = fopen($path, 'c+b');
    if (!$file) throw new RuntimeException('Could not save. Please try again.');
    try {
        if (!flock($file, LOCK_EX)) throw new RuntimeException('Could not lock visitor data.');
        fseek($file, 0, SEEK_END);
        $start = ftell($file);
        if (($row['type'] ?? '') === 'initial_symbols' && $start > 0) return;
        $line = ($start === 0 ? "<?php http_response_code(404); exit; ?>\n" : '')
            . json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
        if (fwrite($file, $line) !== strlen($line) || !fflush($file)) {
            ftruncate($file, $start);
            throw new RuntimeException('Could not save. Please try again.');
        }
    } finally { fclose($file); }
}

function fbo_blog_list_symbols(string $blogPath): string
{
    $path = $blogPath . '/backend/visitor-interactions.php';
    $rows = fbo_interactions_read($path);
    if (!$rows) {
        fbo_interactions_append($path, ['type' => 'initial_symbols', 'symbols' => array_rand(FBO_SYMBOLS, 3), 'time' => time()]);
        $rows = fbo_interactions_read($path);
    }
    $symbols = [];
    foreach ($rows as $row) {
        if (in_array($row['type'] ?? '', ['initial_symbols', 'symbols'], true) && count($row['symbols'] ?? []) === 3) {
            $symbols = $row['symbols'];
        }
    }
    return '<span class="blog-list-symbols" aria-hidden="true">' . implode('', array_map(static fn($index) => fbo_symbol_svg((int) $index), $symbols)) . '</span>';
}

// Stable numeric positions preserve existing three-symbol contributions.
const FBO_SYMBOLS = [
    'Horse', 'Music notes', 'Star', 'Lightning', 'Cherry', 'Disco ball',
    'Cassette', 'Game controller', 'Alien', 'Ghost', 'Smiley', 'Flower',
    'Heart', 'Butterfly', 'Moon', 'Sun', 'Planet', 'Rocket',
    'Crown', 'Flame', 'Mushroom', 'Skull', 'Headphones', 'Peace',
];

const FBO_SILHOUETTES = [
 'M8 37C2 28 5 21 12 23C8 25 9 30 13 31L31 30C37 28 37 18 43 14L43 5L49 11L54 8L54 16L61 23Q62 27 57 29L49 24L44 35Q40 44 30 44H17Q10 43 8 37Z',
 'M23 12L54 5V42C54 53 37 56 36 47C35 42 40 37 47 38V18L30 22V48C30 59 12 61 11 52C10 46 16 42 23 44Z',
 'M32 3L41 22L62 25L47 40L51 61L32 51L13 61L17 40L2 25L23 22Z',
 'M35 2L10 36H29L24 62L55 25H36L43 2Z',
 'M31 6C36 17 42 24 45 34L41 36L31 16Q30 28 21 37L17 35Q28 22 27 6ZM31 7Q45 0 52 10Q40 18 31 7ZM27 45A12 12 0 1 1 3 45A12 12 0 1 1 27 45ZM60 46A13 13 0 1 1 34 46A13 13 0 1 1 60 46Z',
 'M30 1H34V9H30ZM32 10A25 25 0 1 1 32 60A25 25 0 1 1 32 10ZM18 19V25H24V19ZM29 16V24H35V16ZM40 19V25H46V19ZM12 30V37H20V30ZM25 29V37H33V29ZM38 30V37H46V30ZM17 42V49H24V42ZM29 43V53H36V43ZM41 42V49H47V42Z',
 'M9 10H55Q61 10 61 16V49Q61 55 55 55H9Q3 55 3 49V16Q3 10 9 10ZM15 18A8 8 0 1 0 15 34H49A8 8 0 1 0 49 18ZM16 42L12 51H52L48 42ZM15 22A4 4 0 1 1 15 30A4 4 0 1 1 15 22ZM49 22A4 4 0 1 1 49 30A4 4 0 1 1 49 22Z',
 'M19 15Q32 19 45 15Q55 13 59 27L63 45Q63 59 52 53L42 43H22L12 53Q1 59 1 45L5 27Q9 13 19 15ZM15 24V29H10V35H15V40H21V35H26V29H21V24ZM45 24A3 3 0 1 0 45 30A3 3 0 1 0 45 24ZM53 32A3 3 0 1 0 53 38A3 3 0 1 0 53 32Z',
 'M32 4C-5 4 2 39 25 57Q32 64 39 57C62 39 69 4 32 4ZM12 25Q25 24 27 38Q14 39 12 25ZM52 25Q39 24 37 38Q50 39 52 25Z',
 'M8 57V29C8-4 56-4 56 29V57Q50 47 44 57Q38 47 32 57Q26 47 20 57Q14 47 8 57ZM22 22A4 6 0 1 0 22 34A4 6 0 1 0 22 22ZM42 22A4 6 0 1 0 42 34A4 6 0 1 0 42 22Z',
 'M32 3A29 29 0 1 1 32 61A29 29 0 1 1 32 3ZM21 18A3 5 0 1 0 21 28A3 5 0 1 0 21 18ZM43 18A3 5 0 1 0 43 28A3 5 0 1 0 43 18ZM14 36C18 57 46 57 50 36H45C40 50 24 50 19 36Z',
 'M32 22C13-3 5 17 20 28C-6 28 3 48 24 39C15 65 39 67 38 43C58 62 69 41 45 33C71 19 47-1 37 23ZM32 26A7 7 0 1 0 32 40A7 7 0 1 0 32 26Z',
 'M32 58C23 50 3 36 3 21C3 3 24 1 32 16C40 1 61 3 61 21C61 36 41 50 32 58Z',
 'M29 23C9-4-7 14 10 33C-5 49 14 65 28 42L30 56H34L36 42C50 65 69 49 54 33C71 14 55-4 35 23L34 17L41 7L38 5L32 14L26 5L23 7L30 17Z',
 'M43 4C7-2-8 44 25 59C43 67 60 54 62 42C33 57 15 20 43 4Z',
 'M32 15A17 17 0 1 1 32 49A17 17 0 1 1 32 15ZM29 1H35V11H29ZM29 53H35V63H29ZM1 29H11V35H1ZM53 29H63V35H53ZM8 12L12 8L20 16L16 20ZM44 48L48 44L56 52L52 56ZM8 52L16 44L20 48L12 56ZM44 16L52 8L56 12L48 20Z',
 'M15 29C10 3 46-1 53 21C70 14 67 28 45 42C19 59-4 54 5 43L10 39C3 51 23 44 38 35C55 26 64 18 53 25L51 20C59 16 63 16 64 19C65 27 47 39 28 46C11 44 12 35 15 29Z',
 'M24 43C12 21 39 3 58 4C59 23 46 48 24 43ZM39 15A6 6 0 1 0 39 27A6 6 0 1 0 39 15ZM22 23L11 25L3 44L18 39ZM43 42L40 54L21 61L27 46ZM17 44Q3 43 4 60Q20 59 21 47Z',
 'M5 18L20 30L32 8L44 30L59 18L53 49H11ZM11 53H53V60H11Z',
 'M34 2C40 23 55 23 55 40C55 69 7 69 8 40C8 31 14 22 19 17C17 30 24 32 26 23C29 16 33 13 34 2ZM32 36C32 44 22 43 22 51C22 62 43 62 43 49C43 43 38 41 37 36C36 42 32 44 32 36Z',
 'M3 33C3-5 61-5 61 33Q61 40 41 39L46 59H18L23 39Q3 40 3 33ZM15 19A5 5 0 1 0 15 29A5 5 0 1 0 15 19ZM33 8A6 6 0 1 0 33 20A6 6 0 1 0 33 8ZM49 22A4 4 0 1 0 49 30A4 4 0 1 0 49 22Z',
 'M32 3C0 3-3 39 15 45V56H49V45C67 39 64 3 32 3ZM19 25A8 8 0 1 0 19 41A8 8 0 1 0 19 25ZM45 25A8 8 0 1 0 45 41A8 8 0 1 0 45 25ZM32 37L27 46H37ZM23 49V56H27V49ZM37 49V56H41V49Z',
 'M5 37V29C5-7 59-7 59 29V37H53V29C53 1 11 1 11 29V37ZM12 30H18Q22 30 22 34V54Q22 58 18 58H12Q5 58 5 50V38Q5 30 12 30ZM46 30H52Q59 30 59 38V50Q59 58 52 58H46Q42 58 42 54V34Q42 30 46 30Z',
 'M32 2A30 30 0 1 1 32 62A30 30 0 1 1 32 2ZM29 9C10 11 1 33 13 48L29 31ZM35 9V31L51 48C63 33 54 11 35 9ZM29 40L18 52Q23 55 29 56ZM35 40V56Q41 55 46 52Z',
];

function fbo_symbol_svg(int $index, string $imageUrl = ''): string
{
    if (!isset(FBO_SILHOUETTES[$index])) return '';
    static $serial = 0;
    $id = 'visitor-image-' . (++$serial);
    $defs = '';
    $fill = '#000';
    if ($imageUrl !== '') {
        $url = htmlspecialchars($imageUrl, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $defs = '<defs><pattern id="' . $id . '" width="1" height="1" viewBox="0 0 64 64" preserveAspectRatio="xMidYMid slice"><rect width="64" height="64" fill="#777"/><image href="' . $url . '" width="64" height="64" preserveAspectRatio="xMidYMid slice"/></pattern></defs>';
        $fill = 'url(#' . $id . ')';
    }
    $legs = $index === 0 ? '<g class="visitor-horse-legs-a"><path d="M16 39L23 41L18 59H12L17 52ZM32 39L39 37L44 54L51 57L49 61L39 58Z"/></g><g class="visitor-horse-legs-b"><path d="M16 39L23 41L25 53L16 59L13 56L19 51ZM32 39L39 37L36 53L28 60L24 58L30 49Z"/></g>' : '';
    $motions = ['gallop', 'music', 'twinkle', 'flash', 'sway', 'spin', 'music', 'shake', 'float', 'float', 'nod', 'sway', 'beat', 'flutter', 'sway', 'spin', 'spin', 'launch', 'nod', 'burn', 'grow', 'shake', 'music', 'sway'];
    return '<svg class="visitor-pixel-symbol" data-motion="' . $motions[$index] . '" width="32" height="32" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false">' . $defs . '<g fill="' . $fill . '" fill-rule="evenodd"><path d="' . FBO_SILHOUETTES[$index] . '"/>' . $legs . '</g></svg>';
}
