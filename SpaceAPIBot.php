<?php
    function sendMessage($recipient, $message){
        $token = ""; //PLACE BOT TOKEN HERE
        $apiWebsite = "https://api.telegram.org/bot" . $token . "/";
        
        file_get_contents($apiWebsite . "sendMessage?disable_web_page_preview=1&chat_id=" . $recipient . "&text=" . $message);
    }
    
    function databaseQuery($sql){
        $SQLServer = ""; //PLACE SERVER URL HERE
        $SQLUsername = ""; //PLACE DATABASE USERNAME HERE
        $SQLPassword = ""; //PLACE DATABASE PASSWORD HERE
        $SQLDatabase = ""; //PLACE DATABASE NAME HERE
        
        $con = mysqli_connect($SQLServer,$SQLUsername,$SQLPassword,$SQLDatabase);
        return $con->query($sql);
    }
    
    $spacesTable = ""; //PLACE TABLE NAME CONTAINING THE LIST OF SPACES HERE
    $defaultsTable = ""; //PLACE TABLE NAME CONTAINING THE DEFAULT SPACE PER USER HERE
    
    $jsonContents = utf8_decode(file_get_contents("php://input"));
    $jsonContents = str_replace("\n", "", $jsonContents);;
    
    $contents = json_decode($jsonContents, true);
    $receivedMessage = $contents["message"]["text"];
    $receivedMessage = explode (" ", $receivedMessage);
    $recipient = $contents["message"]["chat"]["id"];
    $command = $receivedMessage[0];
    
    if ($command == "/state"){
        if (isset($receivedMessage[1])){
            unset($receivedMessage[0]);
            $receivedMessage = implode(" ", $receivedMessage);
            
            $json_link = databaseQuery("SELECT * FROM " . $spacesTable . " where space = '" . $receivedMessage . "'")->fetch_all();
            if (!isset($json_link[0][0])){
                $message = $receivedMessage . " is not yet added to the list. Want to add it? Use /add <url>.";
            } else {
                $space = $json_link[0][0];
                $json_link = $json_link[0][1];
                
                $json = utf8_decode(file_get_contents($json_link));
                $decodedJson = json_decode($json, true);
                $open = $decodedJson["state"]["open"];
                
                if ($open){
                    $openedState = "open";
                } else {
                    $openedState = "closed";
                }
                $message = $space . " is " . $openedState . "!";
            }
        } else {
            $configuredSpace = databaseQuery("SELECT * FROM " . $defaultsTable . " where ID = '" . $recipient . "'")->fetch_all();
            if (!isset($configuredSpace[0])){
                $message = "Please specify the hackerspace you want to get the info for. For help, use /start or /help.";
            } else {
                $json_link = databaseQuery("SELECT * FROM " . $spacesTable . " where space = '" . $configuredSpace[0][1] . "'")->fetch_all();
                $space = $json_link[0][0];
                $json_link = $json_link[0][1];
                
                $json = utf8_decode(file_get_contents($json_link));
                $decodedJson = json_decode($json, true);
                $open = $decodedJson["state"]["open"];
                if ($open){
                    $openedState = "open";
                } else {
                    $openedState = "closed";
                }
                $message = $space . " is " . $openedState . "!";
            }
        }
        sendMessage($recipient, $message);
    } else if ($command == "/default" && isset($receivedMessage[1])){
        $checkExistingSpace = databaseQuery("SELECT * FROM " . $spacesTable . " where space = '" . $receivedMessage[1] . "'")->fetch_all();
        if (isset($checkExistingSpace[0])){
            $checkExistingID = databaseQuery("SELECT * FROM " . $defaultsTable . " where ID = '" . $recipient . "'")->fetch_all();
            if (!isset($checkExistingID[0])){
                databaseQuery("INSERT INTO " . $defaultsTable . " (ID, space) VALUES ('" . $recipient . "', '" . $checkExistingSpace[0][0] . "')");
            } else {
                databaseQuery("UPDATE  " . $defaultsTable . " SET space =  '" . $checkExistingSpace[0][0] . "' WHERE  ID = " . $recipient);
            }
            $message = "Default set to " . $checkExistingSpace[0][0] . ".";
        } else {
            $message = "The specified space isn't on the list. Add a different space that's on the list or add a new space to the list.";
        }
        sendMessage($recipient, $message);
    } else if ($command == "/spaces"){
        $message = urlencode("You can look up the status for the following hackerspaces:\n");
        $spaces = databaseQuery("SELECT * FROM " . $spacesTable . " ORDER BY space ASC")->fetch_all();
        foreach ($spaces as $space){
            $message .= urlencode("\n- " . $space[0]);
        }
        $message .= urlencode("\n\nHave a space that's not on the list? Use /add <url>!");
        sendMessage($recipient, $message);
    } else if ($command == "/add"){
        if (isset($receivedMessage[1])){
            $checkExistingURL = databaseQuery("SELECT * FROM " . $spacesTable . " where json_link = '" . $receivedMessage[1] . "'")->fetch_all();
            if (!isset($checkExistingURL[0])){
                $filterFrom = $jsonContents = utf8_decode(file_get_contents("$receivedMessage[1]"));
                $filterFrom = str_replace("\n", "", $filterFrom);
                $filterFrom = json_decode($filterFrom, true);
                
                $spaceName = $filterFrom["space"];
                
                if (!isset($spaceName)){
                    $message = "The URL provided or the JSON returned by the URL is not valid.";
                } else {
                    $checkExistingSpace = databaseQuery("SELECT * FROM " . $spacesTable . " where space = '" . $spaceName . "'")->fetch_all();
                    if (!isset($checkExistingSpace[0])){
                        databaseQuery("INSERT INTO " . $spacesTable . " (space, json_link) VALUES ('" . $spaceName . "', '" . $receivedMessage[1] . "')");
                        $message = $spaceName . " has been added!";
                    } else {
                        $message = $spaceName . " is already added to the list!";
                    }
                }
            } else {
                $message = "The URL is already added to the list!";
            }
        } else {
            $message = urlencode("Please include the JSON URL to use for the space.\nExample: /add https://example.com/json");
        }
        sendMessage($recipient, $message);
   } else if ($command == "/start" || $command == "/help"){
        $message = urlencode("Let's get started!\n\nFirst of all, to get a list of all spaces that are available to use within this bot, use /spaces.\nIf you see a space you'd like to get the status of, use /state <space>.\nWant to set a default? Use /default <space>. You can get the status of the default space with /state.\nIf there's a space that you'd like to use this bot with, use /add <url>.\n\nFor background info about this bot, use /info.\n\nDo you want to completely remove all your preferences stored by this bot? Use /purge.");
        sendMessage($recipient, $message);
    } else if ($command == "/info"){
        $message = urlencode("This bot has been created by @stuiterveer. Shoot me a message if you'd like or visit https://stuiterveer.com/. It's okay, I won't bite!\n\nLooking for the source code for this bot? https://github.com/ACKspace/SpaceAPIBot has everything you need!");
        sendMessage($recipient, $message);
    }  else if ($command == "/purge"){
        databaseQuery("DELETE FROM " . $defaultsTable . " WHERE `ID` = " .  $recipient);
        $message = "All data that's stored for your account by this bot is removed!";
        sendMessage($recipient, $message);
    }
?>
