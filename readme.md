# DBConn PHP 

This is a useful library to handle common and fast operations on MysQL/MariaDB database engines, using Object notation an taking inspiration from Laravel Eloquent. 

## Installation

Just install the library via composer running 

```shell script
composer require chrisbaltazar/dbconn 
``` 

### Requirements

* PHP >= 7.0 

## Initial config. 


All the necessary configuration is done inside the class constructor, so you all you need to do is have ready the needed CONSTANTS/ENV values before instantiating your DB object. 

### The list of config fields are:

* DB_HOST (string)
* DB_USER (string)
* DB_PWD (string)
* DB_NAME (string)
* DB_DEBUG (bool) 
* SESSION_ID (string) _name of the variable into the application session to get the current user_
* TIME_ZONE (string) _GMT+1_ 
* SUMMER_TIME (bool) _autocalculate the time changing_
* CHARSET (string) _utf8_  
 

### Additional config. 
 
By default the library also tries to handle the popular _tablestamps_ 
* updated_by 
* updated_at 
* deleted_at 

and fill in their values on every query, this is also configured into the `constructor` so every change you may need can be done extending the class and overriding this part. 

> The most important thing to remember is that the `init` method MUST be called after doing the class configuration if you are extending the library 

## Usage

```php 
use chrisbaltazar/dbconn; 

$db = new DBConn(); 
```

## Examples 
Getting a list of results from one single table... 
```php 
$data = $db->from('tablename')->getArray(); 
```
Maybe a JSON would be more useful for more common cases
```php 
$data = $db->from('tablename')->getJSON(); 
```
Selecting only the fields you need in the consult, (separated by comma)
```php 
$data = $db->select('field1, field2, field3')
           ->from('tablename')
           ->getJSON(); 
```
Every time you need to debug your consult you can use the `getSQL` method at the end in order to get the query body to be executed before finish. 

```php
$data = $db->select('field1, field2, field3')
           ->from('tablename')
           ->getSQL(); 
```

Let's make a JOIN with more tables
```php 
$data = $db->select('field1, field2, field3', 'field4', 'field5')
           ->from('maintable')
           ->join('table2')
           ->join('table3', 1, 'LEFT')
           ->getJSON(); 

// In this case you can declare on the "join" statement the following: 
// 1. Table name for join to 
// 2. Index of target table in the tables array to join with, 
// for this example we have 3 tables in total, counting the main source(from table)
// In this case the index 1 will point to table2 instead of the maintable  which is index o    
// 3. The type of join, INNER by default
```
>by default the joins are done using the common table's id such as `id` and `foreign_id` 

If you need to specify the ON clause for every JOIN you can use 
```php
$data = $db->select('field1, field2, field3', 'field4')
           ->from('maintable')
           ->join('table2')->on('local_id', 'foreign_id')
           ->getJSON();
```

Now we are separating the fields to extract from each table
```php 
$data = $db->select('field1, field2, field3', 'field4', 'field5')
           ->from('maintable')
           ->join('table2')
           ->join('table3', 1, 'LEFT')
           ->getJSON(); 
// This way, we are extracting fields 1, 2 and 3 from table 0 or maintable
// and field4 from the fisrt join, table1 in this case
// the same for field5 which will come from table3 in that order
```

How about adding an ORDER and GROUP clauses
```php
$data = $db->select('field1, field2, field3')
           ->from('tablename')
           ->order('somefield1, somefield2')
           ->group('someotherfield')
           ->getJSON();

// Also you can specify the scope of the ORDER or GROUP 
// using array notation and index declaration like: 

->order([1 => 'table1_field', 0 => 'table0_field']);
```

What about "WHERE" clause? Let's see...
```php 
$data = $db->select(['field1, field2', 'field3', 'field4'])
           ->from('tablename')
           ->join(['othertablename', 0, 'other_id = main_id'])
           ->join(['anothertablename', 1, 'another_id = other_id', 'LEFT'])
           ->where(['table0field1 = somevalue, table0field2 = 0', 'table1field = somethingelse'])
           ->getJSON();

// The result of the above statement would be: 
...where table0_name.field1 = 'somevalue' 
     and table0_name.field2 = '0' 
     and table1_name.field = 'someothervalue'
```
Using the SAVE method for INSERTS and UPDATES
```php
$db->save('tablename', ['fieldname' => 'value'...], ['fieldname' => 'value']); 
// Here, you can set an array of values to INSERT or UPDATE the table, 
// which will be auto evaluated depending on the third parameter, the "where" part
```
Another using of this... 
```php
$db->save('tablename', $_POST, ['id' => $_POST['id']); 
// You can see how can be more dynamic than previous using if you like
// In this case, maybe the 'id' could be present or not and the method will 
// evaluate it as well. 
```
Finally, DELETES... 
```php 
$db->delete('tablename', $id);
// The DELETE method, will detect if you are passing only a numeric value as condition
// and use it with the table id automatically  
$db->delete('tablename', ['field' => 'value', ...]);
// Or you can either set all the condition fields to make the delete
```
Please remember that you can set previously the "delete flags" in order to avoid a permanent deletion

## License
[MIT](https://choosealicense.com/licenses/mit/)
