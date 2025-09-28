</div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    
    <!-- Main JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/app.js"></script>
    <!-- Chatbot -->
    <script src="<?php echo SITE_URL; ?>/assets/chatbot/chatbot.js"></script>
    
    <!-- Page specific JavaScript -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $js; ?>.js"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Inline JavaScript -->
    <?php if (isset($page_js)): ?>
        <script><?php echo $page_js; ?></script>
    <?php endif; ?>
    
    <!-- Notifications Container -->
    <div class="notifications-container"></div>
    
    <!-- Toast notifications styles -->
    <style>
        .notifications-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        
        .notification {
            background: white;
            border-left: 4px solid var(--primary-wine);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            margin-bottom: 12px;
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideInRight 0.3s ease;
        }
        
        .notification-success {
            border-left-color: var(--success);
        }
        
        .notification-error {
            border-left-color: var(--error);
        }
        
        .notification-warning {
            border-left-color: var(--warning);
        }
        
        .notification-info {
            border-left-color: var(--info);
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--primary-black);
            margin-bottom: 4px;
        }
        
        .notification-message {
            color: var(--primary-gray);
            font-size: 14px;
            line-height: 1.4;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: var(--primary-gray);
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-close:hover {
            color: var(--primary-black);
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Alert styles for server-side messages */
        .alert {
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--info);
            color: var(--info);
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--warning);
            color: var(--warning);
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
        }
        
        .alert-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            margin-left: 12px;
        }
        
        /* Search dropdown styles */
        .search-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--gray-medium);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
        }
        
        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--primary-black);
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }
        
        .search-result-item:hover {
            background-color: var(--gray-light);
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-icon {
            width: 32px;
            height: 32px;
            background: var(--wine-light);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .search-result-content {
            flex: 1;
        }
        
        .search-result-title {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .search-result-title mark {
            background-color: var(--primary-wine);
            color: white;
            padding: 1px 2px;
            border-radius: 2px;
        }
        
        .search-result-type {
            font-size: 12px;
            color: var(--primary-gray);
            text-transform: uppercase;
        }
        
        .search-loading,
        .search-empty,
        .search-error {
            padding: 16px;
            text-align: center;
            color: var(--primary-gray);
            font-size: 14px;
        }
        
        .search-error {
            color: var(--error);
        }
        
        /* Notification item styles for dropdown */
        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-light);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .notification-item:hover {
            background-color: var(--gray-light);
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background-color: rgba(114, 47, 55, 0.05);
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background-color: var(--primary-wine);
            border-radius: 50%;
        }
        
        .notification-item.unread .notification-content {
            margin-left: 12px;
        }
        
        .notification-item .notification-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .notification-item .notification-message {
            font-size: 12px;
            color: var(--primary-gray);
            margin-bottom: 2px;
        }
        
        .notification-item .notification-time {
            font-size: 11px;
            color: var(--primary-gray);
        }
        
        .notification-empty {
            padding: 20px;
            text-align: center;
            color: var(--primary-gray);
            font-size: 14px;
        }
        
        .dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            background-color: var(--gray-light);
        }
        
        .dropdown-footer {
            padding: 8px 16px;
            border-top: 1px solid var(--gray-light);
            text-align: center;
            background-color: var(--gray-light);
        }
        
        .dropdown-footer a {
            color: var(--primary-wine);
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
        }
        
        .dropdown-footer a:hover {
            text-decoration: underline;
        }
        
        .mark-all-read {
            color: var(--primary-wine);
            text-decoration: none;
            font-size: 12px;
            font-weight: normal;
        }
        
        .mark-all-read:hover {
            text-decoration: underline;
        }
    </style>
    
    <script>
        // Auto-close alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const closeBtn = alert.querySelector('.alert-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => {
                        alert.style.display = 'none';
                    });
                }
                
                // Auto close after 5 seconds
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            alert.style.display = 'none';
                        }, 300);
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>
