# 💼 Apt Finance Manager v1.1a - Complete Setup Guide

## 🚀 Quick Installation (4 Steps)

### Step 1: Upload Files
- Extract the package to your cPanel File Manager
- Upload all files to your `public_html` directory
- Ensure all folders and files are properly uploaded

### Step 2: Database Setup
Visit: `yourdomain.com/install.php`
- Enter your database credentials
- Follow the installation wizard
- Default login will be created: `admin` / `admin123`

### Step 3: Run Migration
Visit: `yourdomain.com/migrate.php`

This will create:
- ✅ All new tables (clients, bank_accounts, inventory, vat_records)
- ✅ New columns for existing tables
- ✅ Default bank accounts (Opay, Kuda, MoniePoint, etc.)
- ✅ Updated invoice status enums

### Step 4: Complete Setup
Visit: `yourdomain.com/setup/index.php`
- Configure company information
- Set currency and tax preferences
- Configure tithe rate (default 10%)

## 🎯 What's New in v1.1a

### ✨ **Enhanced Features**
- 🎨 **Professional Dark Theme** - Modern, sleek interface
- 👥 **Complete Client Management** - Track clients and receivables
- 🏦 **Banking Integration** - 8 predefined Nigerian banks
- 💰 **VAT Threshold Monitoring** - ₦25,000,000 automatic alerts
- 📱 **Mobile Responsive** - Hamburger menu for mobile devices
- 🧾 **Receipt Number Tracking** - Add receipt numbers to transactions
- 🔄 **Recurring Costs** - Mark and track recurring expenses
- 📊 **Quick Actions Dashboard** - Fast access to common tasks

### 🆕 **New Pages & Features**
- **About Page** - App information and developer details
- **Client Detail Pages** - Individual client management
- **Enhanced Reports** - Export to PDF, Excel, CSV
- **Backup & Restore** - Full database backup functionality
- **Recent Transactions** - Quick view on dashboard

## 🔧 Configuration Options

### Currency & Localization
- **Supported Currencies**: ₦ (Naira), $ (USD), € (Euro), £ (GBP)
- **Country Defaults**: Nigeria (7.5% VAT), Others configurable
- **Tax Management**: Enable/disable VAT with custom rates

### User Roles & Permissions
- **Admin**: Full system access, user management, settings
- **Accountant**: Create invoices, record transactions, manage tithes
- **Viewer**: Read-only access to dashboard and reports

### Banking Setup
Pre-configured Nigerian banks:
- Opay, Kuda Bank, MoniePoint
- GTBank (Personal & Corporate)
- Access Bank Corporate
- PalmPay, Cash transactions

## 📊 Key Metrics & Calculations

### Financial Calculations
- **Trade Receivables**: Invoice Amount - Total Payments Received
- **Tithe Calculation**: (Invoice Inflow - Invoice Outflow) × Tithe Rate
- **VAT Calculation**: Invoice Amount × VAT Rate (when threshold reached)
- **Profit/Loss**: Total Inflow - Total Outflow (per invoice and global)

### Automatic Features
- **Invoice ID Generation**: DDMMYY-INV-CLIENT-### format
- **VAT Alerts**: Automatic notification when ₦25M threshold reached
- **Tithe Generation**: Auto-calculated when invoice marked "Completed"
- **Receivables Tracking**: Real-time calculation of outstanding amounts

## 🛡️ Security & Best Practices

### After Installation
1. **Change Default Password**: Login and immediately change admin password
2. **Create Users**: Add accountant and viewer users as needed
3. **Configure Backup**: Set up regular database backups
4. **Test Features**: Verify all functionality works correctly

### Security Features
- Password hashing with PHP's `password_hash()`
- SQL injection prevention with prepared statements
- Input sanitization and validation
- Role-based access control
- Session timeout management

## 🔧 Troubleshooting

### Common Issues

**"Table doesn't exist" errors:**
```bash
1. Run migrate.php to create missing tables
2. Check database user has CREATE/ALTER permissions
3. Verify database connection in config/config.php
```

**White screen or 500 errors:**
```bash
1. Check PHP error logs in cPanel
2. Verify file permissions (644 files, 755 folders)
3. Ensure PHP 7.4+ is enabled
```

**Styling issues:**
```bash
1. Hard refresh browser (Ctrl+F5)
2. Check if assets/css/style.css uploaded correctly
3. Verify Poppins font is loading from Google Fonts
```

**Navigation errors:**
```bash
1. Clear browser cache
2. Check JavaScript console for errors
3. Ensure all page files are uploaded correctly
```

## 📱 Mobile Usage

### Responsive Features
- **Hamburger Menu**: Navigation collapses on mobile devices
- **Touch-friendly**: Optimized button sizes and spacing
- **Readable Text**: Proper font scaling for mobile screens
- **Fast Loading**: Optimized assets for mobile connections

## 📈 Getting Started Guide

### First Steps After Installation
1. **Login**: Use admin/admin123, then change password
2. **Company Setup**: Complete company information
3. **Add First Client**: Create your first client record
4. **Create Invoice**: Generate your first invoice
5. **Record Transaction**: Add your first payment or expense
6. **Explore Reports**: Check out the financial reports

### Best Practices
- **Regular Backups**: Use the backup feature weekly
- **Client Updates**: Keep client information current
- **Transaction Recording**: Record transactions promptly
- **Invoice Management**: Update payment status regularly
- **Report Reviews**: Check monthly financial reports

## 🎉 Success Indicators

After successful setup, you should see:
- ✅ Dark theme interface loading correctly
- ✅ Dashboard with financial metrics
- ✅ All navigation links working
- ✅ Client management functional
- ✅ Invoice creation working
- ✅ Transaction recording operational
- ✅ Reports generating correctly
- ✅ Mobile responsive design

## 📞 Support & Resources

### Getting Help
- **Documentation**: Check README.md for detailed information
- **Issues**: Report bugs on GitHub repository
- **Email Support**: dapo.alla@gmail.com
- **Version**: Current version is 1.1a

### Useful Links
- **GitHub Repository**: [github.com/dapoalla/apt-finance-manager](https://github.com/dapoalla/apt-finance-manager)
- **License**: MIT License (see LICENSE file)
- **Contributing**: See CONTRIBUTING.md for guidelines

## 🎯 Next Steps

### Optional Cleanup
After successful setup, you can delete:
- `install.php` (security recommendation)
- `migrate.php` (after running once)
- `debug.php`, `test.php` (if present)
- `create_package.php` (development file)

### Customization
- Update company logo in header
- Customize colors in CSS file
- Add additional bank accounts if needed
- Configure email settings (future feature)

---

**🎉 Congratulations! Your Apt Finance Manager is ready to use!**

**Built with ❤️ by Dapo Alla for small businesses worldwide**