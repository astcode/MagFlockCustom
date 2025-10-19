<?php

return [
    "id" => "20250101000001",
    "description" => "Create people table",
    "up" => [
        "CREATE TABLE people (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)"
    ],
    "down" => [
        "DROP TABLE people"
    ],
];
