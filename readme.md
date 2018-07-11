# DBConn PHP 

This is a useful class to handle dynamic connections and operations on different database engines, using Object notation 

## Installation

Just include this class in your own scripts and create an instance of it 

### Requirements

* PHP >= 5.4

## Initial config. 


To start to work with this, just declare in the "constructor" of the class, all the connections your want to use on it, as the following: 

```php 
$this->connections['DEFAULT'] = array(
     'HOST' => 'yourhostname', 
     'USR' => 'username', 
     'PWD' => 'dbpassword', 
     'DB' => 'dbname');
```
and so on... using an IDENTIFIER on each case into the array. 

### Additional config. 

You can also declare some attributes for the class, in order to handle "Logic deletes" on tables, instead of permanent. Regarding to the variable called "flags", which use simple values like 0 and 1 as status. 

Other useful attribute it is the class variable "tablestamps" which is an array of vars to manage the "updater" date and author, using the name of the SESSION for the current user. 

Of course you will find the proper "setters" for all this inside as well. 


## Usage

```php 
  require_once('DBConn.php'); 
  
  $db = new DBConn(); // For default 
```
or 
```php 
  $db = new DBConn('CONNECTION_NAME_IN ARRAY'); 
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
Selecting fields you need in the consult, (separated by comma)
```php 
$data = $db->select('field1, field2, field3')
           ->from('tablename')
           ->getJSON(); 
```
Let's make a JOIN with more tables
```php 
$data = $db->select('field1, field2, field3')
           ->from('tablename')
           ->join(['othertablename', 0, 'other_id = main_id'])
           ->join(['anothertablename', 1, 'another_id = other_id', 'LEFT'])
           ->getJSON(); 
// In this case you can declare on the "join" statement the following: 
// 1. Table name for join to 
// 2. Index of target table in the tables array to join with, 
// for this example we have 3 tables in total, counting the main source(from)  
// 3. The condition which these tables have to join with 
// 4. The type of join, INNER by default
```
Now we can separate the fields to extract from each table
```php 
$data = $db->select(['field1, field2', 'field3', 'field4'])
           ->from('tablename')
           ->join(['othertablename', 0, 'other_id = main_id'])
           ->join(['anothertablename', 1, 'another_id = other_id', 'LEFT'])
           ->getJSON(); 
// This way, we are extracting fields 1 and 2 from table 0 or main source
// and field 3 from the fisrt join, table 1 in this case
// finally, field 4 from second join, table 2 for us. 
```
How about adding an ORDER and GROUP clauses
```php
$data = $db->select('field1, field2, field3')
           ->from('tablename')
           ->order('somefield')
           ->group('someotherfield')
           ->getJSON(); 
// Also you can specify the scope of the ORDER or GROUP 
// using array notation and index declaration like: 

->order([1 => 'table1field', 0 => 'table0field'])

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
