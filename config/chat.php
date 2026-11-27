<?php

return [
    'disappear_after_seconds' => max(1, (int) env('CHAT_DISAPPEAR_AFTER_SECONDS', 5)),
    'nudge_cooldown_seconds' => max(1, (int) env('CHAT_NUDGE_COOLDOWN_SECONDS', 60)),
];
