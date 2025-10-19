# MagMoBoCE Migration Conventions

- Store component migrations under `migrations/<component>` directories (default: `migrations/magds`).
- Each migration is a PHP file returning an array with the following keys:
  ```php
  <?php
  return [
      "id" => "202510180001",
      "description" => "Describe the change",
      "up" => [
          "-- SQL statements executed when migrating up",
      ],
      "down" => [
          "-- SQL statements executed when migrating down",
      ],
  ];
  ```
- Use zero-padded, sortable `id` values (timestamp or sequence). The manager runs files in lexical order.
- Down statements are required for rollbacks; keep them safe and idempotent.
- Avoid trailing semicolons; each entry executes independently via PDO.
