<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Bhargav Bvs
 */
class DbHandler
{

    private $conn;

    function __construct()
    {
        require_once 'C:\xampp\htdocs\falconbackend\include\DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    public function checkLogin($username, $email)
    {
        // Checking the login details
        $sql = "SELECT * FROM `user_details` WHERE username = '$username' AND email_address = '$email'";
        $result = $this->conn->query($sql)->fetch_assoc();

        if ($result != NULL) {
            return 1;
        } else {
            return 0;
        }
    }

    public function getInboxData($user_id)
    {
        $sql = "SELECT t1.id, t1.forwarded, t4.subject, t4.body, t1.created_date as received_date, t2.username as from_username, t2.email_address 
                as from_email, t3.username, t3.email_address FROM 
                (SELECT id, message_id, from_user_id, created_date, forwarded FROM inbox WHERE user_id = '$user_id') as t1 INNER JOIN 
                (SELECT username, user_id, email_address FROM user_details) as t2 INNER JOIN 
                (SELECT username, email_address FROM user_details WHERE user_id = '$user_id') as t3 INNER JOIN 
                (SELECT message_id, body, subject FROM messages) as t4 
                WHERE t2.user_id = t1.from_user_id and t4.message_id = t1.message_id ORDER BY t1.created_date DESC";

        $inbox_data = $this->conn->query($sql);
        $result = array();

        while ($item = $inbox_data->fetch_assoc()) {
            $id = $item['id'];
            $sql = "SELECT body, created_date FROM `replies` WHERE inbox_id = '$id'";
            $reply = $this->conn->query($sql);

            $sql = "SELECT file_name FROM `attachments` WHERE inbox_id = '$id'";
            $attachments_result = $this->conn->query($sql);

            $sql = "SELECT t3.email_address, t3.username FROM 
                (SELECT sent_id FROM cc_emails WHERE inbox_id = '$id') as t1 INNER JOIN 
                (SELECT sent_id,inbox_id, user_id FROM cc_emails) as t2 INNER JOIN 
                (SELECT user_id,username, email_address FROM user_details) as t3 
                WHERE t2.sent_id = t1.sent_id AND t2.user_id = t3.user_id";
            $cc_emails = $this->conn->query($sql);
            $item['replies'] = array();
            $item['cc_emails'] = array();
            $item['attachments'] = array();
            while ($cc_emails_item = $cc_emails->fetch_assoc()) {
                array_push($item['cc_emails'], $cc_emails_item);
            }
            while ($reply_item = $reply->fetch_assoc()) {
                array_push($item['replies'], $reply_item);
            }
            while ($attachment_item = $attachments_result->fetch_assoc()) {
                array_push($item['attachments'], $attachment_item);
            }
            array_push($result, $item);
        }
        return $result;
    }

    public function getSentData($user_id)
    {
        $sql = "SELECT t1.id, t4.subject, t4.body, t1.created_date as received_date, t2.username as to_username, t2.email_address as 
                from_email, t3.username, t3.email_address, t1.forwarded FROM
                (SELECT id, message_id, to_user_id, created_date, forwarded FROM sent WHERE user_id = '$user_id') as t1 INNER JOIN
                (SELECT username, user_id, email_address FROM user_details) as t2 INNER JOIN
                (SELECT username, email_address FROM user_details WHERE user_id = '$user_id') as t3 INNER JOIN 
                (SELECT message_id, body, subject FROM messages) as t4 
                WHERE t2.user_id = t1.to_user_id and t4.message_id = t1.message_id ORDER BY t1.created_date DESC";

        $inbox_data = $this->conn->query($sql);
        $result = array();

        while ($item = $inbox_data->fetch_assoc()) {
            $id = $item['id'];
            $sql = "SELECT body, created_date FROM `replies` WHERE sent_id = '$id'";
            $reply = $this->conn->query($sql);

            $sql = "SELECT file_name FROM `attachments` WHERE sent_id = '$id'";
            $attachments_result = $this->conn->query($sql);

            $sql = "SELECT t3.email_address, t3.username FROM 
                (SELECT inbox_id,sent_id, user_id FROM cc_emails) as t2 INNER JOIN 
                (SELECT user_id,username, email_address FROM user_details) as t3 
                WHERE t2.sent_id = '$id' AND t2.user_id = t3.user_id";
            $cc_emails = $this->conn->query($sql);

            $item['replies'] = array();
            $item['cc_emails'] = array();
            $item['attachments'] = array();
            while ($cc_emails_item = $cc_emails->fetch_assoc()) {
                array_push($item['cc_emails'], $cc_emails_item);
            }
            while ($reply_item = $reply->fetch_assoc()) {
                array_push($item['replies'], $reply_item);
            }
            while ($attachment_item = $attachments_result->fetch_assoc()) {
                array_push($item['attachments'], $attachment_item);
            }

            array_push($result, $item);
        }
        return $result;
    }


    public function getDraftsData($user_id)
    {
        $sql = "SELECT t1.id, t1.subject, t1.body, t1.created_date as received_date, t2.username as to_username, t2.email_address as 
                to_email, t3.username, t3.email_address FROM
                (SELECT id, subject, body, to_user_id, created_date FROM drafts WHERE user_id = '$user_id') as t1 INNER JOIN
                (SELECT username, user_id, email_address FROM user_details) as t2 INNER JOIN
                (SELECT username, email_address FROM user_details WHERE user_id = 1) as t3 WHERE t2.user_id = t1.to_user_id";

        $result = $this->conn->query($sql);

        return $result;
    }

    public function getTrashData($user_id)
    {
        $sql = "SELECT t1.id, t1.subject, t1.body, t1.created_date as received_date, t2.username as to_username, t2.email_address as 
                to_email, t3.username, t3.email_address FROM
                (SELECT id, subject, body, to_user_id, created_date FROM trash WHERE user_id = 1) as t1 INNER JOIN
                (SELECT username, user_id, email_address FROM user_details) as t2 INNER JOIN
                (SELECT username, email_address FROM user_details WHERE user_id = 1) as t3 WHERE t2.user_id = t1.to_user_id\"";

        $result = $this->conn->query($sql);

        return $result;
    }

    public function compose($user_id, $subject, $body, $emails, $forwarded, $draft, $attachment)
    {
        $email = $emails[0];
        $sql = "SELECT user_id FROM user_details WHERE email_address = '$email'";
        $result = $this->conn->query($sql)->fetch_assoc();

        if ($result != NULL && $result['user_id'] != 0) {
            $to_user_id = $result['user_id'];

            $sql = "SELECT message_id FROM messages WHERE subject= '$subject' AND  body = '$body'";
            $result = $this->conn->query($sql)->fetch_assoc();
            if ($result == NULL) {
                $sql = "INSERT INTO messages (subject, body) VALUES ('$subject', '$body')";
                $this->conn->query($sql);
                $message_id = $this->conn->insert_id;
            } else {
                $message_id = $result['message_id'];
            }

            if ($draft == 0) {
                $sql = "INSERT INTO inbox (message_id, user_id, from_user_id, forwarded) VALUES  
                    ('$message_id','$to_user_id', '$user_id', '$forwarded')";
                $this->conn->query($sql);
                $inbox_id = $this->conn->insert_id;

                $sql = "INSERT INTO sent (message_id, user_id, to_user_id, forwarded) VALUES  
                    ('$message_id','$user_id', '$to_user_id', '$forwarded')";
                $this->conn->query($sql);
                $sent_id = $this->conn->insert_id;

                if ($attachment) {
                    for ($i = 0; $i < sizeof($attachment); $i++) {
                        $sql = "INSERT INTO attachments (file_name, inbox_id, sent_id, reply_id) VALUES 
                      ('$attachment[$i]', $inbox_id, $sent_id,0)";
                        $this->conn->query($sql);
                    }
                }

            } else if ($draft == 1) {
                $sql = "INSERT INTO drafts (message_id, user_id, to_user_id) VALUES  
                    ('$message_id','$user_id', '$to_user_id')";
                $this->conn->query($sql);
            }
        }

        if (sizeof($emails) > 1) {

            $sql = "INSERT INTO cc_emails (user_id, inbox_id, sent_id) VALUES 
                        ('$to_user_id', '$inbox_id', '$sent_id')";
            $this->conn->query($sql);

            foreach (array_slice($emails, 1) as $email) {
                $sql = "SELECT user_id FROM user_details WHERE email_address = '$email'";
                $result = $this->conn->query($sql)->fetch_assoc();

                if ($result != NULL && $result['user_id'] != 0) {
                    $to_user_id = $result['user_id'];

                    $sql = "INSERT INTO inbox (message_id, user_id, from_user_id, forwarded) VALUES  
                    ('$message_id','$to_user_id', '$user_id', '$forwarded')";
                    $this->conn->query($sql);
                    $inbox_id = $this->conn->insert_id;

                    $sql = "INSERT INTO cc_emails (user_id, inbox_id, sent_id) VALUES 
                        ('$to_user_id', '$inbox_id', '$sent_id')";
                    $this->conn->query($sql);

                }
            }
        }

        return 1;

    }

    public function reply($reply, $user_id, $inbox_ids, $attachment)
    {
        $sql = "SELECT t2.id FROM 
                (SELECT message_id, user_id, from_user_id FROM `inbox` WHERE id = '$inbox_ids[0]') as t1 INNER JOIN 
                (SELECT id, message_id, user_id, to_user_id FROM sent) as t2 
                WHERE t1.message_id = t2.message_id and t1.user_id = t2.to_user_id and t1.from_user_id = t2.user_id";

        $result = $this->conn->query($sql)->fetch_assoc();
        $sent_id = $result['id'];

        if ($user_id != NULL && $user_id > 0) {
            $size = sizeof($inbox_ids);
            for ($j = 0; $j < $size; $j++) {
                $sql = "INSERT INTO replies (body, inbox_id, sent_id, user_id) VALUES 
                  ('$reply', $inbox_ids[$j], $sent_id,$user_id)";
                $this->conn->query($sql);
                $reply_id = $this->conn->insert_id;

                echo $j;

                if ($attachment) {
                    for ($i = 0; $i < sizeof($attachment); $i++) {
                        echo $i;
                        $sql = "INSERT INTO attachments (file_name, inbox_id, sent_id, reply_id) VALUES 
                      ('$attachment[$i]', $inbox_ids[$j], $sent_id, $reply_id)";
                        $this->conn->query($sql);
                    }
                }
            }
        }
    }

    public function forward($body_user, $user_id, $inbox_ids, $emails)
    {
        $email = $emails[0];
        $sql = "SELECT user_id, username FROM user_details WHERE email_address = '$email'";
        $result1 = $this->conn->query($sql)->fetch_assoc();

        $sql = "SELECT t2.subject, t2.body, t3.username, t3.email_address FROM 
                (SELECT message_id, user_id, from_user_id FROM `inbox` WHERE id = '$inbox_ids[0]') as t1 INNER JOIN 
                (SELECT message_id, subject, body FROM messages) as t2 INNER JOIN 
                (SELECT user_id, username, email_address From user_details) as t3 
                WHERE t1.message_id = t2.message_id and t3.user_id = t1.from_user_id";

        $result = $this->conn->query($sql)->fetch_assoc();
        $subject = $result['subject'];
        $body_initial = $result['body'];

        $body = '---------- Forwarded message ---------- \r\n From:' . $result['username'] .
            '\r\n Subject:' . $subject . '\r\n \r\n \r\n' .$body_user.'   '.$body_initial;

        if ($result1 != NULL && $result1['user_id'] != 0) {
            $to_user_id = $result1['user_id'];

            $subject = 'Fwd: ' . $subject;

            $sql = "SELECT message_id FROM messages WHERE subject= '$subject' AND  body = '$body'";
            $result = $this->conn->query($sql)->fetch_assoc();
            if ($result == NULL) {
                $sql = "INSERT INTO messages (subject, body) VALUES ('$subject', '$body')";
                $this->conn->query($sql);
                $message_id = $this->conn->insert_id;
            } else {
                $message_id = $result['message_id'];
            }

            $sql = "INSERT INTO inbox (message_id, user_id, from_user_id, forwarded) VALUES  
                    ('$message_id','$to_user_id', '$user_id', '1')";
            $this->conn->query($sql);
            $inbox_id = $this->conn->insert_id;

            $sql = "INSERT INTO sent (message_id, user_id, to_user_id, forwarded) VALUES  
                    ('$message_id','$user_id', '$to_user_id', '1')";
            $this->conn->query($sql);
            $sent_id = $this->conn->insert_id;

            $sql = "SELECT id, body, user_id FROM `replies` WHERE inbox_id= '$inbox_ids[0]'";
            $replies_result = $this->conn->query($sql);
            $replies_combine = array();

            while ($replies = $replies_result->fetch_assoc()){

                $body_reply = $replies['body'];
                $user_id_reply = $replies['user_id'];
                $reply_id_intial = $replies['id'];

                $sql = "INSERT INTO replies (body, inbox_id, sent_id, user_id) VALUES
                  ('$body_reply', $inbox_id, $sent_id, $user_id_reply)";
                $this->conn->query($sql);
                $reply_id_new = $this->conn->insert_id;
                $old = array($reply_id_intial, $reply_id_new);
                array_push($replies_combine, $old);
            }
            print_r($replies_combine);

            $sql = "SELECT file_name FROM `attachments` WHERE inbox_id = '$inbox_ids[0]' and reply_id = 0";
            $attachments_result = $this->conn->query($sql);

            while ($attachment = $attachments_result->fetch_assoc()){
                $file_name = $attachment['file_name'];

                $sql = "INSERT INTO attachments (file_name, inbox_id, sent_id, reply_id) VALUES 
                      ('$file_name', $inbox_id, $sent_id, 0)";
                $this->conn->query($sql);
            }

            $sql = "SELECT reply_id, file_name FROM `attachments` WHERE inbox_id = '$inbox_ids[0]' and reply_id > 0";
            $attachments_result = $this->conn->query($sql);

            while ($attachment = $attachments_result->fetch_assoc()){
                $file_name = $attachment['file_name'];
                $reply_id_old = $attachment['reply_id'];

                for($i = 0; $i<sizeof($replies_combine); $i++){
                    if($replies_combine[$i][0] == $reply_id_old){
                        $reply_id_final = $replies_combine[$i][1];
                    }
                }
                $sql = "INSERT INTO attachments (file_name, inbox_id, sent_id, reply_id) VALUES 
                      ('$file_name', $inbox_id, $sent_id, $reply_id_final)";
                $this->conn->query($sql);
            }
        }

        if (sizeof($emails) > 1) {

            $sql = "INSERT INTO cc_emails (user_id, inbox_id, sent_id) VALUES 
                        ('$to_user_id', '$inbox_id', '$sent_id')";
            $this->conn->query($sql);

            foreach (array_slice($emails, 1) as $email) {
                $sql = "SELECT user_id FROM user_details WHERE email_address = '$email'";
                $result = $this->conn->query($sql)->fetch_assoc();

                if ($result != NULL && $result['user_id'] != 0) {
                    $to_user_id = $result['user_id'];

                    $sql = "INSERT INTO inbox (message_id, user_id, from_user_id, forwarded) VALUES  
                    ('$message_id','$to_user_id', '$user_id', '1')";
                    $this->conn->query($sql);
                    $inbox_id = $this->conn->insert_id;

                    $sql = "INSERT INTO cc_emails (user_id, inbox_id, sent_id) VALUES 
                        ('$to_user_id', '$inbox_id', '$sent_id')";
                    $this->conn->query($sql);

                }
            }
        }

        return 1;
    }

    public function storeDraft($user_id, $subject, $body, $email)
    {
        $sql = "SELECT user_id FROM user_details WHERE email_address = '$email'";
        $result = $this->conn->query($sql)->fetch_assoc();

        if ($result != NULL) {
            $to_user_id = $result['user_id'];
            $sql = "INSERT INTO messages (subject, body) VALUES ('$subject', '$body')";
            $result = $this->conn->query($sql);

            $sql = "SELECT message_id FROM messages WHERE subject = '$subject' AND body = '$body'";
            $result = $this->conn->query($sql)->fetch_assoc();

            $message_id = $result['message_id'];

            $sql = "INSERT INTO user_message_mapping (user_id, message_id, location_id) VALUES ($user_id, $message_id, 3)";
            echo $sql;
            $this->conn->query($sql);

        }
    }

    public function message($id, $from, $user_id)
    {
        if ($from == 1) {
            $sql = "SELECT t1.id as inbox_id,t4.subject, t4.body, t1.created_date as received_date, t2.username as from_username, t2.email_address 
                as from_email, t3.username, t3.email_address FROM 
                (SELECT id, message_id, from_user_id, created_date FROM inbox WHERE user_id = '$user_id' and id = '$id') as t1 INNER JOIN 
                (SELECT username, user_id, email_address FROM user_details) as t2 INNER JOIN 
                (SELECT username, email_address FROM user_details WHERE user_id = '$user_id') as t3 INNER JOIN 
                (SELECT message_id, body, subject FROM messages) as t4 WHERE 
                t2.user_id = t1.from_user_id and t4.message_id = t1.message_id";

            $inbox_data = $this->conn->query($sql);
            $result = array();

            while ($item = $inbox_data->fetch_assoc()) {
                $sql = "SELECT t2.id, t2.body, t2.created_date, t1.username, t1.email_address as email_address FROM 
                        (SELECT user_id, username, email_address FROM user_details) as t1 INNER JOIN
                        (SELECT id, body, created_date, user_id FROM `replies` WHERE inbox_id = '$id') as t2
                        ON t1.user_id = t2.user_id ORDER BY t2.created_date ASC ";
                $reply = $this->conn->query($sql);

                $sql = "SELECT file_name FROM `attachments` WHERE inbox_id = '$id' and reply_id='0'";
                $attachments_result = $this->conn->query($sql);

                $sql = "SELECT t3.email_address, t3.username, t2.sent_id, t2.inbox_id FROM 
                (SELECT sent_id FROM cc_emails WHERE inbox_id = '$id') as t1 INNER JOIN 
                (SELECT sent_id,inbox_id, user_id FROM cc_emails) as t2 INNER JOIN 
                (SELECT user_id,username, email_address FROM user_details) as t3 
                WHERE t2.sent_id = t1.sent_id AND t2.user_id = t3.user_id";
                $cc_emails = $this->conn->query($sql);
                $item['replies'] = array();
                $item['cc_emails'] = array();
                $item['attachments'] = array();
                while ($cc_emails_item = $cc_emails->fetch_assoc()) {
                    array_push($item['cc_emails'], $cc_emails_item);
                }
                while ($reply_item = $reply->fetch_assoc()) {
                    $reply_item['attachments'] = array();
                    $reply_id = $reply_item['id'];
                    $sql="SELECT file_name FROM `attachments` WHERE reply_id='$reply_id'";
                    $data = $this->conn->query($sql);
                    while ($data_item = $data->fetch_assoc()){
                        array_push($reply_item['attachments'],$data_item);
                    }
                    array_push($item['replies'], $reply_item);
                }
                while ($attachment_item = $attachments_result->fetch_assoc()) {
                    array_push($item['attachments'], $attachment_item);
                }
                array_push($result, $item);
            }

        } elseif ($from == 2) {
            $sql = "SELECT t1.id as sent_id, t5.id as inbox_id, t4.subject, t4.body, t1.created_date as received_date, t2.username as to_username, t2.email_address 
                as from_email, t3.username, t3.email_address FROM 
                (SELECT id, message_id, to_user_id, created_date FROM sent WHERE user_id = '$user_id' and id = '$id') as t1 INNER JOIN 
                (SELECT username, user_id, email_address FROM user_details) as t2 INNER JOIN 
                (SELECT username, email_address FROM user_details WHERE user_id = '$user_id') as t3 INNER JOIN 
                (SELECT message_id, body, subject FROM messages) as t4 INNER JOIN 
                (SELECT id, user_id, message_id FROM inbox WHERE from_user_id = '$user_id') as t5
                WHERE t2.user_id = t1.to_user_id and t4.message_id = t1.message_id and t1.message_id = t5.message_id 
                and t5.user_id = t1.to_user_id";

            $inbox_data = $this->conn->query($sql);
            $result = array();

            while ($item = $inbox_data->fetch_assoc()) {
                $sql = "SELECT t2.id, t2.body, t2.created_date, t1.username, t1.email_address as email_address FROM 
                        (SELECT user_id, username, email_address FROM user_details) as t1 INNER JOIN
                        (SELECT id, body, created_date, user_id FROM `replies` WHERE sent_id = '$id' GROUP BY body) as t2
                        ON t1.user_id = t2.user_id ORDER BY t2.created_date ASC ";
                $reply = $this->conn->query($sql);

                $sql = "SELECT file_name FROM `attachments` WHERE sent_id = '$id' and reply_id = 0";
                $attachments_result = $this->conn->query($sql);

                $sql = "SELECT t3.email_address, t3.username, t2.sent_id, t2.inbox_id FROM 
                (SELECT sent_id,inbox_id, user_id FROM cc_emails) as t2 INNER JOIN 
                (SELECT user_id,username, email_address FROM user_details) as t3 
                WHERE t2.sent_id = '$id' AND t2.user_id = t3.user_id";
                $cc_emails = $this->conn->query($sql);
                $item['replies'] = array();
                $item['cc_emails'] = array();
                $item['attachments'] = array();

                while ($cc_emails_item = $cc_emails->fetch_assoc()) {
                    array_push($item['cc_emails'], $cc_emails_item);
                }
                while ($reply_item = $reply->fetch_assoc()) {
                    $reply_item['attachments'] = array();
                    $reply_id = $reply_item['id'];
                    $sql="SELECT file_name FROM `attachments` WHERE reply_id='$reply_id'";
                    $data = $this->conn->query($sql);
                    while ($data_item = $data->fetch_assoc()){
                        array_push($reply_item['attachments'],$data_item);
                    }
                    array_push($item['replies'], $reply_item);
                }
                while ($attachment_item = $attachments_result->fetch_assoc()) {
                    array_push($item['attachments'], $attachment_item);
                }
                array_push($result, $item);
            }
        }


        return $result;


    }

    public function get_user_id($username, $email)
    {
        $sql = "SELECT user_id FROM user_details WHERE username ='$username' and email_address = '$email'";
        $result = $this->conn->query($sql)->fetch_assoc();
        return $result['user_id'];
    }

    public function move_to_trash($message_id, $user_id)
    {
        $sql = "UPDATE user_message_mapping SET location_id = 4 WHERE message_id = $message_id and 
        user_id = $user_id";
        $result = $this->conn->query($sql);

    }


}

?>
