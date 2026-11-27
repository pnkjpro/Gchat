<?php

return [
    'max_upload_kilobytes' => 5120,
    'upload_types' => 'jpg,jpeg,png,gif,webp,pdf,txt,csv,zip,doc,docx,xls,xlsx,ppt,pptx,mp3,wav,m4a,mp4,webm,mov',
    'disappear_after_seconds' => max(1, (int) env('CHAT_DISAPPEAR_AFTER_SECONDS', 5)),
    'nudge_cooldown_seconds' => max(1, (int) env('CHAT_NUDGE_COOLDOWN_SECONDS', 60)),
];
