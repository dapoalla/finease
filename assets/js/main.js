// Main JavaScript functionality

// Chart drawing function
function drawChart(ctx, data) {
    const canvas = ctx.canvas;
    const width = canvas.width;
    const height = canvas.height;
    
    // Clear canvas
    ctx.clearRect(0, 0, width, height);
    
    // Chart settings
    const padding = 40;
    const chartWidth = width - (padding * 2);
    const chartHeight = height - (padding * 2);
    
    // Find max value for scaling
    let maxValue = 0;
    data.forEach(item => {
        maxValue = Math.max(maxValue, parseFloat(item.inflow) || 0, parseFloat(item.outflow) || 0);
    });
    
    if (maxValue === 0) maxValue = 1000; // Prevent division by zero
    
    // Draw grid lines
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    
    for (let i = 0; i <= 5; i++) {
        const y = padding + (chartHeight / 5) * i;
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
    }
    
    // Draw data lines
    const stepX = chartWidth / (data.length - 1);
    
    // Inflow line (green)
    ctx.strokeStyle = '#38a169';
    ctx.lineWidth = 3;
    ctx.beginPath();
    
    data.forEach((item, index) => {
        const x = padding + (stepX * index);
        const y = padding + chartHeight - (item.inflow / maxValue * chartHeight);
        
        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    });
    ctx.stroke();
    
    // Outflow line (red)
    ctx.strokeStyle = '#e53e3e';
    ctx.lineWidth = 3;
    ctx.beginPath();
    
    data.forEach((item, index) => {
        const x = padding + (stepX * index);
        const y = padding + chartHeight - (item.outflow / maxValue * chartHeight);
        
        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    });
    ctx.stroke();
    
    // Draw data points and labels
    ctx.fillStyle = '#2d3748';
    ctx.font = '12px Poppins';
    ctx.textAlign = 'center';
    
    data.forEach((item, index) => {
        const x = padding + (stepX * index);
        
        // Month labels
        ctx.fillText(item.month, x, height - 10);
        
        // Data points
        const inflowY = padding + chartHeight - (item.inflow / maxValue * chartHeight);
        const outflowY = padding + chartHeight - (item.outflow / maxValue * chartHeight);
        
        // Inflow point
        ctx.fillStyle = '#38a169';
        ctx.beginPath();
        ctx.arc(x, inflowY, 4, 0, 2 * Math.PI);
        ctx.fill();
        
        // Outflow point
        ctx.fillStyle = '#e53e3e';
        ctx.beginPath();
        ctx.arc(x, outflowY, 4, 0, 2 * Math.PI);
        ctx.fill();
        
        ctx.fillStyle = '#2d3748';
    });
    
    // Legend
    ctx.fillStyle = '#38a169';
    ctx.fillRect(width - 150, 20, 15, 15);
    ctx.fillStyle = '#2d3748';
    ctx.font = '14px Poppins';
    ctx.textAlign = 'left';
    ctx.fillText('Inflow', width - 130, 32);
    
    ctx.fillStyle = '#e53e3e';
    ctx.fillRect(width - 150, 45, 15, 15);
    ctx.fillStyle = '#2d3748';
    ctx.fillText('Outflow', width - 130, 57);
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true; // If no form found, allow submission
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#e53e3e';
            isValid = false;
        } else {
            input.style.borderColor = '#e2e8f0';
        }
    });
    
    return isValid;
}

// Currency formatting
function formatCurrency(amount, currency = '₦') {
    return currency + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Date formatting
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Show/hide loading spinner
function showLoading(element) {
    element.innerHTML = '<div class="spinner">Loading...</div>';
    element.disabled = true;
}

function hideLoading(element, originalText) {
    element.innerHTML = originalText;
    element.disabled = false;
}

// AJAX helper function
function makeRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error('Request failed'));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        if (data) {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    });
}

// Show alert messages
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.main-content');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Hamburger menu toggle
function toggleMenu() {
    const navMenu = document.getElementById('navMenu');
    navMenu.classList.toggle('active');
}

// Initialize tooltips and other UI enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('click', function() {
            const cardClass = this.className;
            const currentPath = window.location.pathname;
            const isInPages = currentPath.includes('/pages/');
            
            if (cardClass.includes('inflow') || cardClass.includes('outflow')) {
                window.location.href = isInPages ? 'transactions.php' : 'pages/transactions.php';
            } else if (cardClass.includes('invoices')) {
                window.location.href = isInPages ? 'invoices.php' : 'pages/invoices.php';
            } else if (cardClass.includes('tithes')) {
                window.location.href = isInPages ? 'tithes.php' : 'pages/tithes.php';
            } else if (cardClass.includes('receivables')) {
                window.location.href = isInPages ? 'clients.php' : 'pages/clients.php';
            }
        });
    });
    
    // Add form validation to all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.id && !validateForm(this.id)) {
                e.preventDefault();
                showAlert('Please fill in all required fields', 'error');
            }
        });
    });
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// Dark mode toggle (optional feature)
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// Load dark mode preference
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
}