<?php

namespace Tests\Unit;

use App\Cms\Hooks\HookManager;
use App\Cms\Shortcodes\ShortcodeManager;
use PHPUnit\Framework\TestCase;

class HooksTest extends TestCase
{
    public function test_filters_run_by_priority_and_pass_extra_args(): void
    {
        $hooks = new HookManager;
        $hooks->addFilter('t', fn ($v) => "<$v>", 20);
        $hooks->addFilter('t', 'strtoupper');
        $hooks->addFilter('t', fn ($v, $s) => $v.$s, 5);

        $this->assertSame('<HI!>', $hooks->applyFilters('t', 'hi', '!'));
    }

    public function test_actions_and_removal(): void
    {
        $hooks = new HookManager;
        $calls = [];
        $cb = function ($a) use (&$calls) { $calls[] = $a; };
        $hooks->addAction('go', $cb);
        $hooks->doAction('go', 1);
        $hooks->removeAction('go', $cb);
        $hooks->doAction('go', 2);

        $this->assertSame([1], $calls);
        $this->assertSame(2, $hooks->didAction('go'));
    }

    public function test_shortcodes(): void
    {
        $s = new ShortcodeManager;
        $s->add('b', fn ($a, $c) => '<b>'.$s->process((string) $c).'</b>');
        $s->add('hi', fn ($a) => 'Hi '.($a['name'] ?? 'you'));

        $this->assertSame('<b>Hi Ann</b> [[x]]', $s->process('[b][hi name="Ann"][/b] [[x]]'));
        $this->assertSame('[hi]', $s->process('[[hi]]'));
        $this->assertSame('Hi Bo', $s->process('<p>[hi name=Bo]</p>'));
    }
}
