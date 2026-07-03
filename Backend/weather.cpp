#include <iostream>
#include <string>
#include <curl/curl.h>
#include <sqlite3.h>
#include "json.hpp"

using json = nlohmann::json;

size_t WriteCallback(void* contents, size_t size, size_t nmemb, void* userp)
{
    size_t totalSize = size * nmemb;
    std::string* buffer = static_cast<std::string*>(userp);
    buffer->append(static_cast<char*>(contents), totalSize);
    return totalSize;
}

int main()
{
    std::string apiKey = " Key ";
    std::string city = " City ";
    std::string dbPath = "/opt/weather/weather.db"; 
    std::string url = "https://api.openweathermap.org/data/2.5/weather?q=" + 
                      city + "&appid=" + apiKey + "&units=metric&lang=en";

    CURL* curl = curl_easy_init();
    std::string response;

    if (!curl) {
        std::cerr << "CURL init error" << std::endl;
        return 1;
    }

    curl_easy_setopt(curl, CURLOPT_URL, url.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, WriteCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 10L);

    CURLcode res = curl_easy_perform(curl);

    if (res != CURLE_OK) {
        std::cerr << "CURL Error: " << curl_easy_strerror(res) << std::endl;
        curl_easy_cleanup(curl);
        return 1;
    }

    curl_easy_cleanup(curl);

    try {
        json j = json::parse(response);

        double temp = j["main"]["temp"];
        double feels = j["main"]["feels_like"];
        int hum = j["main"]["humidity"];
        int press = j["main"]["pressure"];
        double wind = j["wind"]["speed"];

        std::string desc = j["weather"][0]["description"];
        std::string icon = j["weather"][0]["icon"];
        std::string name = j["name"];

        sqlite3* db;
        if (sqlite3_open(dbPath.c_str(), &db) != SQLITE_OK) {
            std::cerr << "Database Error" << std::endl;
            return 1;
        }

        std::string query = 
            "INSERT INTO weather_data "
            "(location, temperature, feels_like, humidity, pressure, wind_speed, description, icon_code) "
            "VALUES ('" + name + "', " +
            std::to_string(temp) + ", " +
            std::to_string(feels) + ", " +
            std::to_string(hum) + ", " +
            std::to_string(press) + ", " +
            std::to_string(wind) + ", '" +
            desc + "', '" +
            icon + "');";

        char* errMsg = nullptr;

        if (sqlite3_exec(db, query.c_str(), nullptr, nullptr, &errMsg) != SQLITE_OK) {
            std::cerr << "SQL Error: " << errMsg << std::endl;
            sqlite3_free(errMsg);
        } else {
            std::cout << "[" << name << "] " << temp << "°C - " << desc << std::endl;
        }

        sqlite3_close(db);
    }
    catch (const std::exception& e) {
        std::cerr << "JSON Error: " << e.what() << std::endl;
        return 1;
    }

    return 0;
}
