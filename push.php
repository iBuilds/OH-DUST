<?php
    // กรณีต้องการตรวจสอบการแจ้ง error ให้เปิด 3 บรรทัดล่างนี้ให้ทำงาน กรณีไม่ ให้ comment ปิดไป
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // include composer autoload
    require_once 'vendor/autoload.php';
    
    // การตั้งเกี่ยวกับ bot
    require_once 'bot_settings.php';
    
    // กรณีมีการเชื่อมต่อกับฐานข้อมูล
    require_once("dbconnect.php");

    require_once("config.php");
    
    ///////////// ส่วนของการเรียกใช้งาน class ผ่าน namespace
    use LINE\LINEBot;
    use LINE\LINEBot\HTTPClient;
    use LINE\LINEBot\HTTPClient\CurlHTTPClient;
    //use LINE\LINEBot\Event;
    //use LINE\LINEBot\Event\BaseEvent;
    //use LINE\LINEBot\Event\MessageEvent;
    use LINE\LINEBot\MessageBuilder;
    use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
    use LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
    use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;
    use LINE\LINEBot\MessageBuilder\LocationMessageBuilder;
    use LINE\LINEBot\MessageBuilder\AudioMessageBuilder;
    use LINE\LINEBot\MessageBuilder\VideoMessageBuilder;
    use LINE\LINEBot\ImagemapActionBuilder;
    use LINE\LINEBot\ImagemapActionBuilder\AreaBuilder;
    use LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder ;
    use LINE\LINEBot\ImagemapActionBuilder\ImagemapUriActionBuilder;
    use LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder;
    use LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder;
    use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
    use LINE\LINEBot\TemplateActionBuilder;
    use LINE\LINEBot\TemplateActionBuilder\DatetimePickerTemplateActionBuilder;
    use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
    use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
    use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselTemplateBuilder;
    use LINE\LINEBot\MessageBuilder\TemplateBuilder\ImageCarouselColumnTemplateBuilder;
    
    $httpClient = new CurlHTTPClient(LINE_MESSAGE_ACCESS_TOKEN);
    $bot = new LINEBot($httpClient, array('channelSecret' => LINE_MESSAGE_CHANNEL_SECRET));

    $sql = "SELECT User_ID FROM User_Detail WHERE Notification = '1'";
    $query = mysqli_query($conn,$sql);
    if (!$query) {
        printf("Error: %s\n", $conn->error);
        exit();
    }
    $resultArray = array();
    $userIds = array(); 
    while($result = mysqli_fetch_array($query,MYSQLI_ASSOC))
    {
        array_push($resultArray,$result);
    }
    for($length = 0; $length < count($resultArray); $length ++){
        array_push($userIds,$resultArray[$length]["User_ID"]);
    }

    $sql = "SELECT Dust FROM Dust_Log WHERE Location = 'average'";
    $query = mysqli_query($conn,$sql);
    if (!$query) {
        printf("Error: %s\n", $conn->error);
        exit();
    }

    $Dust = array();
    while($result = mysqli_fetch_array($query,MYSQLI_ASSOC))
    {
        array_push($Dust,$result);
    }

    if(isset($_POST["send"])){
        if($_POST["send"] == "Good_Morning"){
            $actionBuilder = array(    
                new PostbackTemplateActionBuilder(
                    'ตรวจสอบพื้นที่อื่นๆ', // ข้อความแสดงในปุ่ม
                    http_build_query(array(
                        'location'=>'all'
                    )) // ข้อมูลที่จะส่งไปใน webhook ผ่าน postback event
                ),      
            );
            $imageUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn%3AANd9GcQdDqUx9bj2KMnm7XWhjAQ-flNnOgUuMHBdgTcHPc7gm_us0tjp';
            $replyData1 = new TemplateMessageBuilder('มหาวิทยาลัยธุรกิจบัณฑิตย์',
                new ButtonTemplateBuilder(
                    'มหาวิทยาลัยธุรกิจบัณฑิตย์',  // กำหนดหัวเรื่อง
                    'ปริมาณฝุ่น PM2.5 : '.$Dust[0]["Dust"].' AQI',   // กำหนดรายละเอียด
                    $imageUrl, // กำหนด url รุปภาพ
                    $actionBuilder  // กำหนด action object
                )
            );
            
            $textReplyMessage = " ";
            if($Dust[0]["Dust"] <= 25) {
                $textReplyMessage = $AQI_Very_Good;
            }
            else if($Dust[0]["Dust"] >= 26 && $Dust[0]["Dust"] <= 50) {
                $textReplyMessage = $AQI_Good;
            }
            else if($Dust[0]["Dust"] >= 51 && $Dust[0]["Dust"] <= 100) {
                $textReplyMessage = $AQI_Fair;
            }
            else if($Dust[0]["Dust"] >= 101 && $Dust[0]["Dust"] <= 200) {
                $textReplyMessage = $AQI_ฺBad;
            }
            else if($Dust[0]["Dust"] > 200) {
                $textReplyMessage = $AQI_Very_Bad;
            }
            $textMessage = new TextMessageBuilder($textReplyMessage);
        
            $multiMessage = new MultiMessageBuilder;
            $multiMessage->add($replyData1);
            $multiMessage->add($textMessage);
            $replyData = $multiMessage;  
        }
        else if($_POST["send"] == "Update"){
            echo $_POST["Value"];
            $Value = $_POST["Value"];
            date_default_timezone_set('Asia/Bangkok');
            $Date = date("Y-m-d H:i:s");
            $sql = "UPDATE Dust_Log SET Time = '$Date', Dust = '$Value' WHERE Location = 'average'";
            $query = mysqli_query($conn,$sql);
            mysqli_close($conn);
        }
    }
        
    $response = $bot->multicast($userIds,$replyData);
    if ($response->isSucceeded()) {
        echo 'Success!';
        return;
    }
    
    // Failed
    echo $response->getHTTPStatus() . ' ' . $response->getRawBody();
?>