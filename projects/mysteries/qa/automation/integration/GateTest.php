<?php
// The hidden test phase: only testers, admins and the owner get in (US-13, K-41, AK-26).
use PHPUnit\Framework\TestCase;

final class GateTest extends TestCase
{
    /** TC-API-001 (K-41): not logged in gets a login link, not the page. */
    public function testNotLoggedInGetsLoginLink(): void
    {
        [$status, , $body] = test_get('/mysteries/');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('/discord-auth.php', $body);
        $this->assertStringNotContainsString('Welcome', $body);
    }

    /** TC-API-002 (AK-26): a logged-in member without the tester role is denied. */
    public function testMemberWithoutRoleIsDenied(): void
    {
        [$status, , $body] = test_get('/mysteries/', ['Cookie: ' . test_session_cookie(TEST_CAROL, 'Carol')]);
        $this->assertSame(403, $status);
        $this->assertStringContainsString('only for testers', $body);
    }

    /** TC-API-003 (K-41): a tester and the owner get in. */
    public function testTesterAndOwnerGetIn(): void
    {
        foreach ([[TEST_BOB, 'Bob'], [TEST_ALICE, 'Alice']] as [$id, $name]) {
            [$status, , $body] = test_get('/mysteries/', ['Cookie: ' . test_session_cookie($id, $name)]);
            $this->assertSame(200, $status, $name);
            $this->assertStringContainsString("Welcome, $name.", $body);
        }
    }

    /** TC-API-004 (K-41): the page asks search engines not to index it. */
    public function testPageIsNotIndexed(): void
    {
        [, $headers] = test_get('/mysteries/');
        $this->assertContains('X-Robots-Tag: noindex, nofollow', $headers);
    }

    /** TC-API-005 (K-28): the visitor's language, English when we do not have it. */
    public function testLanguageAndEnglishFallback(): void
    {
        [, , $sv] = test_get('/mysteries/', ['Accept-Language: sv-SE,sv;q=0.9']);
        $this->assertStringContainsString('Logga in med Discord', $sv);
        [, , $fr] = test_get('/mysteries/', ['Accept-Language: fr-FR']);
        $this->assertStringContainsString('Log in with Discord', $fr);
    }

    /** TC-API-021 (K-54, AK-42): the mystery files (every variant's clues, the solutions) are never served, not even to the owner. */
    public function testMysteryContentIsNeverServed(): void
    {
        $owner = ['Cookie: ' . test_session_cookie(TEST_ALICE, 'Alice')];
        foreach (['mystery.json', 'en.json', 'sv.json'] as $file) {
            foreach ([[], $owner] as $headers) {
                [$status, , $body] = test_get("/mysteries/content/last-toast/$file", $headers);
                $this->assertSame(403, $status, $file);
                $this->assertStringNotContainsString('solution', $body, $file);
            }
        }
        [$status] = test_get('/mysteries/content/');
        $this->assertSame(403, $status, 'no listing of the mysteries');
        [$status, $headers] = test_get('/mysteries/media/last-toast/cover.webp');           // K-63: the pictures are the same in every variant
        $this->assertSame(200, $status, 'the pictures are served');
        $this->assertNotEmpty(preg_grep('/^Content-Type: image\/webp/i', $headers));
    }
}
