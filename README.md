[![PHP Composer](https://github.com/mathsgod/r-db/actions/workflows/php.yml/badge.svg)](https://github.com/mathsgod/r-db/actions/workflows/php.yml)

# r-db

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
    UserRole::class=>[]
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

## Stream wrapper

By using stream wrapper, you can access the database table as a file

```php
use R\DB\Schema;
use R\DB\Stream;

Stream::Register(Schema::Create(), "db");
echo file_get_contents("db://User"); //List all users

```

### List single record
```php
echo file_get_contents("db://User/1"); //List user with primary key 1
``` 

### List by fields
```php
//List all user with fields first_name and last_name
echo file_get_contents("db://User?fields[]=first_name&fields[]=last_name"); 

//List user with primary key 1 and field username
echo file_get_contents("db://User/1?fields[]=user_id&fields[]=username"); 
```


### List by filter
```php

$query=http_build_query([
    "filters"=>[
        "status"=>[
            "eq"=>1
        ]
    ]
]);

echo file_get_contents("db://User?$query"); //List all user with status=1

```

### List by limit and offset
```php

echo file_get_contents("db://User?limit=10&offset=0"); //List first 10 users

```


## Schema Aware
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