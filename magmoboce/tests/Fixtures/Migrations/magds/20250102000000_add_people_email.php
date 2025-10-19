<?php

return [
    "id" => "20250102000000",
    "description" => "Add email column to people",
    "up" => [
        "ALTER TABLE people ADD COLUMN email TEXT"
    ],
    "down" => [
        "ALTER TABLE people DROP COLUMN email"
    ],
];
