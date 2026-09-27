#include <iostream>
#include <string>
#include <fstream>
#include <curl/curl.h>
#include <sqlite3.h>
#include <nlohmann/json.hpp>

using json = nlohmann::json;

struct WeatherCode {
    std::string description;
    std::string icon;
};

WeatherCode parseWMOCode(int code, bool isDay) {
    const std::string suffix = isDay ? "d" : "n";

    switch (code) {
        case 0: return {"Cielo sereno", "01" + suffix};
        case 1: return {"Prevalentemente sereno", "02" + suffix};
        case 2: return {"Parzialmente nuvoloso", "03" + suffix};
        case 3: return {"Nuvoloso", "04" + suffix};
        case 45: case 48: return {"Nebbia", "50" + suffix};
        case 51: return {"Pioviggine debole", "09" + suffix};
        case 53: return {"Pioviggine moderata", "09" + suffix};
        case 55: return {"Pioviggine forte", "09" + suffix};
        case 56: case 57: return {"Pioviggine congelantesi", "09" + suffix};
        case 61: return {"Pioggia debole", "10" + suffix};
        case 63: return {"Pioggia moderata", "10" + suffix};
        case 65: return {"Pioggia forte", "10" + suffix};
        case 66: case 67: return {"Pioggia congelantesi", "10" + suffix};
        case 71: return {"Neve debole", "13" + suffix};
        case 73: return {"Neve moderata", "13" + suffix};
        case 75: return {"Neve forte", "13" + suffix};
        case 77: return {"Granuli di neve", "13" + suffix};
        case 80: return {"Acquazzone debole", "09" + suffix};
        case 81: return {"Acquazzone moderato", "09" + suffix};
        case 82: return {"Acquazzone forte", "09" + suffix};
        case 85: return {"Rovescio di neve debole", "13" + suffix};
        case 86: return {"Rovescio di neve forte", "13" + suffix};
        case 95: return {"Temporale", "11" + suffix};
        case 96: case 99: return {"Temporale con grandine", "11" + suffix};
        default: return {"Sconosciuto", "03" + suffix};
    }
}

size_t WriteCallback(void* contents, size_t size, size_t nmemb, void* userp) {
    const size_t totalSize = size * nmemb;
    if (userp == nullptr) return 0;
    std::string* buffer = static_cast<std::string*>(userp);
    buffer->append(static_cast<char*>(contents), totalSize);
    return totalSize;
}

int main() {
    const std::string lat = "Latitudine";
    const std::string lon = "Longitudine";
    const std::string cityName = "Nome della Città";
    const std::string dbPath = "/opt/weather/weather.db";
    const std::string jsonOutPath = "/opt/weather/forecast.json";

    const std::string url =
        "https://api.open-meteo.com/v1/forecast"
        "?latitude=" + lat +
        "&longitude=" + lon +
        "&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,surface_pressure,wind_speed_10m,is_day"
        "&hourly=temperature_2m,weather_code,precipitation_probability"
        "&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max"
        "&forecast_days=7"
        "&timezone=Europe/Rome";

    CURLcode globalInit = curl_global_init(CURL_GLOBAL_DEFAULT);
    if (globalInit != CURLE_OK) {
        std::cerr << "Errore curl_global_init: " << curl_easy_strerror(globalInit) << std::endl;
        return 1;
    }

    CURL* curl = curl_easy_init();
    if (curl == nullptr) {
        std::cerr << "Errore: impossibile inizializzare CURL." << std::endl;
        curl_global_cleanup();
        return 1;
    }

    std::string response;
    curl_easy_setopt(curl, CURLOPT_URL, url.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, WriteCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 10L);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 5L);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
    curl_easy_setopt(curl, CURLOPT_USERAGENT, "WeatherApp/1.0");

    CURLcode res = curl_easy_perform(curl);

    if (res != CURLE_OK) {
        std::cerr << "Errore CURL: " << curl_easy_strerror(res) << std::endl;
        curl_easy_cleanup(curl);
        curl_global_cleanup();
        return 1;
    }

    long httpCode = 0;
    curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &httpCode);
    if (httpCode != 200) {
        std::cerr << "Errore HTTP: " << httpCode << std::endl;
        curl_easy_cleanup(curl);
        curl_global_cleanup();
        return 1;
    }

    curl_easy_cleanup(curl);
    curl_global_cleanup();

    try {
        json j = json::parse(response);

        if (!j.contains("current")) {
            std::cerr << "Errore: nodo 'current' non trovato." << std::endl;
            return 1;
        }

        std::ofstream outFile(jsonOutPath);
        if (outFile.is_open()) {
            outFile << response;
            outFile.close();
        } else {
            std::cerr << "Errore: Impossibile scrivere su " << jsonOutPath << std::endl;
        }

        const json& current = j["current"];
        double temp = current.at("temperature_2m").get<double>();
        double feels = current.at("apparent_temperature").get<double>();
        int hum = current.at("relative_humidity_2m").get<int>();
        double press = current.at("surface_pressure").get<double>();
        double wind = current.at("wind_speed_10m").get<double>() / 3.6;
        int wmoCode = current.at("weather_code").get<int>();
        bool isDay = current.at("is_day").get<int>() == 1;

        WeatherCode weather = parseWMOCode(wmoCode, isDay);
        const std::string& desc = weather.description;
        const std::string& icon = weather.icon;

        sqlite3* db = nullptr;
        if (sqlite3_open(dbPath.c_str(), &db) != SQLITE_OK) {
            std::cerr << "Errore DB: " << (db != nullptr ? sqlite3_errmsg(db) : "non disponibile") << std::endl;
            if (db != nullptr) sqlite3_close(db);
            return 1;
        }

        const char* sql =
            "INSERT INTO dati_meteo "
            "(localita, temperatura, percepita, umidita, pressione, velocita_vento, descrizione, icona_codice) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?);";

        sqlite3_stmt* stmt = nullptr;
        if (sqlite3_prepare_v2(db, sql, -1, &stmt, nullptr) != SQLITE_OK) {
            std::cerr << "Errore prepare SQL: " << sqlite3_errmsg(db) << std::endl;
            sqlite3_close(db);
            return 1;
        }

        sqlite3_bind_text(stmt, 1, cityName.c_str(), -1, SQLITE_TRANSIENT);
        sqlite3_bind_double(stmt, 2, temp);
        sqlite3_bind_double(stmt, 3, feels);
        sqlite3_bind_int(stmt, 4, hum);
        sqlite3_bind_double(stmt, 5, press);
        sqlite3_bind_double(stmt, 6, wind);
        sqlite3_bind_text(stmt, 7, desc.c_str(), -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 8, icon.c_str(), -1, SQLITE_TRANSIENT);

        if (sqlite3_step(stmt) != SQLITE_DONE) {
            std::cerr << "Errore INSERT: " << sqlite3_errmsg(db) << std::endl;
            sqlite3_finalize(stmt);
            sqlite3_close(db);
            return 1;
        }

        sqlite3_finalize(stmt);
        sqlite3_close(db);

        std::cout << "[" << cityName << "] Dati attuali inseriti nel DB e forecast.json aggiornato." << std::endl;
    }
    catch (const std::exception& e) {
        std::cerr << "Errore: " << e.what() << std::endl;
        return 1;
    }

    return 0;
}
