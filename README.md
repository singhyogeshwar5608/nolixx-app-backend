# Nolixx App - PHP Backend Setup Guide

## 📋 Prerequisites
- PHP 8.0 or higher
- MySQL/MariaDB
- Composer
- Apache/Nginx with mod_rewrite enabled

---

## 🚀 Installation Steps

### 1. Install Composer Dependencies
```bash
cd backend
composer install
```

### 2. Configure Environment Variables
Edit `backend/.env` file:
```env
DB_HOST=localhost
DB_NAME=app_database
DB_USER=root
DB_PASS=

EMAIL_USER=yourgmail@gmail.com
EMAIL_PASS=your_16_digit_app_password
```

**Important:** For Gmail SMTP:
1. Enable 2-Factor Authentication on your Google account
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use the 16-digit app password (not your regular password)

### 3. Setup Database
Run the SQL file:
```bash
mysql -u root -p < database.sql
```

Or manually execute in phpMyAdmin/MySQL Workbench.

### 4. Configure Web Server

#### Apache (.htaccess already included)
Point document root to `backend/` folder.

#### Nginx
```nginx
server {
    listen 80;
    server_name localhost;
    root /path/to/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Test API Endpoints

**Send OTP:**
```bash
curl -X POST http://localhost/api/send-otp \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'
```

**Verify OTP:**
```bash
curl -X POST http://localhost/api/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","otp":"123456"}'
```

---

## 📁 Project Structure
```
backend/
├── vendor/              # Composer dependencies
├── .env                 # Environment configuration
├── .htaccess           # Apache rewrite rules
├── composer.json       # PHP dependencies
├── database.sql        # Database schema
│
├── config/
│   ├── database.php    # PDO connection
│   └── mail.php        # SMTP settings
│
├── core/
│   ├── Router.php      # Request routing
│   └── cors.php        # CORS handling
│
├── helpers/
│   └── response.php    # JSON response helper
│
├── controllers/
│   └── AuthController.php  # OTP logic
│
├── routes/
│   └── api.php         # Route definitions
│
└── public/
    └── index.php       # Application entry point
```

---

## 🔐 Security Notes
- Never commit `.env` file to version control
- Use prepared statements (already implemented)
- Keep Composer dependencies updated
- Use HTTPS in production
- Implement rate limiting for OTP endpoints
- Add IP-based throttling to prevent abuse

---

## 🐛 Troubleshooting

**Database connection failed:**
- Check MySQL service is running
- Verify credentials in `.env`
- Ensure database exists

**Email not sending:**
- Verify Gmail App Password is correct
- Check firewall allows port 587
- Enable "Less secure app access" if needed (not recommended)

**404 errors:**
- Ensure `.htaccess` is in backend root
- Enable `mod_rewrite` in Apache
- Check document root points to `backend/` folder

---

## 📞 API Documentation

### POST /api/send-otp
**Request:**
```json
{
  "email": "user@example.com"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "OTP sent successfully to your email",
  "data": {}
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Invalid email format"
}
```

---

### POST /api/verify-otp
**Request:**
```json
{
  "email": "user@example.com",
  "otp": "123456"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "OTP verified successfully",
  "data": {
    "email": "user@example.com"
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Invalid OTP"
}
```

---

## 🎯 Next Steps
1. Add rate limiting middleware
2. Implement user registration after OTP verification
3. Add JWT authentication
4. Create user management endpoints
5. Add logging system
6. Implement email templates
7. Add unit tests

---

## 📝 License
Proprietary - Nolixx App
