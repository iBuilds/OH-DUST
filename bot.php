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

require_once("flex_gen.php");
 
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
use LINE\LINEBot\Constant\Flex\ComponentIconSize;
use LINE\LINEBot\Constant\Flex\ComponentImageSize;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
use LINE\LINEBot\Constant\Flex\ComponentFontSize;
use LINE\LINEBot\Constant\Flex\ComponentFontWeight;
use LINE\LINEBot\Constant\Flex\ComponentMargin;
use LINE\LINEBot\Constant\Flex\ComponentSpacing;
use LINE\LINEBot\Constant\Flex\ComponentButtonStyle;
use LINE\LINEBot\Constant\Flex\ComponentButtonHeight;
use LINE\LINEBot\Constant\Flex\ComponentSpaceSize;
use LINE\LINEBot\Constant\Flex\ComponentGravity;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\BubbleStylesBuilder;
use LINE\LINEBot\MessageBuilder\Flex\BlockStyleBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\IconComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\SpacerComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\FillerComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\SeparatorComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
 
$httpClient = new CurlHTTPClient(LINE_MESSAGE_ACCESS_TOKEN);
$bot = new LINEBot($httpClient, array('channelSecret' => LINE_MESSAGE_CHANNEL_SECRET));
 
// คำสั่งรอรับการส่งค่ามาของ LINE Messaging API
$content = file_get_contents('php://input');
 
// แปลงข้อความรูปแบบ JSON  ให้อยู่ในโครงสร้างตัวแปร array
$events = json_decode($content, true);
if(!is_null($events)){
    // ถ้ามีค่า สร้างตัวแปรเก็บ replyToken ไว้ใช้งาน
    $replyToken = $events['events'][0]['replyToken'];
    $userID = $events['events'][0]['source']['userId'];
    $sourceType = $events['events'][0]['source']['type'];        
    $is_postback = NULL;
    $is_message = NULL;

    $sql = "SELECT User_ID FROM User_Detail WHERE User_ID = '$userID'";
    $query = mysqli_query($conn,$sql);
    if (!$query) {
        printf("Error: %s\n", $conn->error);
        exit();
    }
    $resultArray = array();
    while($result = mysqli_fetch_array($query,MYSQLI_ASSOC))
    {
        array_push($resultArray,$result);
    }
    if(count($resultArray) == 0){
        $sql = "INSERT INTO User_Detail (User_ID, Notification) VALUES ('$userID', '1')";
        $query = mysqli_query($conn,$sql);
    }
    if(isset($events['events'][0]) && array_key_exists('message',$events['events'][0])){
        $is_message = true;
        $typeMessage = $events['events'][0]['message']['type'];
        $userMessage = $events['events'][0]['message']['text'];     
        $idMessage = $events['events'][0]['message']['id'];          
    }
    if(isset($events['events'][0]) && array_key_exists('postback',$events['events'][0])){
        $is_postback = true;
        $dataPostback = NULL;
        parse_str($events['events'][0]['postback']['data'],$dataPostback);;
        $paramPostback = NULL;
        if(array_key_exists('params',$events['events'][0]['postback'])){
            if(array_key_exists('date',$events['events'][0]['postback']['params'])){
                $paramPostback = $events['events'][0]['postback']['params']['date'];
            }
            if(array_key_exists('time',$events['events'][0]['postback']['params'])){
                $paramPostback = $events['events'][0]['postback']['params']['time'];
            }
            if(array_key_exists('datetime',$events['events'][0]['postback']['params'])){
                $paramPostback = $events['events'][0]['postback']['params']['datetime'];
            }                       
        }
    }   
    if(!is_null($is_postback)){
        $textReplyMessage = "ข้อความจาก Postback Event Data = ";
        if(is_array($dataPostback)){
            for($num_location = 0; $num_location < count($Device); $num_location ++){
                if($Device[$num_location]["Device_ID"] == $dataPostback['location']){
                    $actionBuilder = array(    
                        new PostbackTemplateActionBuilder(
                            'ตรวจสอบพื้นที่อื่นๆ', // ข้อความแสดงในปุ่ม
                            http_build_query(array(
                                'location'=>'all'
                            )) // ข้อมูลที่จะส่งไปใน webhook ผ่าน postback event
                        ),      
                    );

                    $Location = $dataPostback['location'];
                    $sql = "SELECT Dust FROM Dust_Log WHERE Location = '$Location'";
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

                    $sql = "SELECT Time FROM Dust_Log WHERE Location = '$Location'";
                    $query = mysqli_query($conn,$sql);
                    if (!$query) {
                        printf("Error: %s\n", $conn->error);
                        exit();
                    }

                    $Time = array();
                    while($result = mysqli_fetch_array($query,MYSQLI_ASSOC))
                    {
                        array_push($Time,$result);
                    }
                    mysqli_close($conn);

                    if (count($Dust[0]["Dust"]) == 0) {
                        $replyData = new TemplateMessageBuilder($Device[$num_location]["Short_Name"],
                            new ButtonTemplateBuilder(
                                $Device[$num_location]["Short_Name"], // กำหนดหัวเรื่อง
                                'ปริมาณฝุ่น PM2.5 : NULL', // กำหนดรายละเอียด
                                $Device[$num_location]["Image_URL"], // กำหนด url รุปภาพ
                                $actionBuilder  // กำหนด action object
                            )
                        );
                    }
                    else {
                        $replyData = new TemplateMessageBuilder($Device[$num_location]["Short_Name"],
                            new ButtonTemplateBuilder(
                                $Device[$num_location]["Short_Name"], // กำหนดหัวเรื่อง
                                'ข้อมูลวันที่ : '.$Time[0]["Time"].'        ปริมาณฝุ่น PM2.5 : '.$Dust[0]["Dust"].' AQI', // กำหนดรายละเอียด
                                $Device[$num_location]["Image_URL"], // กำหนด url รุปภาพ
                                $actionBuilder  // กำหนด action object
                            )
                        );
                    }
                    
                }
            }
            if($dataPostback['location'] == "all"){
                $multiMessage = new MultiMessageBuilder;
                for($num_location = 0; $num_location < count($Device); $num_location ++){
                    ${'actionBuilder' . $num_location} = array(
                        new PostbackTemplateActionBuilder(
                            'ตรวจสอบปริมาณฝุ่น', // ข้อความแสดงในปุ่ม
                            http_build_query(array(
                                'location' => $Device[$num_location]["Device_ID"]
                            )) // ข้อมูลที่จะส่งไปใน webhook ผ่าน postback event
                        )    
                    );
                }
                $resultArray = array();
                for($num_location = 0; $num_location < count($Device); $num_location ++){
                    array_push(
                        $resultArray,
                        new CarouselColumnTemplateBuilder(
                            $Device[$num_location]["Name"],
                            $Device[$num_location]["Detail"],
                            $Device[$num_location]["Image_URL"],
                            ${'actionBuilder' . $num_location}
                        )
                    );
                }
                $replyData = new TemplateMessageBuilder('ตรวจสอบปริมาณฝุ่น PM2.5',
                    new CarouselTemplateBuilder(
                        $resultArray
                    )
                );
            }
        }
        if(!is_null($paramPostback)){
            $textReplyMessage = " \r\nParams = ".$paramPostback;
        }
    }
    if(!is_null($is_message)){
        switch ($typeMessage){
            case 'text':
                $userMessage = strtolower($userMessage); // แปลงเป็นตัวเล็ก สำหรับทดสอบ
                switch ($userMessage) {
                    case "pm2.5":
                        $multiMessage = new MultiMessageBuilder;
                        for($num_location = 0; $num_location < count($Device); $num_location ++){
                            ${'actionBuilder' . $num_location} = array(
                                new PostbackTemplateActionBuilder(
                                    'ตรวจสอบปริมาณฝุ่น', // ข้อความแสดงในปุ่ม
                                    http_build_query(array(
                                        'location' => $Device[$num_location]["Device_ID"]
                                    )) // ข้อมูลที่จะส่งไปใน webhook ผ่าน postback event
                                )    
                            );
                        }
                        $resultArray = array();
                        for($num_location = 0; $num_location < count($Device); $num_location ++){
                            array_push(
                                $resultArray,
                                new CarouselColumnTemplateBuilder(
                                    $Device[$num_location]["Name"],
                                    $Device[$num_location]["Detail"],
                                    $Device[$num_location]["Image_URL"],
                                    ${'actionBuilder' . $num_location}
                                )
                            );
                        }
                        $replyData = new TemplateMessageBuilder('ตรวจสอบปริมาณฝุ่น PM2.5',
                            new CarouselTemplateBuilder(
                                $resultArray
                            )
                        );
                        break;                                                                                                                                                                                      
                    default:
                        $textReplyMessage = "ขออภัยครับ เราไม่เข้าใจ";
                        $replyData = new TextMessageBuilder($textReplyMessage);
                        break;                                    
                }
                break;
            default:
                $textReplyMessage = json_encode($events);
                $replyData = new TextMessageBuilder($textReplyMessage);
                break;
        }
    }
}
$response = $bot->replyMessage($replyToken,$replyData);
if ($response->isSucceeded()) {
    echo 'Succeeded!';
    return;
}
 
// Failed
echo $response->getHTTPStatus() . ' ' . $response->getRawBody();
?>