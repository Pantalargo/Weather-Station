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

## Component Deployment and Installation

### 1. Database Initialization

The backend and frontend components expect the database to reside in a secure global directory. Create the target directory and initialize the database schema using the provided SQL file:

```bash
sudo mkdir -p /opt/weather
sudo sqlite3 /opt/weather/weather.db 
```

To ensure proper data isolation and security, configure strict file permissions. The web server user (www-data) requires read access to the database and read/execute access to the enclosing folder:

```bash
sudo chown -R your_username:www-data /opt/weather
sudo chmod 750 /opt/weather
sudo chmod 640 /opt/weather/weather.db
```

### 2. Backend Compilation and Automation

Open `backend/weather.cpp` and replace the placeholder API key and city variables with your valid OpenWeatherMap credentials. Compile the source file into a binary executable:

```bash
g++ -o /opt/weather/worker backend/weather.cpp -lcurl -lsqlite3
```

To automate the data collection process, register the binary within the system crontab. Open the crontab configuration editor:

```bash
crontab -e
```

Append the following line to execute the binary precisely every 15 minutes, redirecting standard output and errors to prevent system mail clogging:

```bash
*/15 * * * * /opt/weather/worker >/dev/null 2>&1
```

### 3. Web Server and Frontend Configuration

Deploy the PHP application files to the designated web server root directory:

```bash
sudo mkdir -p /var/www/weather
sudo cp frontend/*.php /var/www/weather/
sudo chown -R www-data:www-data /var/www/weather
```

Configure a dedicated NGINX server block. Create a configuration file within `/etc/nginx/sites-available/weather` and reference the following structure:

```nginx
server {
    listen 8080;
    server_name localhost;

    root /var/www/weather;
    index index.php index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }
}
```

Enable the site by creating a symbolic link to the enabled sites directory and restart the NGINX service:

```bash
sudo ln -s /etc/nginx/sites-available/weather /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

### 4. Cloudflare Tunnel Ingress Setup

To securely Route external traffic to the local NGINX port (8080), append the web application ingress rule into your local `/etc/cloudflared/config.yml` configuration:

```yaml
tunnel: your-cloudflare-tunnel-id
credentials-file: /etc/cloudflared/your-cloudflare-tunnel-id.json

ingress:
  - hostname: weather.yourdomain.com
    service: http://localhost:8080
    originRequest:
      noTLSVerify: true
  - service: http_status:404
```

Restart the cloudflared service to apply the configuration.

## Database Schema Reference

The `weather_data` table utilizes the following strict relational schema:

* `id`: INTEGER, Primary Key, Auto-incremented unique identifier.
* `timestamp`: DATETIME, Defaults to current system UTC timestamp on row insertion.
* `location`: TEXT, The name of the city returned by the API.
* `temperature`: REAL, Current recorded temperature in Celsius.
* `feels_like`: REAL, Human-perceived equivalent temperature in Celsius.
* `humidity`: INTEGER, Relative humidity percentage.
* `pressure`: INTEGER, Atmospheric pressure at sea level measured in hPa.
* `wind_speed`: REAL, Wind speed measured in meters per second.
* `description`: TEXT, Automated weather condition description string.
* `icon_code`: TEXT, The unique identifier string corresponding to OpenWeatherMap assets.
