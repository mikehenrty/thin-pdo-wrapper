# Thin PDO Wrapper

A simple database client utilizing PHP PDO.

## Advantages
- Maintains at most one connection to slave DB and one to master DB per request.
- Automatically uses slave connection for data retrieval, master for writes.
- Randomized slave connection, with automatic fallback to master if no slaves exist.
- Enforces data sanitization (using PDO prepared statements and bind parameters).
- Catches all errors, and writes them to error log if configured to do so.
- Automatic timestamps for creates and updates
- Handles multiple inserts with a single query.
- Fallback to custom queries if one needs to do a join or second order query.
- Deals exclusively with associative arrays for input/output

## Configuration

### Master
```
  $pdo->configMaster(
    'myhostname.example',
    'my_database_name',
    'my_database_user',
    'my_database_password',
    'my_database_port' // optional
  );
```

### Slaves (optional)
```
  $pdo->configSlave(
    'myhostname1.example',
    'my_database_name',
    'my_database_user',
    'my_database_password',
    'my_database_port' // optional
  );

  $pdo->configSlave(
    'myhostname2.example',
    'my_database_name',
    'my_database_user',
    'my_database_password',
    'my_database_port' // optional
  );
  // etc
```

## Examples

### Selecting
```
  $results = PDOWrapper::instance()->select('post', array('thread_id'=>$thread_id));
  $results = PDOWrapper::instance()->select('thread', array('open'=>0), $limit+1, $start, array('favs'=>'DESC'));
```

### Inserting
```
  $invite_id = PDOWrapper::instance()->insert('invite', array(
    'user_id' => $user_id,
    'text' => $text,
    'invite_key' => $invite_key
  ));
```

### Complex Queries with bind parameters
```
  $post = PDOWrapper::instance()->queryFirst('
    SELECT invite.*, user.name FROM invite
    LEFT JOIN user ON user.id=invite.user_id
    WHERE invite_key=:invite_key
  ', array(':invite_key'=>$invite_key));
```

