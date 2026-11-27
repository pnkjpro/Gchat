<?php

namespace App\Livewire;

use App\Models\BoardGame;
use App\Models\Conversation;
use App\Services\LudoGame;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use PChess\Chess\Board;
use PChess\Chess\Chess;

class GameRoom extends Component
{
    #[Locked]
    public int $conversationId;

    #[Locked]
    public ?string $selectedType = null;

    #[Locked]
    public string $from = '';

    public string $promotion = 'q';

    public function mount(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->conversation();
    }

    private function conversation(): Conversation
    {
        abort_unless(Auth::check(), 401);

        return Conversation::forUser(Auth::id())->findOrFail($this->conversationId);
    }

    public function showGame(?string $type): void
    {
        $this->conversation();
        abort_unless($type === null || in_array($type, ['chess', 'ludo'], true), 422);
        $this->selectedType = $type;
        $this->from = '';
        $this->resetValidation();
    }

    public function startGame(): void
    {
        $this->conversation();
        abort_unless(in_array($this->selectedType, ['chess', 'ludo'], true), 422);
        $state = $this->selectedType === 'chess'
            ? ['fen' => Board::DEFAULT_POSITION, 'moves' => [], 'turn' => 'w', 'check' => false]
            : app(LudoGame::class)->initial();

        $game = BoardGame::firstOrCreate(
            ['conversation_id' => $this->conversationId, 'type' => $this->selectedType],
            ['first_player_id' => Auth::id(), 'state' => $state, 'status' => 'playing']
        );
        DB::transaction(function () use ($game, $state): void {
            $game = BoardGame::whereKey($game->id)->lockForUpdate()->firstOrFail();
            if ($game->status !== 'playing') {
                $game->update(['first_player_id' => Auth::id(), 'state' => $state, 'status' => 'playing']);
            }
        });
        $this->from = '';
        $this->resetValidation();
    }

    private function lockedGame(): BoardGame
    {
        $this->conversation();
        $game = BoardGame::where('conversation_id', $this->conversationId)
            ->where('type', $this->selectedType)->lockForUpdate()->firstOrFail();
        if ($game->status !== 'playing') {
            throw ValidationException::withMessages(['game' => 'This game is finished. Start another round.']);
        }

        return $game;
    }

    private function assertTurn(BoardGame $game): void
    {
        $first = $game->first_player_id === Auth::id();
        $current = $game->type === 'chess' ? $game->state['turn'] === 'w' : $game->state['turn'] === 0;
        if ($first !== $current) {
            throw ValidationException::withMessages(['game' => 'It is the other player’s turn.']);
        }
    }

    public function chooseSquare(string $square): void
    {
        abort_unless(isset(Board::SQUARES[$square]) && $this->selectedType === 'chess', 422);
        $this->resetValidation();
        DB::transaction(function () use ($square): void {
            $game = $this->lockedGame();
            $this->assertTurn($game);
            $engine = new Chess($game->state['fen']);
            $piece = $engine->board[Board::SQUARES[$square]];
            if ($piece && $piece->getColor() === $engine->turn) {
                $this->from = $this->from === $square ? '' : $square;

                return;
            }
            if ($this->from === '') {
                return;
            }
            $this->validate(['promotion' => ['in:q,r,b,n']]);
            $engine = new Chess;
            foreach ($game->state['moves'] as $san) {
                $engine->move($san);
            }
            $move = $engine->move(['from' => $this->from, 'to' => $square, 'promotion' => $this->promotion]);
            if (! $move) {
                throw ValidationException::withMessages(['game' => 'That move is not legal. Choose another square.']);
            }
            $state = $game->state;
            $state['moves'][] = $move->san;
            $state['fen'] = $engine->fen();
            $state['turn'] = $engine->turn;
            $state['check'] = $engine->inCheck();
            $status = $engine->inCheckmate() ? ($engine->turn === 'w' ? 'black wins' : 'white wins')
                : ($engine->inDraw() ? 'draw' : 'playing');
            $game->update(['state' => $state, 'status' => $status]);
            $this->from = '';
        });
    }

    public function rollDice(): void
    {
        abort_unless($this->selectedType === 'ludo', 422);
        $this->resetValidation();
        DB::transaction(function (): void {
            $game = $this->lockedGame();
            $this->assertTurn($game);
            $state = app(LudoGame::class)->roll($game->state, random_int(1, 6));
            $game->update(['state' => $state]);
        });
    }

    public function moveToken(int $index): void
    {
        abort_unless($this->selectedType === 'ludo', 422);
        $this->resetValidation();
        DB::transaction(function () use ($index): void {
            $game = $this->lockedGame();
            $this->assertTurn($game);
            $state = app(LudoGame::class)->move($game->state, $index);
            $game->update(['state' => $state, 'status' => $state['winner'] === null ? 'playing' : ($state['winner'] === 0 ? 'coral wins' : 'teal wins')]);
        });
    }

    public function resign(): void
    {
        $this->resetValidation();
        DB::transaction(function (): void {
            $game = $this->lockedGame();
            $first = $game->first_player_id === Auth::id();
            $game->update(['status' => $game->type === 'chess'
                ? ($first ? 'black wins' : 'white wins')
                : ($first ? 'teal wins' : 'coral wins')]);
        });
        $this->from = '';
    }

    public function render(): View
    {
        $conversation = $this->conversation();
        $game = $this->selectedType ? BoardGame::where('conversation_id', $this->conversationId)->where('type', $this->selectedType)->first() : null;
        $first = ! $game || $game->first_player_id === Auth::id();
        $yourTurn = $game && $game->status === 'playing' && ($first === ($game->type === 'chess' ? $game->state['turn'] === 'w' : $game->state['turn'] === 0));
        $squares = [];
        $targets = [];
        if ($game?->type === 'chess') {
            $engine = new Chess($game->state['fen']);
            foreach (Board::SQUARES as $name => $index) {
                $piece = $engine->board[$index];
                $squares[] = ['name' => $name, 'piece' => $piece?->toUnicode() ?? '', 'color' => $piece?->getColor(), 'dark' => ((ord($name[0]) - 97) + (int) $name[1]) % 2 === 1];
            }
            if (! $first) {
                $squares = array_reverse($squares);
            }
            if ($this->from !== '' && $yourTurn) {
                $targets = array_map(fn ($move) => $move->to, $engine->moves(Board::SQUARES[$this->from]));
            }
        }

        return view('livewire.game-room', [
            'game' => $game, 'first' => $first, 'yourTurn' => $yourTurn, 'squares' => $squares, 'targets' => $targets,
            'opponent' => $conversation->otherParticipant(Auth::id())->name,
            'ludo' => app(LudoGame::class),
        ]);
    }
}
