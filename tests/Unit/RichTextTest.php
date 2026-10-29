<?php

namespace Tests\Unit;

use App\Support\RichText;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    public function test_it_keeps_supported_formatting_and_safe_links(): void
    {
        $html = RichText::sanitize('<p><strong>Important</strong></p><ul><li><a href="https://example.com" onclick="alert(1)">Details</a></li></ul>');

        $this->assertStringContainsString('<strong>Important</strong>', $html);
        $this->assertStringContainsString('<ul><li><a href="https://example.com" rel="nofollow noopener noreferrer">Details</a></li></ul>', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_it_removes_dangerous_markup_and_unsafe_links(): void
    {
        $html = RichText::sanitize('<p>Hello</p><script>alert(1)</script><a href="javascript:alert(1)">bad link</a><iframe src="https://example.com"></iframe>');

        $this->assertStringContainsString('Hello', $html);
        $this->assertStringContainsString('bad link', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_it_extracts_plain_text_and_detects_empty_content(): void
    {
        $this->assertSame("First\nSecond", RichText::plainText('<p>First</p><p><em>Second</em></p>'));
        $this->assertTrue(RichText::isEmpty('<p><br></p>'));
        $this->assertFalse(RichText::isEmpty('<p>Ready</p>'));
    }

    public function test_it_keeps_tables_images_code_and_only_safe_text_colors(): void
    {
        $html = RichText::sanitize('<table><tr><th>Plan</th></tr><tr><td>Ship</td></tr></table><pre><code>git status</code></pre><img src="https://example.com/diagram.png" alt="Diagram" onerror="alert(1)"><span data-text-color="#dc2626" style="color:red">Urgent</span><span data-text-color="url(javascript:alert(1))">Text</span><img src="data:text/html,unsafe">');

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<pre><code>git status</code></pre>', $html);
        $this->assertStringContainsString('<img src="https://example.com/diagram.png" alt="Diagram" loading="lazy">', $html);
        $this->assertStringContainsString('data-text-color="#dc2626"', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
        $this->assertStringNotContainsString('url(javascript:', $html);
    }
}
