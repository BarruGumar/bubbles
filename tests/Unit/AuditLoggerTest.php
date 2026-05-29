<?php

namespace Tests\Unit;

use App\Services\AuditLogger;
use ReflectionMethod;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    private function sanitize(array $data): array
    {
        $method = new ReflectionMethod(AuditLogger::class, 'sanitize');
        $method->setAccessible(true);

        return $method->invoke(null, $data);
    }

    // ── Remoção de chaves sensíveis ───────────────────────────────

    public function test_removes_password_key(): void
    {
        $result = $this->sanitize(['password' => 'secret123', 'action' => 'login']);

        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayHasKey('action', $result);
    }

    public function test_removes_all_sensitive_keys(): void
    {
        $sensitive = [
            'password'              => 'x',
            'password_confirmation' => 'x',
            'token'                 => 'x',
            'remember_token'        => 'x',
            'secret'                => 'x',
            'api_key'               => 'x',
            'api_secret'            => 'x',
            'access_token'          => 'x',
            'refresh_token'         => 'x',
            'credit_card'           => 'x',
            'cvv'                   => 'x',
            'ssn'                   => 'x',
        ];

        $result = $this->sanitize($sensitive);

        $this->assertEmpty($result);
    }

    public function test_sensitive_key_check_is_case_insensitive(): void
    {
        $result = $this->sanitize(['PASSWORD' => 'secret', 'Token' => 'abc']);

        $this->assertArrayNotHasKey('PASSWORD', $result);
        $this->assertArrayNotHasKey('Token', $result);
    }

    // ── Remoção de HTML / JS ──────────────────────────────────────

    public function test_strips_html_tags_from_string_values(): void
    {
        $result = $this->sanitize([
            'reason' => '<script>alert(1)</script>Motivo legítimo',
        ]);

        $this->assertSame('Motivo legítimo', $result['reason']);
    }

    public function test_strips_html_but_keeps_text_content(): void
    {
        $result = $this->sanitize([
            'note' => '<b>Nota importante</b>',
        ]);

        $this->assertSame('Nota importante', $result['note']);
    }

    public function test_strips_nested_html(): void
    {
        $result = $this->sanitize([
            'content' => '<div><p onclick="evil()">Texto</p></div>',
        ]);

        $this->assertSame('Texto', $result['content']);
    }

    // ── Truncagem ─────────────────────────────────────────────────

    public function test_truncates_string_to_500_chars(): void
    {
        $longString = str_repeat('a', 600);

        $result = $this->sanitize(['description' => $longString]);

        $this->assertSame(500, mb_strlen($result['description']));
    }

    public function test_string_shorter_than_500_is_not_changed(): void
    {
        $short = str_repeat('b', 100);

        $result = $this->sanitize(['description' => $short]);

        $this->assertSame($short, $result['description']);
    }

    public function test_truncation_happens_after_tag_removal(): void
    {
        // String of 510 'a' wrapped in a <b> tag. After strip_tags → 510 'a'. After truncate → 500.
        $longString = '<b>' . str_repeat('a', 510) . '</b>';

        $result = $this->sanitize(['text' => $longString]);

        $this->assertSame(500, mb_strlen($result['text']));
        $this->assertStringNotContainsString('<b>', $result['text']);
    }

    // ── Valores não-string são preservados ────────────────────────

    public function test_integer_values_pass_through_unchanged(): void
    {
        $result = $this->sanitize(['count' => 42]);

        $this->assertSame(42, $result['count']);
    }

    public function test_boolean_values_pass_through_unchanged(): void
    {
        $result = $this->sanitize(['active' => true, 'deleted' => false]);

        $this->assertTrue($result['active']);
        $this->assertFalse($result['deleted']);
    }

    public function test_null_values_pass_through_unchanged(): void
    {
        $result = $this->sanitize(['ends_at' => null]);

        $this->assertNull($result['ends_at']);
    }

    // ── Array vazio ───────────────────────────────────────────────

    public function test_empty_array_returns_empty_array(): void
    {
        $result = $this->sanitize([]);

        $this->assertSame([], $result);
    }
}
