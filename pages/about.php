<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'About';
require_once '../includes/header.php';
?>

<div class="about-container">
    <div class="about-header">
        <h1>💼 FinEase</h1>
        <p class="version">Version 1.1b</p>
    </div>
    
    <div class="about-grid">
        <div class="about-card">
            <h2>🚀 About This Application</h2>
            <p>FinEase is a comprehensive business financial management system designed to help small and medium businesses track their finances, manage clients, and maintain accurate records with ease.</p>
            
            <h3>✨ Key Features</h3>
            <ul>
                <li>📊 Real-time financial dashboard</li>
                <li>👥 Complete client management</li>
                <li>📄 Job order management and invoice generation</li>
                <li>💰 Transaction recording with banking integration</li>
                <li>🙏 Automatic tithe calculations</li>
                <li>💳 Trade receivables tracking</li>
                <li>📈 Comprehensive financial reports</li>
                <li>🏦 Multi-bank account support</li>
                <li>💱 VAT threshold monitoring</li>
                <li>🔄 Recurring cost management</li>
                <li>📱 Mobile-responsive design</li>
                <li>🌙 Modern dark theme interface</li>
            </ul>
        </div>
        
        <div class="about-card">
            <h2>🎯 Benefits</h2>
            <ul>
                <li><strong>Save Time:</strong> Automate financial calculations and reporting</li>
                <li><strong>Stay Organized:</strong> Keep all financial data in one place</li>
                <li><strong>Make Informed Decisions:</strong> Access real-time financial insights</li>
                <li><strong>Ensure Compliance:</strong> Track VAT and tax obligations</li>
                <li><strong>Improve Cash Flow:</strong> Monitor receivables and payments</li>
                <li><strong>Spiritual Stewardship:</strong> Automatic tithe calculations</li>
                <li><strong>Professional Job Orders:</strong> Manage work orders and generate invoices</li>
                <li><strong>Multi-Device Access:</strong> Works on desktop and mobile</li>
            </ul>
        </div>
    </div>    

    <div class="developer-section">
        <div class="developer-card">
            <h2>👨‍💻 Developer Information</h2>
            <div class="developer-info">
                <p><strong>Designed & Developed by:</strong> Dapo Alla</p>
                <p><strong>Email:</strong> <a href="mailto:dapo.alla@gmail.com">dapo.alla@gmail.com</a></p>
                <p><strong>Version:</strong> 1.1b</p>
                <p><strong>Release Date:</strong> December 2024</p>
            </div>
            
            <div class="links-section">
                <h3>🔗 Links</h3>
                <div class="link-buttons">
                    <a href="https://github.com/dapoalla/apt-finance-manager" target="_blank" class="btn btn-primary">
                        📂 GitHub Repository
                    </a>
                    <button onclick="showDonationModal()" class="btn btn-success">
                        ☕ Buy Me Coffee
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="tech-specs">
        <div class="tech-card">
            <h2>⚙️ Technical Specifications</h2>
            <div class="tech-grid">
                <div class="tech-item">
                    <strong>Backend:</strong> PHP 7.4+
                </div>
                <div class="tech-item">
                    <strong>Database:</strong> MySQL 5.7+
                </div>
                <div class="tech-item">
                    <strong>Frontend:</strong> HTML5, CSS3, JavaScript
                </div>
                <div class="tech-item">
                    <strong>Framework:</strong> Vanilla PHP (No dependencies)
                </div>
                <div class="tech-item">
                    <strong>Hosting:</strong> cPanel Compatible
                </div>
                <div class="tech-item">
                    <strong>License:</strong> MIT License
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Donation Modal -->
<div id="donationModal" class="modal" style="display: none;">
    <div class="modal-content donation-modal">
        <div class="modal-header">
            <h3>☕ Support Development</h3>
            <button onclick="hideDonationModal()" class="close-btn">&times;</button>
        </div>
        
        <div class="donation-content">
            <p>If you find FinEase useful, consider buying me a cup of coffee! Your support helps keep this project alive and growing.</p>
            
            <div class="crypto-info">
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=0xa4C9677FDBaC8F1eAB0234585d98ED0059b9d5aD" 
                         alt="USDT Wallet QR Code" 
                         class="qr-code"
                         onerror="handleQRError(this)"
                         onload="handleQRLoad(this)">
                    <p class="qr-label">Scan to Copy Address</p>
                </div>
                
                <div class="wallet-details">
                    <div class="detail-item">
                        <strong>Asset:</strong> Tether (USDT)
                    </div>
                    <div class="detail-item">
                        <strong>Network:</strong> BNB Smart Chain
                    </div>
                    <div class="detail-item">
                        <strong>Wallet Address:</strong>
                        <div class="wallet-address">
                            <input type="text" value="0xa4C9677FDBaC8F1eAB0234585d98ED0059b9d5aD" readonly id="walletAddress">
                            <button onclick="copyWalletAddress()" class="copy-btn">📋</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="warning-box">
                <strong>⚠️ Warning:</strong> Only send Tether USD (BEP20) assets to this address. Other assets will be lost forever.
            </div>
            
            <div class="thank-you">
                <p><strong>Thank you for buying me a cup of coffee! ☕</strong></p>
            </div>
        </div>
    </div>
</div>

<style>
.about-container {
    max-width: 1200px;
    margin: 0 auto;
}

.about-header {
    text-align: center;
    margin-bottom: 2rem;
}

.about-header h1 {
    font-size: 2.5rem;
    font-weight: 500;
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.version {
    color: var(--text-muted);
    font-size: 1.1rem;
}

.about-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.about-card,
.developer-card,
.tech-card {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.about-card h2,
.developer-card h2,
.tech-card h2 {
    color: var(--text-primary);
    margin-bottom: 1rem;
    font-weight: 500;
}

.about-card h3 {
    color: var(--text-primary);
    margin: 1.5rem 0 1rem 0;
    font-weight: 500;
}

.about-card ul,
.developer-card ul {
    color: var(--text-secondary);
    padding-left: 1.5rem;
}

.about-card li {
    margin-bottom: 0.5rem;
}

.developer-info p {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.developer-info a {
    color: var(--accent-primary);
    text-decoration: none;
}

.developer-info a:hover {
    text-decoration: underline;
}

.links-section {
    margin-top: 1.5rem;
}

.link-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.tech-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.tech-item {
    color: var(--text-secondary);
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 6px;
}

/* Donation Modal */
.donation-modal {
    max-width: 500px;
    width: 90%;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted);
    cursor: pointer;
}

.crypto-info {
    display: flex;
    gap: 1.5rem;
    margin: 1.5rem 0;
}

.qr-section {
    text-align: center;
}

.qr-code {
    width: 150px;
    height: 150px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background: white;
    padding: 8px;
    object-fit: contain;
    transition: all 0.3s ease;
}

.qr-code:hover {
    transform: scale(1.05);
    box-shadow: var(--shadow-lg);
}

.qr-code.error {
    background: var(--bg-secondary);
    border-color: var(--accent-danger);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-danger);
    font-size: 0.8rem;
    text-align: center;
}

/* Responsive QR code */
@media (max-width: 480px) {
    .qr-code {
        width: 120px;
        height: 120px;
    }
}

.qr-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
}

.wallet-details {
    flex: 1;
}

.detail-item {
    margin-bottom: 1rem;
    color: var(--text-secondary);
}

.detail-item strong {
    color: var(--text-primary);
}

.wallet-address {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.wallet-address input {
    flex: 1;
    padding: 0.5rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    color: var(--text-primary);
    font-size: 0.85rem;
}

.copy-btn {
    padding: 0.5rem;
    background: var(--accent-primary);
    border: none;
    border-radius: 4px;
    color: white;
    cursor: pointer;
}

.warning-box {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--accent-danger);
    padding: 1rem;
    border-radius: 8px;
    color: var(--accent-danger);
    margin: 1rem 0;
}

.thank-you {
    text-align: center;
    margin-top: 1rem;
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .about-grid {
        grid-template-columns: 1fr;
    }
    
    .crypto-info {
        flex-direction: column;
        align-items: center;
    }
    
    .link-buttons {
        flex-direction: column;
    }
}
</style>

<script>
function showDonationModal() {
    document.getElementById('donationModal').style.display = 'flex';
}

function hideDonationModal() {
    document.getElementById('donationModal').style.display = 'none';
}

function copyWalletAddress() {
    const walletInput = document.getElementById('walletAddress');
    walletInput.select();
    document.execCommand('copy');
    
    const copyBtn = document.querySelector('.copy-btn');
    const originalText = copyBtn.textContent;
    copyBtn.textContent = '✅';
    
    setTimeout(() => {
        copyBtn.textContent = originalText;
    }, 2000);
}

function handleQRError(img) {
    img.style.display = 'none';
    const qrSection = img.parentElement;
    const errorDiv = document.createElement('div');
    errorDiv.className = 'qr-code error';
    errorDiv.innerHTML = '❌<br>QR Code<br>Failed to Load';
    errorDiv.title = 'QR Code could not be loaded. Please copy the wallet address manually.';
    qrSection.insertBefore(errorDiv, img);
}

function handleQRLoad(img) {
    img.style.opacity = '0';
    img.style.transition = 'opacity 0.3s ease';
    setTimeout(() => {
        img.style.opacity = '1';
    }, 100);
}
</script>

<?php require_once '../includes/footer.php'; ?>