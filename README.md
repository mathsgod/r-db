[![PHP Composer](https://github.com/mathsgod/r-db/actions/workflows/php.yml/badge.svg)](https://github.com/mathsgod/r-db/actions/workflows/php.yml)

# r-db

## Install
```
composer require mathsgod/r-db
```

## Setup
### using .env
```
DATABASE_HOSTNAME=
DATABASE_DATABASE=
DATABASE_USERNAME=
DATABASE_PASSWORD=
DATABASE_PORT=
DATABASE_CHARSET=
```

## Select with function Q

### simple select

```php
use function R\DB\Q;
class User{ //simple class file

}

print_r(Q(User::class)->get()); // select * from User

```

### select with fields
```php
print_r(Q(User::class)->filter(["type"=>1])->get()); // select * from User where type=1

```

### populate
```php 
class UserRole{

}

class User{

}

print_r(Q(User::class)->populate([
    Q(UserRole:class)
])->get());

/* 
output:
(
    [0] => stdClass Object
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
class User implmenet SchemaAwareInterface{
    public static function GetSchema(){
        return $schema1;
    }
}


```
## Example

### extends with R\DB\Model

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