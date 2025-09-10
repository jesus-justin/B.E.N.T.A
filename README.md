3""# B.E.N.T.A - Business Expense and Net Transaction Analyzer

A comprehensive web-based financial management system for tracking business expenses, income, and generating insightful reports.

## Features

### Core Functionality
- **User Authentication**: Secure login and registration system
- **Transaction Management**: Add, edit, delete, and categorize transactions
- **Category Management**: Organize transactions with custom categories
- **Financial Reports**: Generate summary, monthly, category, and trend reports
- **Dashboard**: Real-time financial overview with key metrics
- **Settings Management**: Customize business information and preferences

### Security Features
- Password hashing with bcrypt
- Session-based authentication
- Input validation and sanitization
- SQL injection prevention
- XSS protection

### User Interface
- Modern, responsive design
- Smooth animations and transitions
- Mobile-friendly interface
- Intuitive navigation

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: Custom CSS with responsive design
- **Icons**: Font Awesome

## Installation

### Prerequisites
- XAMPP (recommended) or similar PHP development environment
- MySQL 5.7+ or MariaDB 10.0+
- PHP 7.4 or higher
- Web browser (Chrome, Firefox, Safari, Edge)

### Quick Setup Steps

1. **Download and Extract**
   ```
   Download the B.E.N.T.A project files
   Extract to: C:\xampp\htdocs\B.E.N.T.A
   ```

2. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services
   - Ensure both show green status

3. **Database Setup**
   - Open browser and go to: `http://localhost/phpmyadmin`
   - Create new database: `benta_db` (important: use this exact name)
   - Select the `benta_db` database
   - Click "Import" tab
   - Choose `schema.sql` file from your project folder
   - Click "Go" to import the database structure

4. **Verify Configuration**
   - Check `config/db.php` - should use default XAMPP settings:
     - Host: localhost
     - Database: benta_db
     - Username: root
     - Password: (empty)

5. **Access the Application**
   - Open browser: `http://localhost/B.E.N.T.A`
   - Click "Register" to create your first account
   - Login with your credentials

### Troubleshooting

**Page Stuck Loading:**
- Ensure MySQL service is running in XAMPP
- Check if database `benta_db` exists in phpMyAdmin
- Verify all tables were created from schema.sql

**Database Connection Error:**
- Confirm MySQL is running (green in XAMPP)
- Check database name matches in config/db.php
- Try accessing phpMyAdmin directly

**Permission Errors:**
- Ensure XAMPP is run as Administrator
- Check file permissions in htdocs folder

**PHP Errors:**
- Check XAMPP Apache error logs
- Ensure PHP extensions are enabled (PDO, MySQLi)

## Database Schema

The system uses the following main tables:
- `users` - User accounts
- `transactions` - Financial transactions
- `categories` - Transaction categories
- `settings` - User preferences

## API Endpoints

### Authentication
- `POST /api/login.php` - User login
- `POST /api/register.php` - User registration
- `POST /api/logout.php` - User logout
- `GET /api/auth_check.php` - Check authentication status

### Transactions
- `GET /api/transactions.php` - Get transactions (with optional filters)
- `POST /api/transactions.php` - Create new transaction
- `PUT /api/transactions.php` - Update existing transaction
- `DELETE /api/transactions.php` - Delete transaction

### Categories
- `GET /api/categories.php` - Get all categories
- `POST /api/categories.php` - Create new category
- `PUT /api/categories.php` - Update category
- `DELETE /api/categories.php` - Delete category

### Reports
- `GET /api/reports.php?type=summary` - Financial summary
- `GET /api/reports.php?type=monthly&year=2024&month=1` - Monthly report
- `GET /api/reports.php?type=category` - Category breakdown
- `GET /api/reports.php?type=trend&year=2024` - Yearly trends

### Settings
- `GET /api/settings.php` - Get user settings
- `PUT /api/settings.php` - Update user settings

## File Structure

```
B.E.N.T.A/
├── api/                    # API endpoints
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── auth_check.php
│   ├── transactions.php
│   ├── categories.php
│   ├── reports.php
│   └── settings.php
├── assets/                 # Static assets
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── main.js
│       └── animations.js
├── config/                 # Configuration files
│   └── db.php
├── includes/               # PHP classes and functions
│   ├── auth.php
│   └── functions.php
├── schema.sql              # Database schema
├── index.php               # Main dashboard
├── login.php               # Login page
├── register.php            # Registration page
├── transactions.php        # Transaction management
├── reports.php             # Reports page
├── settings.php            # Settings page
├── TODO.md                 # Development progress
└── README.md               # This file
```

## Usage Guide

### Getting Started
1. Register a new account or login with existing credentials
2. The system will automatically create default categories for you
3. Start adding your financial transactions

### Adding Transactions
1. Navigate to the Transactions page
2. Click "Add Transaction"
3. Fill in the transaction details:
   - Amount (positive number)
   - Description (optional)
   - Category (choose from existing or create new)
   - Date (YYYY-MM-DD format)
   - Type (Income or Expense)

### Managing Categories
1. Go to Settings > Categories
2. Add new categories for income or expenses
3. Edit or delete existing categories (categories with transactions cannot be deleted)

### Generating Reports
1. Navigate to the Reports page
2. Select report type (Summary, Monthly, Categories, Trends)
3. Set date filters if applicable
4. Click "Generate Report"

### Customizing Settings
1. Go to Settings
2. Update business information
3. Change currency preferences
4. Set fiscal year start date

## Security Considerations

- All passwords are hashed using bcrypt
- User input is validated and sanitized
- Prepared statements prevent SQL injection
- Session management for authentication
- CSRF protection through proper session handling

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+

## Contributing

This is a complete implementation of the B.E.N.T.A system. For modifications or enhancements:

1. Test all changes thoroughly
2. Maintain security best practices
3. Update documentation as needed
4. Ensure responsive design works on all devices

## License

This project is for educational and personal use.

## Support

For issues or questions about the system:
1. Check the browser console for JavaScript errors
2. Verify database connection settings
3. Ensure all PHP files have proper permissions
4. Check XAMPP error logs for server-side issues

---

**B.E.N.T.A** - Making financial management simple and effective.
