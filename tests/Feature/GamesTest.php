<?php

namespace Tests\Feature;

use App\Livewire\GameRoom;
use App\Models\BoardGame;
use App\Models\Conversation;
use App\Models\User;
use App\Services\LudoGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GamesTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->conversation = Conversation::create(['participant_one_id' => $this->alice->id, 'participant_two_id' => $this->bob->id]);
        $this->actingAs($this->alice);
    }

    private function room()
    {
        return Livewire::test(GameRoom::class, ['conversationId' => $this->conversation->id]);
    }

    public function test_chess_validates_moves_turns_and_shared_persistence(): void
    {
        $alice = $this->room()->call('showGame', 'chess')->call('startGame')->assertSee('You play white')
            ->call('chooseSquare', 'e2')->call('chooseSquare', 'e5')->assertHasErrors('game');
        $this->assertSame([], BoardGame::sole()->state['moves']);
        $alice->call('chooseSquare', 'e4')->assertHasNoErrors();
        $this->assertSame(['e4'], BoardGame::sole()->state['moves']);
        $alice->call('chooseSquare', 'd2')->assertHasErrors('game');

        $this->actingAs($this->bob);
        $this->room()->call('showGame', 'chess')->assertSee('You play black')
            ->call('chooseSquare', 'e7')->call('chooseSquare', 'e5')->assertHasNoErrors();
        $this->assertSame(['e4', 'e5'], BoardGame::sole()->state['moves']);
        $this->assertDatabaseCount('board_games', 1);
    }

    public function test_chess_detects_checkmate_and_prevents_restarting_an_active_game(): void
    {
        $this->room()->call('showGame', 'chess')->call('startGame')
            ->call('chooseSquare', 'f2')->call('chooseSquare', 'f3');
        $this->actingAs($this->bob);
        $this->room()->call('showGame', 'chess')->call('startGame')
            ->call('chooseSquare', 'e7')->call('chooseSquare', 'e5');
        $this->actingAs($this->alice);
        $this->room()->call('showGame', 'chess')->call('chooseSquare', 'g2')->call('chooseSquare', 'g4');
        $this->actingAs($this->bob);
        $this->room()->call('showGame', 'chess')->call('chooseSquare', 'd8')->call('chooseSquare', 'h4')
            ->assertHasNoErrors()->assertSee('Black wins');
        $this->assertSame('black wins', BoardGame::sole()->status);
    }

    public function test_resigning_finishes_a_game_and_allows_a_new_round(): void
    {
        $room = $this->room()->call('showGame', 'chess')->call('startGame')->call('resign')->assertSee('Black wins');
        $room->call('startGame')->assertSee('Your turn');
        $this->assertSame('playing', BoardGame::sole()->status);
        $this->assertSame([], BoardGame::sole()->state['moves']);
    }

    public function test_ludo_dice_are_server_generated_and_move_state_is_saved(): void
    {
        $room = $this->room()->call('showGame', 'ludo')->call('startGame');
        $room->call('rollDice')->assertHasNoErrors();
        $game = BoardGame::sole();
        $this->assertGreaterThanOrEqual(1, $game->state['last_roll']);
        $this->assertLessThanOrEqual(6, $game->state['last_roll']);

        $state = app(LudoGame::class)->initial();
        $state['die'] = 6;
        $game->update(['state' => $state]);
        $room->call('moveToken', 0)->assertHasNoErrors();
        $this->assertSame(0, $game->fresh()->state['tokens'][0][0]);
        $this->assertNull($game->fresh()->state['die']);
        $this->assertSame(0, $game->fresh()->state['turn']);
        $this->assertDatabaseCount('board_games', 1);
    }

    public function test_ludo_rejects_out_of_turn_actions_and_illegal_moves(): void
    {
        $room = $this->room()->call('showGame', 'ludo')->call('startGame');
        $room->call('moveToken', 0)->assertHasErrors('game');

        $this->actingAs($this->bob);
        $this->room()->call('showGame', 'ludo')->call('rollDice')->assertHasErrors('game');
        $this->room()->call('showGame', 'ludo')->call('moveToken', 0)->assertHasErrors('game');
    }

    public function test_unauthorized_users_cannot_open_or_modify_games(): void
    {
        $room = $this->room()->call('showGame', 'chess')->call('startGame');
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        $this->room()->assertNotFound();
        $room->call('resign')->assertNotFound();
        $this->assertSame('playing', BoardGame::sole()->status);
    }
}
