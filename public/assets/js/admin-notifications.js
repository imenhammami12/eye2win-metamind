// public/assets/js/admin-notifications.js
// Real-time notification system for admin panel

(function() {
    'use strict';
    
    const POLL_INTERVAL = 20000; // 20 seconds
    
    // Play admin notification sound
    function playNotificationSound() {
        const audio = new Audio('/assets/sounds/admin-notification.mp3');
        audio.volume = 0.6;
        audio.play().catch(e => console.log('Audio blocked:', e));
    }
    
    // Update sidebar badges
    function updateBadges(stats) {
        // Update complaints badge
        const complaintsBadge = document.querySelector('.sidebar a[href*="complaints"] .badge');
        if (complaintsBadge) {
            if (stats.pendingComplaints > 0) {
                complaintsBadge.textContent = stats.pendingComplaints;
                complaintsBadge.style.display = 'inline-block';
            } else {
                complaintsBadge.style.display = 'none';
            }
        }
        
        // Update coach applications badge
        const coachBadge = document.querySelector('.sidebar a[href*="coach-applications"] .badge');
        if (coachBadge) {
            if (stats.pendingCoachApplications > 0) {
                coachBadge.textContent = stats.pendingCoachApplications;
                coachBadge.style.display = 'inline-block';
            } else {
                coachBadge.style.display = 'none';
            }
        }
    }
    
    // Show toast notification
    function showToast(notification) {
        // Determine color based on type
        let bgColor = '#667eea';
        if (notification.type === 'COMPLAINT_NEW' || notification.type === 'COACH_APPLICATION') {
            bgColor = '#f5576c';
        }
        
        const toast = document.createElement('div');
        toast.className = 'admin-notification-toast';
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 9999;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        `;
        
        toast.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 5px;">
                        ${notification.icon} ${notification.title}
                    </div>
                    <div style="font-size: 14px; opacity: 0.95;">
                        ${escapeHtml(notification.message)}
                    </div>
                    ${notification.link ? `
                        <a href="${escapeHtml(notification.link)}" 
                           style="display: inline-block; margin-top: 10px; padding: 5px 12px; background: rgba(255,255,255,0.2); border-radius: 4px; color: white; text-decoration: none; font-size: 13px;">
                            View Details
                        </a>
                    ` : ''}
                </div>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; padding: 0; margin-left: 10px;">
                    ×
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 8 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }, 8000);
    }
    
    // Check for new notifications
    async function checkNotifications() {
        try {
            const response = await fetch('/api/admin/notifications/check?t=' + Date.now(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success) {
                // Show toasts for new notifications
                if (data.newNotifications && data.newNotifications.length > 0) {
                    data.newNotifications.forEach(notif => {
                        showToast(notif);
                        playNotificationSound();
                    });
                }
            }
        } catch (error) {
            console.error('Error checking admin notifications:', error);
        }
    }
    
    // Update dashboard stats
    async function updateStats() {
        try {
            const response = await fetch('/api/admin/notifications/stats?t=' + Date.now(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.stats) {
                updateBadges(data.stats);
            }
        } catch (error) {
            console.error('Error updating stats:', error);
        }
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
        .admin-notification-toast:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.4);
        }
    `;
    document.head.appendChild(style);
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Check immediately
        checkNotifications();
        updateStats();
        
        // Then check periodically
        setInterval(checkNotifications, POLL_INTERVAL);
        setInterval(updateStats, 60000); // Update stats every minute
        
        console.log('✓ Admin notification system initialized');
    });
})();