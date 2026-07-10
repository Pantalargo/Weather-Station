# Weather Station WebApp

This is a stack application that collects weather data automatically stores it and shows it on the web. The project uses a C++ backend worker that runs fast a SQLite3 database that's small a PHP frontend dashboard that is served by NGINX and Cloudflare Tunnels to make it secure and accessible from outside.

##. Workflow

The system is like a pipeline that is automated and has four parts:

1. Getting Data (Backend): A C++ program runs every 15 minutes because of a system cronjob. It sends a request to the OpenWeatherMap API using libcurl. When it gets the data back it uses the nlohmann/json library to understand the weather information.

2. Saving Data (Database): The program takes the data it got like temperature and humidity. Puts it into a SQLite3 database on the local machine.

3. Showing Data (Frontend): The NGINX web server gets requests from users. Sends them to the PHP 8.3-FPM processor. The PHP scripts connect to the SQLite3 database get the records make the timestamps look nice apply filters and show a user interface that works well and looks good with Tailwind CSS.

4. Making it Secure (Networking): Cloudflare Tunnels make the local NGINX port accessible from the internet over HTTPS. This way we do not need to set up SSL or ports on the local router manually.

## System Requirements

This project needs to run on a Linux system like Ubuntu Server or Debian. We need to install the following:

```bash

sudo apt install g++ libcurl4-openssl-dev libsqlite3-dev sqlite3 nginx php8.3-fpm

```

Note: The C++ code needs the `json.hpp` header from the nlohmann/json library to be, in the backend directory when it is compiled. The Weather Station WebApp uses this library to understand the weather data it collects.


