<?php

function getQueries( $sql ) {
  $queries  = array();
  $tables = array();
  $strlen   = strlen($sql);
  $position = 0;
  $query    = '';
  $is_create_query = false;
  $cur_table = 0;
  $cur_field = 0;
  while ($position < $strlen) {
    $char  = $sql{ $position };
      
    if ($char == '-' ) {
        /* Не включать коментарии через -- */
        if ( substr($sql, $position, 3) !== '-- ' )  {
          $query .= $char;
        } else {
          while ( $char !== "\r" && $char !== "\n" && $position < $strlen - 1 ) {
            $char = $sql{ ++$position };
          };
        }
    } else if ($char == '/' ) {
        /* Не включать коментарии через /* */
        if ( substr($sql, $position, 2) == '/*' )  {
          $position += 2;
          while ( (substr($sql, $position-2, 2) != '*/') && $position < $strlen - 1 ) {
            $char = $sql{ ++$position };
          };
        };
    } else if ($char ==  '#' ) {
        /* Не включать коментарии через # */
        while ( $char !== "\r" && $char !== "\n" && $position < $strlen - 1 ) {
          $char = $sql{ ++$position };
        };
    } else if ( $char == ' ' ){
        /* Не включать лишние пробелы */
        while ( $char == ' ' ) {
          $char = $sql{ ++$position };
        };
        $position --;
        $query .= ' ';
    } else if (in_array($char, array("\n", "\r")) ){
        /* Не включать лишние переносы строк */
    } else if (in_array ($char, array('`', '\'', '"'))) {
        $quote  = $char;
        $query .= $quote;
        while ( $position < $strlen - 1 )    {
          $char = $sql{ ++$position };
          if ( $char === '\\' ) {
            $query .= $char;
            if ( $position < $strlen - 1 )  {
              $char   = $sql{ ++$position };
              $query .= $char;
              if ( $position < $strlen - 1 ) { 
                $char = $sql{ ++$position };
              }
            } else {
              break;
            }
          }
          if ( $char === $quote ) break;
          $query .= $char;
        }
        $query .= $quote;

        
    } else if ($char == ';') {
        $query = trim($query);
        if ( $query ) {
          $queries[] = $query;
          if (preg_match("/^(CREATE TABLE)+ (IF NOT EXISTS )?`([\S]+)\` \((.*)\) (.*)$/", $query, $_matches) ) {
            $table_name = $_matches[3];
            $fields = $_matches[4];
            $fields_before = '';
            /*Вдруг есть затяпые в DEFAULT заменим их на [zapataya]*/
            while  ($fields_before != $fields) {
              $fields_before = $fields;
              $fields = preg_replace("/DEFAULT \'([^\']*),([^\']*)\'/s", "DEFAULT '$1[zapataya]$2'", $fields);
            }
            /*Разобьем на добавляемые поля */
//            if (preg_match_all("/(`([\S^`]+)` (\w+)\(? ?(\d*) ?\)? ([^,]*)),?/", $fields, $__matches, PREG_SET_ORDER)) {
            if (preg_match_all("/(`([\S^`]+)` ([^,]*)),?/", $fields, $__matches, PREG_SET_ORDER)) {

              foreach ($__matches as $key=>$val) {
                /*Если меняли запятые, то высмтавим их обратно */
                $__matches[$key] = str_replace('[zapataya]' , ',', $val);
              }
              $tables[$table_name] = $__matches;
            }
          }
        }
       
     
        $query     = '';
        $command = '';
    } else  {
        $query .= mb_strtoupper($char);
    }
   $position++;
  }
  $query = trim( $query );
  if ( $query ) {
    $queries[] = $query;
    if (preg_match("/^(CREATE TABLE)+ (IF NOT EXISTS )?`([\S]+)\` \((.*)\) (.*)$/", $query, $_matches) ) {
      $table_name = $_matches[3];
      $fields = $_matches[4];
      $fields_before = '';
      /*Вдруг есть затяпые в DEFAULT? заменим их на [zapataya]*/
      while  ($fields_before != $fields) {
        $fields_before = $fields;
        $fields = preg_replace("/DEFAULT \'([^\']*),([^\']*)\'/s", "DEFAULT '$1[zapataya]$2'", $fields);
      }
      /*Разобьем на добавляемые поля */
//      if (preg_match_all("/(`([\S^`]+)` (\w+)\(? ?(\d*) ?\)? ([^,]*)),?/", $fields, $__matches, PREG_SET_ORDER)) {
      if (preg_match_all("/(`([\S^`]+)` ([^,]*)),?/", $fields, $__matches, PREG_SET_ORDER)) {
        foreach ($__matches as $key=>$val) {
          /*Если меняли запятые, то высмтавим их обратно */
          $__matches[$key] = str_replace('[zapataya]' , ',', $val);
        }
        $tables[$table_name] = $__matches;
      }
    }
  }

  $result['QUERIES'] = $queries;
  $result['TABLES'] = $tables;  
  return $result;
}

/**********************************************/
function isFieldExists($table, $Fieldname) {
  $result = mysqli_db()->query('SHOW COLUMNS FROM `'.$table.'` LIKE "'.$Fieldname.'"');
  if ($result->num_rows >= 1) {
    return true;
  };
  return false;
}

/**********************************************/
function isFieldNeedUpdate($table, $FieldName, $FieldType, $fieldSize) {

}

/**********************************************/
function isTableExists($table) {
  if ($result = $mysqli->query("SHOW TABLES LIKE '".$table."'")) {
    if($result->num_rows == 1) {
      return true;
    }
  }
  return false;
}

/**********************************************/
/*  CONNECT TO DATABASE  */
function connect_to_db($dbhost, $dbuser, $dbpass, $dbname) {
  global $mysqli;
  $mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
  if ($mysqli->connect_errno) {
    die ();
  }
}

/**********************************************/
/* возвращает объект базу mysqli */
function mysqli_db() {
  global $mysqli;
  return $mysqli;
};
/**********************************************/
function UpdateDB ($CurSql, $dbhost, $dbuser, $dbpass, $dbname) {
  connect_to_db($dbhost, $dbuser, $dbpass, $dbname);
  echo '<p>Connected</p>';
  $resurs = getQueries( $CurSql );
  if (is_array($resurs['QUERIES'] )) {
    foreach ($resurs['QUERIES'] as $query){
      if (mb_substr($query,0, mb_strlen('CREATE TABLE')) == 'CREATE TABLE') {
        /* Выполним все запросы на созднание таблиц */
        if (mb_substr($query,0, mb_strlen('CREATE TABLE IF NOT EXISTS')) != 'CREATE TABLE IF NOT EXISTS') {
          /* Если в запросе нет IF NOT EXISTS, то добавим это */
          $query = str_replace('CREATE TABLE','CREATE TABLE IF NOT EXISTS', $query);
        };
        mysqli_db()->query($query);
        echo '<p>'.$query.'</p>';
      }
    }
  }
  if (is_array($resurs['TABLES'] )) {
    foreach ($resurs['TABLES'] as $table=>$fields){
      if (is_array($fields)) {
        foreach ($fields as $field) {
          if (!isFieldExists($table, $field[2]) ) {
            $query = 'ALTER TABLE `'.$table.'` ADD '.$field[1];
            mysqli_db()->query($query);            
            echo '<p>'.$query.'</p>';
          } else {
            $query = 'ALTER TABLE `'.$table.'` MODIFY COLUMN '.$field[1];
            mysqli_db()->query($query);            
            echo '<p>'.$query.'</p>';
          }
        }
      }
    }
  }
}

function ShowForm() {
  echo '<form action="?do=1" enctype="multipart/form-data" method="post">';
  echo '<input type="text" name="host" value="localhost" placeholder="MySQL HOST" title="MySQL HOST"/><br/>';
  echo '<input type="text" name="user" value="" placeholder="MySQL USER" title="MySQL USER"/><br/>';
  echo '<input type="text" name="pass" value="" placeholder="MySQL PASSWORD" title="MySQL PASSWORD"/><br/>';
  echo '<input type="text" name="base" value="" placeholder="MySQL DATEBASE" title="MySQL DATEBASE"/><br/>';
  echo '<input type="file" name="file" placeholder="MySQL DUMP File" title="MySQL DUMP File"/><br/>';
  echo '<input type="submit" value="SUBMIT"/><br/>';  
  echo '</form>';
}

function ActionForm() {
  $uploadfile = tempnam("", "updater_sql".rand(100,999));  
  if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
     echo '<p>Uploaded file</p>';
    
     $CurSql = file_get_contents($uploadfile);

     echo '<p>Content loaded</p>';

     $dbhost = $_POST['host'];
     $dbuser = $_POST['user'];
     $dbpass = $_POST['pass'];
     $dbname = $_POST['base'];
     UpdateDB ($CurSql, $dbhost, $dbuser, $dbpass, $dbname);
  } else {
     echo '<p>ERROR TO UPLOAD FILE</p>';
  }
}

if ($_GET['do']== '') {
  ShowForm();
} else {
  ActionForm();
}
?>