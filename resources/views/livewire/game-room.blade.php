<section class="games-room" wire:poll.2s aria-label="Play corner">
    <div class="game-tiles">
        <button type="button" class="game-tile chess-tile {{ $selectedType === 'chess' ? 'chosen' : '' }}" wire:click="showGame('chess')"><span class="game-glyph" aria-hidden="true">♞</span><span><strong>Chess</strong><small>A little strategy, a little fun.</small></span><span aria-hidden="true">↗</span></button>
        <button type="button" class="game-tile ludo-tile {{ $selectedType === 'ludo' ? 'chosen' : '' }}" wire:click="showGame('ludo')"><span class="game-glyph" aria-hidden="true">⚄</span><span><strong>Ludo</strong><small>Your next lucky roll awaits.</small></span><span aria-hidden="true">↗</span></button>
    </div>
    @if ($selectedType)
        <div class="game-stage" role="dialog" aria-modal="true" aria-label="{{ ucfirst($selectedType) }} game" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())" x-on:keydown.escape.window.stop="$wire.showGame(null)">
            <div class="game-stage-heading"><h3>{{ ucfirst($selectedType) }} with {{ $opponent }}</h3><button type="button" class="text-button" wire:click="showGame(null)" aria-label="Close game">✕</button></div>
            @error('game')<p class="error" role="alert">{{ $message }}</p>@enderror
            @error('promotion')<p class="error" role="alert">{{ $message }}</p>@enderror
            @if (! $game)
                <div class="start-game"><span aria-hidden="true">{{ $selectedType === 'chess' ? '♟' : '⚄' }}</span><p>{{ $selectedType === 'chess' ? 'Take white, make the first move, and let the game unfold.' : 'Two players, four pieces each. Roll a six to leave home, then race all four pieces to the centre.' }}</p><button type="button" class="button primary" wire:click="startGame">Start {{ $selectedType }}</button></div>
            @else
                <div class="game-status" role="status"><span class="mode-dot"></span>{{ $game->status !== 'playing' ? ucfirst($game->status) : ($yourTurn ? 'Your turn' : $opponent.'’s turn') }}@if ($game->type === 'chess' && $game->state['check'] && $game->status === 'playing') · Check @endif<span class="your-color">You play {{ $game->type === 'chess' ? ($first ? 'white' : 'black') : ($first ? 'coral' : 'teal') }}</span></div>
                @if ($game->type === 'chess')
                    <div class="chess-layout">
                        <div class="chess-board" role="group" aria-label="Chess board">
                            @foreach ($squares as $square)
                                <button type="button" wire:key="square-{{ $square['name'] }}" wire:click="chooseSquare('{{ $square['name'] }}')" class="chess-square {{ $square['dark'] ? 'dark' : 'light' }} {{ $from === $square['name'] ? 'selected-square' : '' }} {{ in_array($square['name'], $targets, true) ? 'legal-target' : '' }} {{ $square['color'] === 'w' ? 'white-piece' : 'black-piece' }}" aria-label="{{ $square['name'] }}{{ $square['piece'] ? ' '.$square['piece'] : ' empty' }}" @disabled(! $yourTurn)><span class="square-name">{{ $square['name'] }}</span><span>{{ $square['piece'] }}</span></button>
                            @endforeach
                        </div>
                        <div class="chess-notes"><p>Tap a piece, then its destination. Legal moves are marked.</p><label for="promotion-{{ $conversationId }}">Promote pawn to</label><select id="promotion-{{ $conversationId }}" wire:model="promotion"><option value="q">Queen</option><option value="r">Rook</option><option value="b">Bishop</option><option value="n">Knight</option></select><h4>Moves</h4><div class="move-list">@forelse (array_chunk($game->state['moves'], 2) as $index => $pair)<span>{{ $index + 1 }}.</span><span>{{ $pair[0] }}</span><span>{{ $pair[1] ?? '…' }}</span>@empty<span>No moves yet.</span>@endforelse</div></div>
                    </div>
                @else
                    @php($state = $game->state)
                    @php($legal = $yourTurn ? $ludo->legalTokens($state) : [])
                    <div class="ludo-layout">
                        <div class="ludo-board" role="group" aria-label="Ludo board">
                            <div class="ludo-home home-coral">CORAL</div><div class="ludo-home home-teal">TEAL</div><div class="ludo-home home-sand"></div><div class="ludo-home home-lilac"></div>
                            @foreach ($ludo->coordinates() as $index => [$row, $column])
                                <span class="ludo-square {{ in_array($index, \App\Services\LudoGame::SAFE_SQUARES, true) ? 'safe-square' : '' }}" style="grid-row:{{ $row + 1 }};grid-column:{{ $column + 1 }}">@if(in_array($index, \App\Services\LudoGame::SAFE_SQUARES, true))☆@endif</span>
                            @endforeach
                            @for ($step = 51; $step < 56; $step++)
                                <span class="ludo-square coral-lane" style="grid-row:8;grid-column:{{ $step - 49 }}"></span>
                                <span class="ludo-square teal-lane" style="grid-row:8;grid-column:{{ 65 - $step }}"></span>
                            @endfor
                            <span class="ludo-centre" style="grid-row:7 / 10;grid-column:7 / 10">✳</span>
                            @foreach ($state['tokens'] as $player => $tokens)
                                @foreach ($tokens as $index => $position)
                                    @php([$row, $column] = $ludo->tokenCoordinate($player, $index, $position))
                                    <button type="button" wire:key="token-{{ $player }}-{{ $index }}" wire:click="moveToken({{ $index }})" class="ludo-token {{ $player === 0 ? 'coral-token' : 'teal-token' }} {{ $yourTurn && $state['turn'] === $player && in_array($index, $legal, true) ? 'movable' : '' }}" style="grid-row:{{ $row + 1 }};grid-column:{{ $column + 1 }};--token-offset:{{ $index % 2 === 0 ? '-13%' : '13%' }}" @disabled(! $yourTurn || $state['turn'] !== $player || ! in_array($index, $legal, true)) aria-label="{{ $player === 0 ? 'Coral' : 'Teal' }} piece {{ $index + 1 }}, {{ $position < 0 ? 'at home' : ($position === \App\Services\LudoGame::FINISH ? 'finished' : 'step '.$position) }}">{{ $index + 1 }}</button>
                                @endforeach
                            @endforeach
                        </div>
                        <div class="ludo-controls"><div class="dice" aria-label="Last roll {{ $state['last_roll'] ?? 'none' }}">{{ $state['last_roll'] ? ['','⚀','⚁','⚂','⚃','⚄','⚅'][$state['last_roll']] : '⚄' }}</div><button type="button" class="button primary" wire:click="rollDice" wire:loading.attr="disabled" @disabled(! $yourTurn || $state['die'] !== null)>Roll dice</button><p>{{ $state['die'] !== null ? 'Choose a highlighted piece to move '.$state['die'].' spaces.' : 'Roll, move, and race to the centre.' }}</p><small>Six to enter · Safe stars · Exact roll to finish<br>Six, capture or finish earns another roll.<br>Three sixes in a row ends your turn.</small></div>
                    </div>
                @endif
                <div class="game-footer">@if($game->status === 'playing')<button type="button" class="text-button" wire:click="resign" wire:confirm="Resign from this game?">Resign</button>@else<button type="button" class="button" wire:click="startGame">Play another round</button>@endif<span>Moves update automatically.</span></div>
            @endif
        </div>
    @endif
</section>
