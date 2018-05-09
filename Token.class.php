<?php

// Token class to set passwords for users based on a link you send
// Extends db which is needed for a connection (see db class)
class Token extends db
{

  // **PROPERTIES**

  // de token voor het zetten van een password op een account,
  // endDate voor het zetten van een vervaldatum van de token,
  // $data[] om alle data die in de class wordt geladen te houden
  public $id         = "";
  public $token      = "";
  private $endDate   = "";
  public $appliedUID = "";
  private $data      = [];


  // **METHODS**

  // setOverviewData() => true
  // Sets either $this->data with all current existing tokens or with an error
  public function setOverviewData()
  {
    $mysqli = $this->connect();
    $user = new user;
    $query = 'SELECT tbl_tokens.*,
                     tbl_users.initials,
                     tbl_users.lastname,
                     tbl_users.intersert
                FROM tbl_tokens
           LEFT JOIN tbl_users
                  ON tbl_tokens.FK_users_id = tbl_users.id';
    $result = $mysqli->query($query);
    if($result->num_rows >= 1){
      while($row = $result->fetch_assoc()){
        $data[] = $row;
      }
      $this->data = $data;
      $user->correctData($this->data);
      return true;
    }
    $this->data['error'] = 'Geen items gevonden.';
    return true;
  }


  // createToken($uid, $email) => boolean
  // on true: has inserted a new token in the DB set to the given UID and email
  // on false: failed the query
  public function createToken($uid, $email)
  {
    $mysqli = $this->connect();
    $this->generateToken($uid, $email);
    $query = 'INSERT INTO tbl_tokens
                      SET token = "'.$this->token.'",
                          FK_users_id = "'.$uid.'",
                          activated = "0",
                          endDate = "'.$this->endDate.'"';
    if($mysqli->query($query)){
      $this->id = $mysqli->insert_id;
      return true;
    }
    return false;
  }


  // generateToken($uid, $email) => true
  // This will create a hash and sets it to current token which can be used to store in the database
  private function generateToken($uid, $email)
  {
    $time = time();
    $timeNextWeek = time() + (2 * 24 * 60 * 60);
    $this->endDate = date('Y-m-d', $timeNextWeek);
    $string = $time.$uid;
    $this->token = hash('sha256', $string).md5($email);
    return true;
  }


  // validate() => boolean / Will search the database wether the set token exists or not in the db
  // on true: token exists => sets all data which belongs to the tokens
  // on false: token is non-existent => won't do anything
  public function validate()
  {
    $mysqli = $this->connect();
    $query = 'SELECT * FROM tbl_tokens WHERE token = "'.$this->token.'" AND activated = "0"';
    $result = $mysqli->query($query);
    if($result->num_rows){
      $data = $result->fetch_assoc();
      $this->appliedUID = $data['FK_users_id'];
      $this->id = $data['id'];
      return true;
    }
    return false;
  }


  // setToken($token = "") => boolean
  // on true: token has been set and real escape string has been appliedUID
  // on false: no token given
  public function setToken($token = "")
  {
    if(empty($token)){
      return false;
    }
    $mysqli = $this->connect();
    $token = $mysqli->real_escape_string($token);
    $this->token = $token;
    return true;
  }


  // delete() => boolean / This function is to auto-remove all expired tokens in the database
  // on true: delete function worked properly
  // on false: yeah, well... now it didn't!
  public function delete()
  {
    $mysqli = $this->connect();
    $query = 'SELECT * FROM tbl_tokens WHERE endDate =';
    $query = 'DELETE FROM tbl_tokens WHERE activated = "1" OR endDate <= NOW()';
    if($mysqli->query($query)){
      return true;
    }
    return false;
  }


  // destroy() => boolean
  // on true: this will destroy the CURRENT set token
  // on false: either token wasn't set or the token can't be deleted
  public function destroy()
  {
    $mysqli = $this->connect();
    $query = 'UPDATE tbl_tokens
                 SET activated = "1"
               WHERE id = "'. $this->id .'"';
    if($mysqli->query($query)){
      return true;
    }
    return false;
  }


  // getData => $this->data;
  public function getData()
  {
    return $this->data;
  }

}// End of class
