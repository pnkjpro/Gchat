<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class LudoGame
{
    public const FINISH = 56;

    public const SAFE_SQUARES = [0, 8, 13, 21, 26, 34, 39, 47];

    public function initial(): array
    {
        return ['tokens' => [[-1, -1, -1, -1], [-1, -1, -1, -1]], 'turn' => 0, 'die' => null, 'sixes' => 0, 'winner' => null, 'last_roll' => null];
    }

    public function legalTokens(array $state): array
    {
        if ($state['die'] === null || $state['winner'] !== null) {
            return [];
        }

        return array_keys(array_filter($state['tokens'][$state['turn']], fn (int $position): bool => $position === -1 ? $state['die'] === 6 : $position < self::FINISH && $position + $state['die'] <= self::FINISH
        ));
    }

    public function roll(array $state, int $die): array
    {
        if ($state['die'] !== null || $state['winner'] !== null || $die < 1 || $die > 6) {
            throw ValidationException::withMessages(['game' => 'Finish your current move before rolling again.']);
        }

        $state['last_roll'] = $die;
        $state['sixes'] = $die === 6 ? $state['sixes'] + 1 : 0;
        if ($state['sixes'] === 3) {
            return $this->nextTurn($state);
        }
        $state['die'] = $die;
        if ($this->legalTokens($state) === []) {
            $state['die'] = null;
            if ($die !== 6) {
                $state = $this->nextTurn($state);
            }
        }

        return $state;
    }

    public function move(array $state, int $token): array
    {
        if (! in_array($token, $this->legalTokens($state), true)) {
            throw ValidationException::withMessages(['game' => 'Choose one of your highlighted pieces.']);
        }

        $player = $state['turn'];
        $die = $state['die'];
        $position = $state['tokens'][$player][$token];
        $position = $position === -1 ? 0 : $position + $die;
        $state['tokens'][$player][$token] = $position;
        $captured = false;

        if ($position < 51) {
            $square = ($position + $player * 26) % 52;
            if (! in_array($square, self::SAFE_SQUARES, true)) {
                foreach ($state['tokens'][1 - $player] as $index => $opponentPosition) {
                    if ($opponentPosition >= 0 && $opponentPosition < 51
                        && ($opponentPosition + (1 - $player) * 26) % 52 === $square) {
                        $state['tokens'][1 - $player][$index] = -1;
                        $captured = true;
                    }
                }
            }
        }

        $state['die'] = null;
        if (count(array_filter($state['tokens'][$player], fn (int $p): bool => $p === self::FINISH)) === 4) {
            $state['winner'] = $player;
        } elseif ($die !== 6 && ! $captured && $position !== self::FINISH) {
            $state = $this->nextTurn($state);
        }

        return $state;
    }

    private function nextTurn(array $state): array
    {
        $state['turn'] = 1 - $state['turn'];
        $state['die'] = null;
        $state['sixes'] = 0;

        return $state;
    }

    public function coordinates(): array
    {
        return [[6, 1], [6, 2], [6, 3], [6, 4], [6, 5], [5, 6], [4, 6], [3, 6], [2, 6], [1, 6], [0, 6], [0, 7], [0, 8],
            [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [6, 9], [6, 10], [6, 11], [6, 12], [6, 13], [6, 14], [7, 14], [8, 14],
            [8, 13], [8, 12], [8, 11], [8, 10], [8, 9], [9, 8], [10, 8], [11, 8], [12, 8], [13, 8], [14, 8], [14, 7], [14, 6],
            [13, 6], [12, 6], [11, 6], [10, 6], [9, 6], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0], [7, 0], [6, 0]];
    }

    public function tokenCoordinate(int $player, int $index, int $position): array
    {
        if ($position < 0) {
            return $player === 0 ? [[2, 2], [2, 4], [4, 2], [4, 4]][$index] : [[10, 10], [10, 12], [12, 10], [12, 12]][$index];
        }
        if ($position < 51) {
            return $this->coordinates()[($position + $player * 26) % 52];
        }
        if ($position === self::FINISH) {
            return [7, 7];
        }

        return $player === 0 ? [7, $position - 50] : [7, 64 - $position];
    }
}
