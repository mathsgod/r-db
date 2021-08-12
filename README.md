# r-db


## Create
```php

class Model extends \R\DB\Model{
    
}



$adapter=new Adapter($config); // laminas-db adapter

$model=new Model;
$model->setDbAdapter($adapter);


```

## Query
```php

class User extends Model{

}

foreacH(User::Query() as $user){
    print_r($user);
}
```






