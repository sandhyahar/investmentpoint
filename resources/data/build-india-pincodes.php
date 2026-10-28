<?php
// php build-pincodes.php <India Post CSV> <output JSON>
// Output: {"State": {"City": ["380001", ...]}} sorted; cities = India Post districts in Title Case.
// Cleaning: each PIN only under one state - where most of its post offices are, or (a single office, or a tie)
// the state of most PINs with the same first 3 digits; spelling variants of a district merged; tiny
// "districts" (<=3 PINs, all also under another district of the state) dropped.
[$_, $in, $out] = $argv;
$fh = fopen($in, 'r');
$head = fgetcsv($fh);
$col = array_flip(array_map('trim', $head));
$title = function (string $s): string {
    $s = preg_replace('/[^\x20-\x7E]/', '', $s); // a few names carry a stray non-ASCII byte ("Agartala\xA0")
    $s = ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $s))), " -(.");
    return preg_replace_callback('/\b(And|Of)\b/', fn ($m) => strtolower($m[1]), $s);
};
$states = ['Andaman and Nico.In.' => 'Andaman and Nicobar Islands', 'Chattisgarh' => 'Chhattisgarh',
    'Dadra and Nagar Hav.' => 'Dadra and Nagar Haveli', 'Megalaya' => 'Meghalaya', 'Pondicherry' => 'Puducherry'];
$typos = ['Jalgoan' => 'Jalgaon', 'Gomti' => 'Gomati', 'Mumbai Subueban' => 'Mumbai Suburban', 'Malappurm' => 'Malappuram',
    'Aizawal' => 'Aizawl', 'Auranagabad' => 'Aurangabad', 'Mahabuababad' => 'Mahabubabad', 'K.v.rangareddy' => 'Rangareddy',
    'Hazaribag' => 'Hazaribagh', 'Devbhoomi Dwerka' => 'Devbhoomi Dwarka', 'Prayagraj Allahabad' => 'Prayagraj', 'Botab' => 'Botad',
    'Chandighar' => 'Chandigarh'];
// Cities India Post files under a neighbouring state's postal circle
$districtState = ['Chandigarh' => 'Chandigarh', 'Pondicherry' => 'Puducherry'];

$rows = [];          // [state, district, pin]
$pinStates = [];     // pin => [state => post offices]
$bad = 0;
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < count($head)) { $bad++; continue; }
    $pin = trim($r[$col['Pincode']]);
    $state = $title($r[$col['StateName']]);
    $district = $title($r[$col['District']]);
    if (!preg_match('/^[1-9]\d{5}$/', $pin) || $state === '' || $district === '' || $state === 'Na' || $district === 'Na') { $bad++; continue; }
    $state = $states[$state] ?? $state;
    $district = trim(preg_replace('/\s*\([a-z.]{1,4}\)$/i', '', $district)); // "Bilaspur (hp)" -> "Bilaspur"
    $district = $typos[$district] ?? $district;
    $state = $districtState[$district] ?? $state;
    $rows[] = [$state, $district, $pin];
    $pinStates[$pin][$state] = ($pinStates[$pin][$state] ?? 0) + 1;
}

// 1. a PIN belongs to one state: where most of its post offices are (2+ offices, a clear winner); else
//    (one office, or a tie) the state its district is mostly filed under - one stray row ("Manipur" for
//    the NIT Agartala office of West Tripura) can't move a PIN, and small territories keep theirs
$keyOf = fn (string $district) => strtolower(preg_replace('/[^a-z]/i', '', $district)); // "Gandhi Nagar" = "Gandhinagar"
$districtStates = [];  // district key => [state => post offices]
foreach ($rows as [$state, $district]) $districtStates[$keyOf($district)][$state] = ($districtStates[$keyOf($district)][$state] ?? 0) + 1;
$districtState = array_map(function ($c) { arsort($c); return array_key_first($c); }, $districtStates);
$pinRows = [];
foreach ($rows as [$state, $district, $pin]) $pinRows[$pin][] = $keyOf($district);
$pinState = [];
foreach ($pinStates as $pin => $c) {
    arsort($c);
    $top = array_values($c);
    if ($top[0] >= 2 && $top[0] > ($top[1] ?? 0)) { $pinState[$pin] = array_key_first($c); continue; }
    $votes = [];
    foreach ($pinRows[$pin] as $k) $votes[$districtState[$k]] = ($votes[$districtState[$k]] ?? 0) + 1;
    arsort($votes);
    $pinState[$pin] = array_key_first($votes);
}
$data = [];      // state => key => ['names' => [name => n], 'pins' => [pin => n]]
$wrong = 0;
foreach ($rows as [$state, $district, $pin]) {
    $key = $keyOf($district);
    if ($pinState[$pin] !== $state) {
        $wrong++;
        // filed under the wrong state: moved when its district belongs to the PIN's state, else left out
        if ($districtState[$key] !== $pinState[$pin]) continue;
        $state = $pinState[$pin];
    }
    $data[$state][$key]['names'][$district] = ($data[$state][$key]['names'][$district] ?? 0) + 1;
    $data[$state][$key]['pins'][$pin] = ($data[$state][$key]['pins'][$pin] ?? 0) + 1;
}

// 2. one name per district (the spelling most post offices use); 3. drop tiny duplicates
$result = [];
$dropped = 0;
foreach ($data as $state => $districts) {
    $pinUse = [];
    foreach ($districts as $d) foreach ($d['pins'] as $pin => $_) $pinUse[$pin] = ($pinUse[$pin] ?? 0) + 1;
    foreach ($districts as $d) {
        $pins = array_map('strval', array_keys($d['pins']));
        if (count($pins) <= 3 && !array_filter($pins, fn ($p) => $pinUse[$p] === 1)) { $dropped++; continue; }
        arsort($d['names']);
        sort($pins);
        $result[$state][array_key_first($d['names'])] = $pins;
    }
}
ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
$pins = 0; $cities = 0;
foreach ($result as &$ds) { ksort($ds, SORT_NATURAL | SORT_FLAG_CASE); $cities += count($ds); foreach ($ds as $p) $pins += count($p); }
unset($ds);
file_put_contents($out, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
fwrite(STDERR, sprintf("states=%d cities=%d city-pins=%d unique-pins=%d skipped=%d wrong-state=%d tiny-dropped=%d bytes=%d\n",
    count($result), $cities, $pins, count($pinState), $bad, $wrong, $dropped, filesize($out)));
