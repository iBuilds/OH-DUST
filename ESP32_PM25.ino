#include <WiFi.h>
#include <HTTPClient.h>
#include <time.h>
#include "PMS.h"
#include "SSD1306.h"

PMS pms(Serial2);
PMS::DATA data;

#define WIFI_STA_NAME "HUAWEI P9"
#define WIFI_STA_PASS "12345678"

bool State_Morning = 0;
int timezone = 7 * 3600;
int dst = 0;
int WL_State = 0;
unsigned long Timer = 0;
int Update_Time = 30000;
int AQI = 0;

/////////////////////Time_Noftication/////////////////////
int Hour = 7;
int Min = 0;
//////////////////////////////////////////////////////////

SSD1306  display(0x3c, 4, 15);

void setup() {
  Serial2.begin(9600);
  Serial.begin(115200);

  pinMode(LED_BUILTIN, OUTPUT);
  pinMode(12, OUTPUT);
  digitalWrite(12, LOW);
  delay(50);
  digitalWrite(12, HIGH);
  display.init();

  Serial.print("Connecting to ");
  Serial.println(WIFI_STA_NAME);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_STA_NAME, WIFI_STA_PASS);

  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(LED_BUILTIN, !digitalRead(LED_BUILTIN));
    display.setFont(ArialMT_Plain_16);
    display.setTextAlignment(TEXT_ALIGN_CENTER);
    display.drawString(64, 20, "Connecting...");
    display.display();
    WL_State ++;
    if (WL_State > 5) {
      ESP.restart();
    }
    Serial.print(".");
    delay(500);
  }
  digitalWrite(LED_BUILTIN, HIGH);

  configTime(timezone, dst, "pool.ntp.org", "time.nist.gov");     //ดึงเวลาจาก Server
  Serial.println("\nWaiting for time");
  while (!time(nullptr)) {
    Serial.print(".");
    delay(1000);
  }
  Serial.println("");
  Serial.println("WiFi connected");
  Serial.println("IP address: ");
  Serial.println(WiFi.localIP());

  for (int i = 0; i <= 100; i ++) {
    display.drawProgressBar(0, 32, 120, 10, i);
    display.setFont(ArialMT_Plain_10);
    display.setTextAlignment(TEXT_ALIGN_CENTER);
    display.drawString(64, 15, String(i) + "%");
    display.display();
    display.clear();
    delay(10);
  }
}

void loop1() {
  if (WiFi.status() != WL_CONNECTED) {
    ESP.restart();
  }

  if (pms.read(data)) {
    AQI = data.PM_AE_UG_2_5 * 0.66;
  }
  Serial.print("PM2.5 : ");
  Serial.print(AQI);
  Serial.print(" AQI");
  Serial.println();

  configTime(timezone, dst, "pool.ntp.org", "time.nist.gov");    //ดีงเวลาปัจจุบันจาก Server อีกครั้ง
  time_t now = time(nullptr);
  struct tm* p_tm = localtime(&now);

  delay(500);
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    ESP.restart();
  }

  for (int i = 0; i < 50; i ++) {
    if (pms.read(data)) {
      AQI = data.PM_AE_UG_2_5 * 0.66;
      AQI = AQI + 7;
    }
    Serial.print("PM2.5 : ");
    Serial.print(AQI);
    Serial.print(" AQI");
    Serial.println();
  }

  display.clear();
  display.setFont(ArialMT_Plain_24);
  display.setTextAlignment(TEXT_ALIGN_CENTER);
  display.drawString(64, 20, String(AQI) + " AQI");
  display.display();

  configTime(timezone, dst, "pool.ntp.org", "time.nist.gov");    //ดีงเวลาปัจจุบันจาก Server อีกครั้ง
  time_t now = time(nullptr);
  struct tm* p_tm = localtime(&now);

  if (p_tm->tm_hour == Hour && p_tm->tm_min == Min && State_Morning == 0 && millis() > 10000) {
    while (1) {
      String url = "https://kskhealtec.000webhostapp.com/push.php";
      Serial.println();
      Serial.println("Get content from " + url);
      HTTPClient http;
      http.begin(url);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      int httpCode = http.POST("send=Good_Morning");
      if (httpCode == 200) {
        String content = http.getString();
        Serial.println("Content ---------");
        Serial.println(content);
        Serial.println("-----------------");
        State_Morning = 1;
        break;
      } else {
        Serial.println("Fail. error code " + String(httpCode));
      }
      Serial.println("END");
    }
  }

  if (millis() - Timer >= Update_Time) {
    Timer = millis();
    while (1) {
      String url = "https://kskhealtec.000webhostapp.com/push.php";
      Serial.println();
      Serial.println("Get content from " + url);
      HTTPClient http;
      http.begin(url);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      int httpCode = http.POST("send=Update&Value=" + String(AQI));
      if (httpCode == 200) {
        String content = http.getString();
        Serial.println("Content ---------");
        Serial.println(content);
        Serial.println("-----------------");
        break;
      } else {
        Serial.println("Fail. error code " + String(httpCode));
      }
      Serial.println("END");
    }
  }

  if (p_tm->tm_hour == 0 && p_tm->tm_min == 0 && State_Morning == 1) {
    State_Morning = 1;
  }

  delay(500);
}
