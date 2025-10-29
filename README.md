# 💼 FinEase v1.2

A comprehensive, modern business financial management system designed for small and medium businesses. Built with PHP and featuring a sleek dark theme interface, comprehensive client management, and advanced financial tracking capabilities.

![FinEase](https://img.shields.io/badge/Version-1.1c-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)

## ✨ Key Features

### 🎯 **Core Financial Management**
- **Real-time Dashboard** with live financial metrics and VAT status
- **Job Order Management** with line items and auto-generated IDs
- **Invoice Generation** from completed job orders
- **Transaction Tracking** with receipt uploads and seller details
- **Client Management** with receivables tracking
- **Automatic Tithe Calculations** (10% of profit, configurable)
- **VAT/Tax Management** with ₦25,000,000 threshold monitoring
- **Trade Receivables** tracking and aging analysis

### 🏦 **Sources & Payments**
- **Customizable Payment Sources**: Add/remove banks, cash, mobile money, etc.
- **Receipt File Uploads** with secure download links
- **Seller/Vendor Details** tracking for better record keeping
- **Payment Status Management**: Unpaid, Partly Paid, Fully Paid
- **Source-wise Transaction Filtering** and reporting

### 📊 **Advanced Reporting**
- **Monthly Financial Reports** with export capabilities
- **Invoice-specific P&L Analysis**
- **Category-based Expense Reports**
- **Tithe Management Reports**
- **Export Options**: PDF, Excel, CSV

### 🎨 **Modern Interface**
- **Professional Dark Theme** with modern gradients
- **Mobile-responsive Design** with hamburger navigation
- **Poppins Font** throughout for consistency
- **Smooth Animations** and hover effects
- **Quick Action Tiles** for common tasks

### 🔧 **System Features**
- **Role-based Access Control**: Admin, Accountant, Viewer
- **Company Branding**: Logo upload for invoices and reports
- **Backup & Restore** functionality
- **Recurring Cost Management**
- **Inventory Tracking** with depreciation
- **Multi-currency Support**: ₦, $, €, £

### 🆕 **v1.1c New Features**
- **Line Items Support**: Itemized billing with quantity and pricing
- **Invoice Generation**: Professional invoices from job orders
- **Receipt Management**: Upload and download transaction receipts
- **Enhanced Permissions**: Role-based delete restrictions
- **Sources Management**: Customizable payment sources in settings
- **Company Logo**: Upload logo for professional branding
- **Seller Tracking**: Vendor details for better record keeping
- **Edit Transactions**: Full transaction editing capabilities
- **Responsive Design**: Improved mobile experience

## 🚀 Quick Installation

### Method 1: One-Click Setup
1. **Download** the latest release
2. **Upload** to your cPanel public_html directory
3. **Visit** `yourdomain.com/install.php`
4. **Follow** the setup wizard
5. **Run** `yourdomain.com/migrate.php` to create tables

### Method 2: Manual Setup
```bash
# 1. Create MySQL database in cPanel
# 2. Upload files to public_html
# 3. Configure database connection
# 4. Run installation
```

## 📋 System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache/Nginx
- **Hosting**: cPanel compatible
- **Storage**: 50MB minimum

## 🔐 Default Credentials

After installation:
- **Username**: `admin`
- **Password**: `admin123`
- **⚠️ Change immediately after first login**

## 📖 Usage Guide

### Creating Your First Invoice
1. Navigate to **Invoices** → **Create Invoice**
2. Select existing client or add new one
3. Enter service description and amount
4. VAT is automatically calculated if threshold reached
5. Track payments and expenses against the invoice

### Recording Transactions
1. Go to **Transactions** → **Add Transaction**
2. Select transaction type (Inflow/Outflow)
3. Choose bank account (required)
4. Add receipt number if available
5. Mark as recurring if applicable

### Managing Clients
1. Visit **Clients** page
2. Add client contact information
3. View client invoice history
4. Track outstanding receivables
5. Monitor payment patterns

## 🎨 Customization

### Branding
- Update company information in **Settings**
- Modify colors in `assets/css/style.css`
- Replace logo in header template

### Currency & Localization
- Support for multiple currencies
- Country-specific tax defaults
- Configurable tithe rates

## 📁 Project Structure

```
apt-finance-manager/
├── 📄 index.php              # Dashboard
├── 🔧 install.php           # Installation wizard
├── 🔄 migrate.php           # Database migration
├── 📁 config/               # Configuration files
├── 📁 includes/             # Core functions & templates
├── 📁 pages/                # Application pages
├── 📁 auth/                 # Authentication
├── 📁 assets/               # CSS, JS, images
└── 📁 setup/                # Setup wizard
```

## 🛡️ Security Features

- **Password Hashing** with PHP's `password_hash()`
- **SQL Injection Prevention** with prepared statements
- **Input Sanitization** and validation
- **Role-based Access Control**
- **Session Management** with timeout
- **CSRF Protection**

## 🔄 Backup & Recovery

### Creating Backups
1. Go to **Settings** → **Backup & Restore**
2. Click **Create Backup**
3. Download the generated SQL file

### Restoring Data
1. Select backup file
2. Click **Restore Backup**
3. Confirm the operation

## 📊 Reporting & Analytics

### Available Reports
- **Monthly Financial Summary**
- **Invoice Performance Analysis**
- **Client Receivables Report**
- **Tax/VAT Compliance Report**
- **Tithe Management Report**

### Export Options
- **PDF**: Professional formatted reports
- **Excel**: Spreadsheet analysis
- **CSV**: Data import/export

## 👥 User Management

### Role Permissions
| Feature | Admin | Accountant | Viewer |
|---------|-------|------------|--------|
| Dashboard | ✅ | ✅ | ✅ |
| Create Invoices | ✅ | ✅ | ❌ |
| Record Transactions | ✅ | ✅ | ❌ |
| Manage Clients | ✅ | ✅ | ❌ |
| View Reports | ✅ | ✅ | ✅ |
| System Settings | ✅ | ❌ | ❌ |
| User Management | ✅ | ❌ | ❌ |

## 🐛 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check database credentials in config/config.php
# Ensure database exists in cPanel
# Verify user permissions
```

**White Screen/500 Error**
```bash
# Check PHP error logs
# Verify file permissions (644 for files, 755 for directories)
# Ensure all required PHP extensions are installed
```

**Migration Errors**
```bash
# Run migrate.php to create missing tables
# Check database user has CREATE/ALTER permissions
# Verify MySQL version compatibility
```

## 🔮 Roadmap

### Version 1.2 (Planned)
- [ ] Email notifications for invoices
- [ ] PDF invoice generation
- [ ] Advanced inventory management
- [ ] Multi-company support
- [ ] API endpoints

### Version 1.3 (Future)
- [ ] Mobile app companion
- [ ] Advanced analytics dashboard
- [ ] Integration with payment gateways
- [ ] Automated recurring invoices

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Setup
```bash
git clone https://github.com/dapoalla/finease.git
cd finease
# Set up local development environment
```

## 📞 Support & Contact

- **Developer**: Dapo Alla
- **Email**: [dapo.alla+github@gmail.com](mailto:dapo.alla+github@gmail.com)
- **GitHub**: [github.com/dapoalla/finease](https://github.com/dapoalla/finease)

### Support the Project
If you find Apt Finance Manager useful, consider supporting development:

**USDT (BEP20) Donations**
```
Wallet: 0xa4C9677FDBaC8F1eAB0234585d98ED0059b9d5aD
Network: BNB Smart Chain
⚠️ Only send USDT (BEP20) to this address
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built with modern web technologies
- Inspired by the needs of small businesses
- Designed for simplicity and efficiency

---

**⭐ Star this repository if you find it useful!**

**Built with ❤️ for entrepreneurs and small businesses worldwide**
