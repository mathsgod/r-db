[![PHP Composer](https://github.com/mathsgod/r-db/actions/workflows/php.yml/badge.svg)](https://github.com/mathsgod/r-db/actions/workflows/php.yml)

# r-db

## PHP compatibility (v4)

The v4 branch requires PHP 8.3 or later. Core ORM behavior is retained.
PSR HTTP Message 1 and 2 are accepted, including uploaded-file conversion.
The optional GraphQLite mapper's return type is compatible with newer
GraphQLite interfaces; GraphQLite 6.2.3 and 8.3.1 are covered by regression tests.

## Regression tests

```sh
composer install
php8.3 vendor/bin/phpunit --testsuite unit
```

The default suite covers model defaults, dirty tracking, JSON references and
GraphQL mappings. It also includes MySQL integration tests for CRUD, JSON,
sorting, pagination, transactions and uploaded files. Integration tests skip
unless an isolated database is explicitly configured:

```sh
RDB_TEST_DATABASE=rdb_test_compat \
RDB_TEST_HOST=127.0.0.1 RDB_TEST_PORT=3306 \
RDB_TEST_USER=root RDB_TEST_PASSWORD=test-password \
php8.3 vendor/bin/phpunit
```

Use a disposable database beginning with `rdb_test_`: the integration suite
creates and truncates its `RdbCompatibilityRecord` table. The original tests
directly under `tests/` use a different, manually provisioned database fixture
and are not included in the new PHPUnit configuration.

Warnings, notices and unexpected deprecations fail the suite. All 14 regression
tests (65 assertions), including the MySQL integration tests, pass on PHP 8.3,
8.4 and 8.5 with Symfony Validator 6.4 and `mathsgod/php-util` 1.5.2 or later.

Symfony Validator `^5.3 || ^6.4` is accepted. Both PHP attributes and legacy
Doctrine annotations retain their validation behavior. Symfony 6.4 deliberately
emits a deprecation when a legacy annotation constraint is used; the regression
test explicitly checks that warning. Prefer PHP attributes (`#[Assert\NotBlank]`)
for code that must run without this upstream deprecation.

## Install
```
composer require mathsgod/r-db
```

## Setup
### using .env
Using .env file to setup default database connection
```ini
DATABASE_HOSTNAME=
DATABASE_DATABASE=
DATABASE_USERNAME=
DATABASE_PASSWORD=
DATABASE_PORT=
DATABASE_CHARSET=
```

## Function Q
Function Q is a fast way to select data from database.
### simple select

```php
use function R\DB\Q;
class User{ //simple class file

}

print_r(Q(User::class)->get()); // select * from User

```

### select with fields and filter
filter parameter is based on laminas-db where 

```php
print_r(Q(User::class)->fields(["user_id","username"])->filter(["type"=>1])->get()); 
// select user_id,username from User where type=1

```

### populate
populate is used to select related data from other table, it will auto check the relationship between tables by primary key
```php 
class UserRole{

}

class User{

}

print_r(Q(User::class)->populate([
    Q(UserRole:class)
])->get());

/* 
Array
(
    [0] => User Object
        (
            [username] => admin
            [user_id] => 1
            [UserRole] => Array
                (
                    [0] => UserRole Object
                        (
                            [user_role_id] => 1
                            [user_id] => 1
                            [role] => Administrators
                        )

                )

        )

)
*/
```

### Schema Aware
You can define a static method GetSchema() in your class to define the schema of the table
```php
class User implements SchemaAwareInterface{
    public static function GetSchema(){
        return $schema1;
    }
}


```
## Class R\DB\Model
By extends R\DB\Model, you can use the following methods to operate the database

```php
class User extends R\DB\Model{
} 
```


### insert record
```php

User::Create([
    "username"=>"user1",
    "first_name"=>"John"
])->save();
```

### get record
```php
$user = User::Get(1);  // 1 is primary key

$user_not_exists = User::Get(999); // $user_not_exists==null
```

### update record
```php
$user = User::Get(1); // 1 is primary key
$user->first_name="Mary";
$user->save(); // user record updated
```

### delete record
```php
$user = User::Get(1);  // 1 is primary key
$user->delete(); // user record is deleted
```

### query list record
```php
$users = User::Query(["status"=>0]);
print_r($users->toArray());  // list all users status is equal to 0
```

## Default driver option
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
