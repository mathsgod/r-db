[![PHP Composer](https://github.com/mathsgod/r-db/actions/workflows/php.yml/badge.svg)](https://github.com/mathsgod/r-db/actions/workflows/php.yml)

# r-db

default driver option
```php
[
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]

```

## mysql8 collation
Due to php pdo default collation not match with mysql8, add the following options
```php
$options=[
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_0900_ai_ci'"
];
```
