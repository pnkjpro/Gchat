<?php

namespace Tests\Unit;

use App\Services\LudoGame;
use PHPUnit\Framework\TestCase;

class LudoRulesTest extends TestCase
{
    public function test_six_is_required_to_enter_and_three_sixes_forfeit_the_turn(): void
    {
        $rules = new LudoGame;
        $state = $rules->roll($rules->initial(), 3);
        $this->assertSame(1, $state['turn']);
        $state = $rules->roll($rules->initial(), 6);
        $state = $rules->move($state, 0);
        $this->assertSame(0, $state['tokens'][0][0]);
        $state = $rules->move($rules->roll($state, 6), 0);
        $state = $rules->roll($state, 6);
        $this->assertSame(1, $state['turn']);
        $this->assertNull($state['die']);
        $this->assertSame(0, $state['sixes']);
    }

    public function test_capture_returns_opponent_home_and_grants_another_roll(): void
    {
        $rules = new LudoGame;
        $state = $rules->initial();
        $state['tokens'][0][0] = 4;
        $state['tokens'][1][0] = 31;
        $state = $rules->move($rules->roll($state, 1), 0);
        $this->assertSame(-1, $state['tokens'][1][0]);
        $this->assertSame(0, $state['turn']);
    }

    public function test_safe_squares_protect_tokens(): void
    {
        $rules = new LudoGame;
        $state = $rules->initial();
        $state['tokens'][0][0] = 7;
        $state['tokens'][1][0] = 34;
        $state = $rules->move($rules->roll($state, 1), 0);
        $this->assertSame(34, $state['tokens'][1][0]);
        $this->assertSame(1, $state['turn']);
    }

    public function test_exact_finish_is_required_and_all_four_pieces_win(): void
    {
        $rules = new LudoGame;
        $state = $rules->initial();
        $state['tokens'][0] = [56, 56, 56, 55];
        $blocked = $rules->roll($state, 2);
        $this->assertSame(1, $blocked['turn']);
        $state = $rules->move($rules->roll($state, 1), 3);
        $this->assertSame(0, $state['winner']);
        $this->assertSame([], $rules->legalTokens($state));
    }

    public function test_tokens_in_the_home_lane_cannot_be_captured(): void
    {
        $rules = new LudoGame;
        $state = $rules->initial();
        $state['turn'] = 1;
        $state['tokens'][0][0] = 51;
        $state['tokens'][1][0] = 24;
        $state = $rules->move($rules->roll($state, 1), 0);
        $this->assertSame(51, $state['tokens'][0][0]);
        $this->assertSame(0, $state['turn']);
    }
}
