<?php
require_once "define.php";
require_once "library.php";
require_once "sql.php";
require_once "base.php";
require_once "conversations.php";

function build_test_database($Database) {
    $Database->exec(
        "CREATE TABLE IF NOT EXISTS Message (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user CHAR(1),
            message VARCHAR(4096),
            conversation_id CHAR(65),
            time_stamp DATETIME
        )"
    );

    $Database->exec(
        "CREATE TABLE IF NOT EXISTS Conversation (
            id CHAR(65) PRIMARY KEY,
            manager_id INT UNSIGNED,
            username,
            last_edit DATETIME
        )"
    );

    $Database->exec(
        "CREATE TABLE IF NOT EXISTS Manager (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(32) UNIQUE,
            password CHAR(128),
            access_level INT UNSIGNED,
            failed_attempts INT
        )"
    );

    $Database->exec(
        "CREATE TABLE IF NOT EXISTS Ip_Check (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip CHAR(45) UNIQUE,
            failed_attempts INT
        )"
    );
    
}

class New_Conversation_Model_Test extends PHPUnit_Framework_TestCase {
    private $Database, $Model;

    function setUp() {
        $this->Database = new PDO("sqlite::memory:");
        $this->Model = new New_Conversation_Model(array(
            "database"=> new Sequel(array("connection" => $this->Database))
        ));
        build_test_database($this->Database);
    }

    
    private function generate_signature($input) {
        $salt = random_string(New_Conversation_Model::SALT_LENGTH);
        $userHash = sha1($salt . $input);//$this->server['REMOTE_ADDR'] . $this->server['HTTP_USER_AGENT']);
        return $salt . $userHash;
    }

    private function is_signature_match($submittedSignature, $storedSignature) {
        $salt = substr($storedSignature, 0, New_Conversation_Model::SALT_LENGTH);
        $originalHash = substr($storedSignature, New_Conversation_Model::SALT_LENGTH, 40);
        $submittedHash = sha1($salt . $submittedSignature);
        return $submittedHash === $originalHash ? true : false;
    }

    private function start_conversation() {
        return $this->Model->start_conversation(array(
            "username" => "username",
            "signature" => "REMOTE_ADDRHTTP_USER_AGENT"
        ));
    }

    function test_start_conversation_correct_id() {
        $conversationId = $this->start_conversation();
        $this->assertTrue($this->is_signature_match(
            "usernameREMOTE_ADDRHTTP_USER_AGENT",
            $conversationId
        ));
    }

    function test_start_conversation_signature_without_salt_is_40_characters() {
        $this->assertEquals(40, strlen($this->start_conversation()) - New_Conversation_Model::SALT_LENGTH);
    }

    function test_start_conversation_results_logged() {
        $conversationId = $this->start_conversation();

        $Results = $this->Database->query("SELECT * FROM Conversation WHERE id = '$conversationId'");
        $row = $Results->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(
            array(
                "id" => $conversationId,
                "manager_id" => null,
                "username" => "username",
                "last_edit" => date("Y-m-d H:i:s")
            ),
            $row
        );
    }
}



class New_Conversation_Model_Mock {
    function start_conversation(array $fig = array()) {
        return "mock_conversation_id";
    }
}

class New_Conversation_Controller_Test extends PHPUnit_Framework_TestCase {
    private $Controller;

    function setUp() {
        $this->Controller = $this->build_controller_override();
    }

    private function build_controller_override(array $fig = array()) {
        return new New_Conversation_Controller(array(
            "post" => try_array($fig, "post", array("username" => "mock_username")),
            "server" => try_array($fig, "server", array(
                "REMOTE_ADDR" => try_array($fig, "REMOTE_ADDR", "mock_remote_addr"),
                "HTTP_USER_AGENT" => try_array($fig, "HTTP_USER_AGENT", "mock_http_user_agent"),
                "REQUEST_METHOD" => try_array($fig, "REQUEST_METHOD", "POST")
            )),
            "model" => new New_Conversation_Model_Mock()
        ));
    }

    function test_post_success() {
        $response = $this->Controller->respond();
        $this->assertEquals(
            json_encode(array("id" => "mock_conversation_id")),
            $response
        );
    }

    function test_post_no_post_data() {
        $Controller = $this->build_controller_override(array(
            "post" => array()
        ));
        $response = $Controller->respond();
        $this->assertEquals(
            json_encode(array("id" => "mock_conversation_id")),
            $response
        );
    }

    /**
     * @expectedException Bad_Request_Exception
     */
    function test_post_signature_too_short() {
        $padLength = New_Conversation_Controller::MIN_SIGNATURE_LENGTH / 2;
        $Controller = $this->build_controller_override(array(
            "post" => array("username" => null),
            "server" => array(
                "REMOTE_ADDR" => str_pad("", $padLength - 1, "x"),
                "HTTP_USER_AGENT" => str_pad("", $padLength, "y"),
                "REQUEST_METHOD" => "POST"
            )
        ));
        $Controller->respond();
    }
}




class Existing_Conversations_Model_Test extends PHPUnit_Framework_TestCase {
    private $Model, $Database;
    function setUp() {
        $this->Database = new PDO("sqlite::memory:");
        $this->Model = new Existing_Conversation_Model(array(
            "database"=> new Sequel(array("connection" => $this->Database))
        ));
        build_test_database($this->Database);
        $this->insert_default_rows();
    }

    private function insert_default_rows() {
        $this->Database->query(
            "INSERT INTO Conversation (id, last_edit)
             VALUES ('conv_id', '2013-01-01 10:10:10')"
        );

        $this->Database->query(
            "INSERT INTO Message (id, conversation_id, message, time_stamp)
             VALUES (1, 'conv_id', 'message 1', '2013-01-01 10:10:09')"
        );

        $this->Database->query(
            "INSERT INTO Message (id, conversation_id, message, time_stamp)
             VALUES (2, 'conv_id', 'message 2', '2013-01-01 10:10:10')"
        );
    }

    function test_is_updated_true() {
        $this->assertTrue($this->Model->is_updated(array(
            "conversationId" => 'conv_id',
            "last_update" => "2013-01-01 10:10:10"
        )));
    }

    function test_is_updated_false() {
        $this->assertFalse($this->Model->is_updated(array(
            "conversationId" => "conv_id",
            "last_update" => "2013-01-01 10:10:09"
        )));
    }

    //function test_get_updates_no_updates() {}
    //function test_get_updates() {}
}
?>
