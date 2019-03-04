<?php

// DATBASE LAYOUT FOR THIS CLASS TO WORK:
// id (int) (AI)
// username (varchar)
// hash (varchar) (12 min)
// salt (varchar) (12 min)
// userlevel (varchar) (1 min)

// This class reserves 2 $_SESSION variables:
// - loggedIn => This is used to check if the user is logged in and is defined by the userID
// - userlevel => This is used to keep track of the logged in userlevel
// - oldLocation => This is used to keep track of the last page visited before going to another page


class User extends db
{

  /* PROPERTIES */
  public $id;
  public $username;
  public $password;
  public $userlevel;
  private $salt;
  private $hash;


  /* METHODS */

  // the construct will load in the logged In user by default or do nothing
  public function __construct()
  {
    if(isset($_SESSION['loggedIn']) && !empty($_SESSION['loggedIn'])){
      $this->setUserById($_SESSION['loggedIn']);
      return true;
    }
    return false;
  } // end of construct


  // This function will set this user by the given $id
  // returns a boolean
  public function setUserById($id)
  {
    $mysqli = $this->connect();
    $id = $mysqli->real_escape_string($id);
    $query = "SELECT * FROM tbl_users WHERE id = '$id'";
    $result = $mysqli->query($query);
    if($result->num_rows === 1){
      $data = $result->fetch_assoc();
      $this->id = $id;
      $this->username = $data["username"];
      $this->userlevel = $data["userlevel"];
      return true;
    }
    return false;
  } // End of function


  // login($username, $password) => boolean
  // on true: given username and password match, sessions will be set
  // on false: given userame and password do not match, or do not exist, sessions won't be set
  public function login($username, $password)
  {
    $mysqli = $this->connect(); // Getting mysqli object from db class
    $username = $mysqli->real_escape_string($username); // string escape username
    $password = $mysqli->real_escape_string($password); // string escape password
    $this->username = $username; // setting username
    $this->password = $password; // setting password
    $query = "SELECT * FROM tbl_users WHERE `username` = '$this->username'";
    $result = $mysqli->query($query);
    if($result->num_rows !== 1){
      $this->logout(); // logout all current sessions
      return false; // Username does not exist in db
    }
    $data = $result->fetch_assoc();
    $this->hash = $data['hash']; // setting hash needed for checkCredentials();
    $this->salt = $data['salt']; // setting salt needed for checkCredentials();
    $this->userlevel = $data['userlevel']; // setting the userlevel
    $this->id = $data['id']; // setting the id of the user
    if($this->checkCredentials()){ // checking wether given input is a match, if no => else
      $this->setSessions(); // setting the session, duhuh ;p
      return true; // Login succeeded!
    }
    $this->logout(); // logging out all current sessions
    return false; // the login failed
  } // End of login();


  // lock() => boolean // should this user be able to view this page?
  public function lock($location = NULL, $oldLocation = NULL, $userlevel = "0")
  {
    if(!empty($oldLocation)){
      $_SESSION['oldLocation'] = $oldLocation;
    }
    if(isset($_SESSION['loggedIn']) && isset($_SESSION['userlevel'])){
      if(is_array($userlevel)){
        if(in_array($_SESSION['userlevel'], $userlevel)){ // check wether this user's userlevel is in the allowed userlevels
          return true; // allowed to view this page
        }
      }elseif($userlevel == $_SESSION['userlevel']){
        return true; // userlevel is same as requested userlevel
      }
    }
    if(isset($location) && !empty($location)){
      $this->move($location);
    }
    return false; // did not meet the requirements
  } // End of lock();


  // checkCredentials() => boolean
  // on true: set username and password match
  // on false: they don't
  private function checkCredentials()
  {
    $hashedPass = $this->hashPass();
    if($hashedPass === $this->hash){
      return true;
    }
    return false;
  } // End of checkCredentials();


  // setSessions() => boolean
  // on true: sessions have been set
  // on false: something has gone wrong => error will be set
  private function setSessions()
  {
    $_SESSION['loggedIn'] = $this->id;
    $_SESSION['userlevel'] = $this->userlevel;
    return true;
  } // End of setSessions();


  // hashPass() => will return hashed and salted $this->password
  public function hashPass($password = "", $salt = "")
  {
    if(isset($password) && !empty($password)){
      // code applied on a user input on this function
      $hashedPass = hash('sha256', $password);
      $hashedPass = hash('sha256', $hashedPass.$salt);
      return $hashedPass;
    }
    $password = $this->password;
    $hashedPass = hash('sha256', $password);
    $hashedPass = hash('sha256', $hashedPass.$this->salt);
    return $hashedPass;
  } // End of hashPass();


  // register() => Boolean
  // registers this user as a new user in the system
  public function register()
  {
    if(!empty($this->username) || !empty($this->password) || isset($this->userlevel)){
      $mysqli = $this->connect();
      $this->username = $mysqli->real_escape_string($this->username);
      $this->password = $mysqli->real_escape_string($this->password);
      $this->userlevel = $mysqli->real_escape_string($this->userlevel);
      $this->salt = $this->generateSalt();
      $this->hash = $this->hashPass($this->password, $this->salt);
      $query = "INSERT INTO `tbl_users`
                        SET `username` = '$this->username',
                            `hash` = '$this->hash',
                            `salt` = '$this->salt',
                            `userlevel` = '$this->userlevel'";
      $result = $mysqli->query($query);
      if(!$result){
        return false; // Something went wrong with the query
      }
      return true; // This user has been added to the database
    }
    return false; // Some data has not been set
  } // End of register();


  // generateSalt() => $salt
  // setsCurrent this salt to a random generated one
  public function generateSalt()
  {
    $time = time();
    $add = hash('sha256', $this->username);
    $this->salt = hash('sha256', $time.$add);
    return $this->salt;
  } // End of generateSalt


  // move($location) => moves to $location
  public function move($location = NULL)
  {
    if(empty($location)){
      echo "
      <script type='text/javascript'>
        window.location.href = '".$_SESSION['oldLocation']."';
      </script>
      ";
    }else{
      echo "
      <script type='text/javascript'>
        window.location.href = '$location';
      </script>
      ";
    }
  } // End of move();


  // logout() => return true, removes all user sessions
  public function logout($location = null)
  {
    if(isset($_SESSION)){
      session_destroy();
    }
    if(isset($location) && !empty($location)){
      $this->move($location);
    }
  } // End of logout();


} // End of class
