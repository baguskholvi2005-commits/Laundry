<?php

return [
    // Lokasi program mysqldump. Di Windows/Laragon biasanya perlu diisi lengkap lewat .env
    'mysqldump_path' => env('DB_DUMP_PATH', 'mysqldump'),

    // Jumlah cadangan terbaru yang disimpan. Yang lebih lama dihapus otomatis
    'keep' => (int) env('BACKUP_KEEP', 14),
];