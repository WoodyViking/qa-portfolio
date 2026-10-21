<?php
// Mystery content: "The Last Toast" and the checker that proves a mystery sound (component level, no database),
// modules/mystery-content.inc.php and content/last-toast/. Exhaustive testing of the variants (5 culprits x 3 weapons
// x 2 lengths), every single clue left out in the short game, and fault seeding: broken copies of the mystery that the
// checker must reject. Traceability: US-16, K-28, K-36, K-50..K-53, K-63, NFR-10, AK-23, AK-39, AK-40, AK-41.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/mystery-content.inc.php';

final class MysteryContentTest extends TestCase
{
    private const LANGS = ['en', 'sv', 'da', 'nl'];
    private const SUSPECTS = ['oskar', 'agnes', 'niels', 'willem', 'margaret'];
    private const WEAPONS = ['falcon', 'poker', 'telescope'];
    /** Texts that are rightly the same as in English: the same word in both languages, not a missed translation. */
    private const SAME_AS_ENGLISH = ['nl' => ['place.salon']];

    private function mystery(): array
    {
        return my_mystery_load('last-toast');
    }

    private function texts(): array
    {
        $t = [];
        foreach (self::LANGS as $l) $t[$l] = my_mystery_json(my_mystery_dir('last-toast') . "/$l.json");
        return $t;
    }

    /** A copy of the mystery with one clue changed by $f (or removed when $f returns null). */
    private function withClue(array $m, string $id, callable $f): array
    {
        $m['clues'] = array_values(array_filter(array_map(fn($c) => $c['id'] === $id ? $f($c) : $c, $m['clues'])));
        return $m;
    }

    private function assertProblem(string $needle, array $problems, string $why): void
    {
        $this->assertNotEmpty(array_filter($problems, fn($p) => str_contains($p, $needle)), "$why: expected \"$needle\" in " . json_encode(array_slice($problems, 0, 5)));
    }

    /** TC-INT-011 (K-50): the mystery is data: people, places, weapons, clues, the drawn parts; the checker finds nothing wrong */
    public function testStructure(): void
    {
        $m = $this->mystery();
        $this->assertSame(['last-toast', 'mystery', 1], [$m['id'], $m['category'], $m['format']]);
        $this->assertSame(['culprit' => self::SUSPECTS, 'weapon' => self::WEAPONS], $m['draw']);
        $this->assertSame(['viktor'], array_column(array_filter($m['people'], fn($p) => $p['role'] === 'victim'), 'id'));
        $this->assertCount(15, my_mystery_variants($m));
        $this->assertSame([60, 90], array_column($m['lengths'], 'minutes'));
        $this->assertFalse($m['strongViolence'], 'playable from 13 (K-39, NFR-08)');
        $this->assertSame([], my_mystery_check($m, $this->texts()));
    }

    /** TC-INT-012 (K-51, AK-39): every culprit with every weapon, in both lengths: the clues leave exactly the drawn ones */
    public function testEveryVariantHasExactlyOneSolution(): void
    {
        $m = $this->mystery();
        foreach ([60, 90] as $len) {
            foreach (self::SUSPECTS as $who) {
                foreach (self::WEAPONS as $what) {
                    $this->assertSame(['culprit' => [$who], 'weapon' => [$what]],
                        my_mystery_solve($m, $len, ['culprit' => $who, 'weapon' => $what]), "$len min, $who with $what");
                }
            }
        }
    }

    /** TC-INT-013 (K-52, K-08, AK-41): 60 min still solves with any one clue missing; 90 min is bigger and has no shortcuts */
    public function testLengths(): void
    {
        $m = $this->mystery();
        $private = fn($len) => array_column(array_filter(my_mystery_clues($m, $len), fn($c) => empty($c['public'])), 'id');
        foreach ($private(60) as $gone) {
            foreach (my_mystery_variants($m) as $v) {
                $left = my_mystery_solve($m, 60, $v, [$gone]);
                $this->assertSame([[$v['culprit']], [$v['weapon']]], [$left['culprit'], $left['weapon']], "60 min without $gone, " . implode(' + ', $v));
            }
        }
        $this->assertGreaterThan(count($private(60)), count($private(90)), '90 min has more clues');
        $this->assertNotContains('window_note', $private(90), 'in 90 min the time of the murder must be worked out');
        $this->assertNotContains('wiped', $private(90), 'in 90 min the weapon must be worked out from the wound');
        $this->assertContains('window_note', $private(60));
        $left = my_mystery_solve($m, 90, ['culprit' => 'agnes', 'weapon' => 'poker'], ['wound']);
        $this->assertCount(3, $left['weapon'], 'in 90 min the wound is needed for the weapon');
    }

    /** TC-INT-014 (K-53, K-13): what a device is shown of a clue is the same in every variant except the text, and never names the version */
    public function testCardsDoNotRevealTheVariant(): void
    {
        $m = $this->mystery();
        $en = $this->texts()['en'];
        foreach ([60, 90] as $len) {
            $seen = [];
            foreach (my_mystery_variants($m) as $v) {
                foreach (my_mystery_clues($m, $len) as $c) {
                    $card = my_mystery_card($c, $v, $en);
                    $this->assertSame(['id', 'title', 'text', 'place', 'from'], array_keys($card));
                    $this->assertNotSame('', $card['text'], "$len min {$c['id']}");
                    $shape = $card;
                    unset($shape['text']);
                    $seen[implode(' + ', $v)][] = $shape;
                }
            }
            $this->assertCount(1, array_unique(array_map('json_encode', $seen)), "$len min: the same clues, titles, places and sources in every variant");
        }
        $folder = array_values(array_filter($m['clues'], fn($c) => $c['id'] === 'folder'))[0];
        $a = my_mystery_card($folder, ['culprit' => 'oskar', 'weapon' => 'poker'], $en);
        $b = my_mystery_card($folder, ['culprit' => 'niels', 'weapon' => 'poker'], $en);
        $this->assertSame($a['title'], $b['title']);
        $this->assertNotSame($a['text'], $b['text']);
    }

    /** TC-INT-015 (K-28, NFR-10): every text in English, Swedish, Danish and Dutch, none left untranslated, nothing stale */
    public function testEveryTextInFourLanguages(): void
    {
        $m = $this->mystery();
        $t = $this->texts();
        $keys = my_mystery_keys($m);
        foreach (self::LANGS as $l) {
            $this->assertSame([], array_values(array_diff($keys, array_keys($t[$l]))), "$l: missing");
            $this->assertSame([], array_values(array_diff(array_keys($t[$l]), $keys)), "$l: not used");
            if ($l === 'en') continue;
            $same = array_filter($keys, fn($k) => $t[$l][$k] === $t['en'][$k] && !preg_match('/^person\.\w+\.name$/', $k)
                && !in_array($k, self::SAME_AS_ENGLISH[$l] ?? [], true));
            $this->assertSame([], array_values($same), "$l: still in English");
        }
        $this->assertSame($t['sv']['title'], my_mystery_texts('last-toast', 'sv')['title']);
        $this->assertSame($t['en']['title'], my_mystery_texts('last-toast', 'xx')['title'], 'an unknown language falls back to English');
    }

    /** TC-INT-016 (AK-40, K-51): the checker rejects a broken mystery and says what is wrong */
    public function testCheckerFindsFaults(): void
    {
        $m = $this->mystery();
        $t = $this->texts();
        $check = fn($bad, $texts = null) => my_mystery_check($bad, $texts ?? $t);

        $bad = $this->withClue($m, 'check_oskar', function ($c) { $c['versions']['guilty']['facts'] = ['alibi_oskar']; return $c; });
        $this->assertProblem('the real culprit oskar is ruled out', $check($bad), 'the guilty one gets an alibi');

        $bad = $m;
        foreach (['blow', 'scent', 'check_margaret'] as $id) $bad = $this->withClue($bad, $id, fn($c) => null);
        $problems = $check($bad);
        $this->assertProblem('90 min, agnes + falcon: culprit not certain, still agnes, margaret', $problems, 'an innocent nobody can rule out');
        $this->assertProblem('fact alibi_margaret is needed by a rule but nothing gives it', $problems, 'a rule nothing can fire');
        $this->assertNotEmpty(array_filter($problems, fn($x) => str_starts_with($x, 'en: not used') && str_contains($x, 'clue.blow.title')), 'texts of a clue that is gone');

        $bad = $this->withClue($m, 'blow', function ($c) { $c['versions']['right']['when'] = ['agnes', 'margaret']; return $c; });
        $this->assertProblem('clue blow: niels is in 0 versions', $check($bad), 'a culprit without a version');

        $bad = $this->withClue($m, 'check_willem', function ($c) { $c['versions']['innocent']['facts'] = ['alibi_wilem']; return $c; });
        $this->assertProblem('fact alibi_wilem is never used by a rule', $check($bad), 'a typo in a fact');

        $bad = $this->withClue($m, 'iou', function ($c) { $c['where'] = 'attic'; return $c; });
        $this->assertProblem('unknown place attic', $check($bad), 'a place that does not exist');

        $texts = $t;
        unset($texts['sv']['clue.scent.pipe']);
        $texts['da']['clue.old_clue'] = 'Gammel';
        $problems = $check($m, $texts);
        $this->assertProblem('sv: missing clue.scent.pipe', $problems, 'a missing translation');
        $this->assertProblem('da: not used clue.old_clue', $problems, 'a stale text');

        $bad = $this->withClue($m, 'wiped', fn($c) => null);
        $texts = array_map(fn($x) => array_filter($x, fn($k) => !str_starts_with($k, 'clue.wiped'), ARRAY_FILTER_USE_KEY), $t);
        $this->assertProblem('60 min, oskar + falcon without wound: weapon not certain', $check($bad, $texts), 'one missing clue breaks the short game');
    }

    /** TC-INT-018 (K-63, K-51): the pictures: every one a clue or the plan names exists; the plan has a point for every place */
    public function testPictures(): void
    {
        $m = $this->mystery();
        $this->assertSame([], my_mystery_problems('last-toast'));
        $this->assertSame(['last-toast'], my_mystery_list());
        $media = my_mystery_media('last-toast');
        foreach ($m['people'] as $p) $this->assertContains('person-' . $p['id'], $media);
        foreach ($m['places'] as $p) $this->assertContains('place-' . $p['id'], $media);
        foreach ($m['weapons'] as $w) $this->assertContains('weapon-' . $w['id'], $media);
        $this->assertContains('cover', $media);
        $this->assertSame([], array_values(array_filter(array_column($m['clues'], 'image'), fn($i) => !in_array($i, $media, true))));
        $noPicture = array_column(array_filter($m['clues'], fn($c) => !isset($c['image']) && !isset($c['from'])), 'id');
        $this->assertSame([], $noPicture, 'every clue has a picture or a portrait of whom it comes from');

        $t = $this->texts();
        $bad = $m;
        $bad['board']['places']['attic'] = [10, 10];
        unset($bad['board']['places']['kitchen']);
        $bad['board']['places']['tower'] = [5000, 10];
        $problems = my_mystery_check($bad, $t);
        $this->assertProblem('board: unknown place attic', $problems, 'a place on the plan that does not exist');
        $this->assertProblem('board: no point for kitchen', $problems, 'a place missing on the plan');
        $this->assertProblem('board: bad point for tower', $problems, 'a point outside the plan');
        $bad = $this->withClue($m, 'iou', function ($c) { $c['image'] = '../secret'; return $c; });
        $this->assertProblem('clue iou: bad image', my_mystery_check($bad, $t), 'a picture name that is a path');
    }

    /** TC-INT-017 (K-36, AK-23): the culprit and the weapon are drawn at random; every one of them comes up */
    public function testDrawIsRandom(): void
    {
        $m = $this->mystery();
        $seen = ['culprit' => [], 'weapon' => []];
        for ($i = 0; $i < 300; $i++) {
            $v = my_mystery_draw($m);
            $this->assertContains($v, my_mystery_variants($m));
            $seen['culprit'][$v['culprit']] = true;
            $seen['weapon'][$v['weapon']] = true;
        }
        $this->assertEqualsCanonicalizing(self::SUSPECTS, array_keys($seen['culprit']));
        $this->assertEqualsCanonicalizing(self::WEAPONS, array_keys($seen['weapon']));
    }
}
