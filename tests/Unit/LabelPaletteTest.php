<?php

namespace Tests\Unit;

use App\Support\LabelColors;
use PHPUnit\Framework\TestCase;

class LabelPaletteTest extends TestCase
{
    public function test_the_locked_label_palette_has_one_source_of_truth(): void
    {
        $this->assertSame([
            'teal',
            'soft_blue',
            'success',
            'warning',
            'danger',
            'violet',
            'sky',
            'rose',
            'slate',
            'charcoal',
        ], LabelColors::ALL);
    }
}
